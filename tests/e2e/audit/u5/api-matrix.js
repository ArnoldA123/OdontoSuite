// Plan #13 (issue #368, U5) — layer 5: the money endpoints by role.
//
// The expected status is derived from `App\Authorization\Permissions::GRANTS`
// (dumped with PHP reflection) plus the two gates that are not in the catalog:
// the `cash.session` middleware on the drawer-gated writes and
// `CashRegisterPolicy::view` on the session reads. Both are resolved from what
// the API answers for the role's own drawer, read once and recorded.
//
// Reads are real; writes carry an invalid or neutral body, so an authorized role
// observes 422 and an unauthorized one 403. The three writes that legitimately
// answer something else are declared: the CSV export streams a file (no data
// written), the receipt renders a PDF from an existing transaction, and
// `generate-quotation` is pointed at an appointment that already has a quotation,
// which the service returns without writing (the quotation count is compared).
//
// Nothing here mutates the ledger. The only row this module could write is a
// quotation, and that path is proved inert by the count.

import { execFileSync } from 'node:child_process'

import { test, expect } from 'playwright/test'

import { loginAs } from '../harness.js'

import { appointmentByKey, ensureFixtures, fixtureSummary } from './fixtures.js'

import { CASH_ROUTE, RTB_ROUTE, audit, call, localDay, stamp } from './helpers.js'

const ROLES = Object.freeze([
  'administrador',
  'recepcionista',
  'odontologo',
  'implantologo',
  'tecnico_dental',
  'asistente',
  'finanzas'
])

const PERMISSIONS = Object.freeze({
  viewCashRegister: 'view.cash_register',
  openCashRegister: 'open.cash_register',
  closeCashRegister: 'close.cash_register',
  manageCashMovement: 'manage.cash_movement',
  manageCashDeposit: 'manage.cash_deposit',
  manageTransaction: 'manage.transaction',
  createTransaction: 'create.transaction',
  voidTransaction: 'void.transaction',
  viewPendingPayment: 'view.pending_payment',
  payPendingPayment: 'pay.pending_payment',
  viewCashReport: 'view.cash_report',
  exportCashReport: 'export.cash_report',
  viewBilling: 'view.billing',
  createQuotationFromAppointment: 'create.quotation_from_appointment',
  viewPaymentMethod: 'view.payment_method',
  viewBranch: 'view.branch',
  overrideAccountingLock: 'override.accounting_lock'
})

const phpJson = script =>
  JSON.parse(
    execFileSync('php', ['-r', script], {
      cwd: process.cwd(),
      encoding: 'utf8',
      timeout: 20000
    })
  )

const dumpGrants = () =>
  phpJson(
    [
      "require 'vendor/autoload.php';",
      '$reflection = new ReflectionClass(App\\Authorization\\Permissions::class);',
      "echo json_encode($reflection->getReflectionConstant('GRANTS')->getValue(), JSON_UNESCAPED_SLASHES);"
    ].join(' ')
  )

/**
 * `CashRegisterPolicy::view` for the two facts the route permission does not
 * carry: the role and whether the session is the actor's own.
 */
const dumpSessionPolicy = () =>
  phpJson(
    [
      "require 'vendor/autoload.php';",
      '$policy = new App\\Policies\\CashRegisterPolicy();',
      '$user = new App\\Models\\User();',
      '$session = new App\\Models\\CashRegisterSession();',
      `$roles = ${JSON.stringify(ROLES)};`,
      '$out = [];',
      'foreach ($roles as $role) {',
      '  $user->role = $role;',
      '  $user->id = 999;',
      '  $session->user_id = 999;',
      '  $own = $policy->view($user, $session);',
      '  $session->user_id = 500;',
      '  $other = $policy->view($user, $session);',
      '  $out[$role] = ["own" => $own, "other" => $other];',
      '}',
      'echo json_encode($out);'
    ].join(' ')
  )

const endpoint = (key, method, path, permission, options = {}) => ({
  key,
  method,
  path,
  permission,
  mode: options.mode ?? 'read',
  body: options.body,
  authorizedStatus: options.authorizedStatus ?? 200,
  gate: options.gate ?? null,
  policy: options.policy ?? null,
  target: options.target ?? null
})

const buildEndpoints = targets => {
  const day = localDay()

  return [
    endpoint(
      'cash.current',
      'GET',
      () => '/api/cash-register/current',
      PERMISSIONS.viewCashRegister
    ),
    endpoint(
      'cash.sessions',
      'GET',
      () => '/api/cash-register/sessions',
      PERMISSIONS.viewCashRegister
    ),
    endpoint(
      'cash.movements',
      'GET',
      role => `/api/cash-register/sessions/${targets.sessionIds[role]}/movements`,
      PERMISSIONS.viewCashRegister,
      { policy: 'CashRegisterPolicy::view', target: 'the role’s own drawer, or another user’s' }
    ),
    endpoint(
      'cash.summary',
      'GET',
      () => '/api/cash-register/summary',
      PERMISSIONS.viewCashRegister
    ),
    endpoint(
      'pending.index',
      'GET',
      () => '/api/cash-register/pending-payments',
      PERMISSIONS.viewPendingPayment
    ),
    endpoint(
      'cash.deposits',
      'GET',
      () => '/api/cash-register/deposits',
      PERMISSIONS.viewCashRegister
    ),
    endpoint(
      'cash.reportPeriod',
      'GET',
      () => `/api/cash-register/reports/period?start_date=${day}&end_date=${day}`,
      PERMISSIONS.viewCashReport
    ),
    endpoint('transactions.index', 'GET', () => '/api/transactions', PERMISSIONS.manageTransaction),
    endpoint(
      'transactions.list',
      'GET',
      () => '/api/transactions/list',
      PERMISSIONS.manageTransaction
    ),
    endpoint(
      'transactions.show',
      'GET',
      () => `/api/transactions/${targets.transactionId}`,
      PERMISSIONS.manageTransaction
    ),
    endpoint(
      'billing.readyToBill',
      'GET',
      () => '/api/appointments/ready-to-bill',
      PERMISSIONS.viewBilling
    ),
    endpoint(
      'billing.preview',
      'GET',
      () => `/api/appointments/${targets.appointmentId}/payment-preview`,
      PERMISSIONS.viewBilling
    ),
    endpoint(
      'paymentMethods.active',
      'GET',
      () => '/api/payment-methods/active',
      PERMISSIONS.viewPaymentMethod
    ),
    endpoint('branches.active', 'GET', () => '/api/branches/active', PERMISSIONS.viewBranch),
    endpoint('cash.open', 'POST', () => '/api/cash-register/open', PERMISSIONS.openCashRegister, {
      mode: 'write',
      body: {},
      authorizedStatus: 422,
      target: 'invalid body (branch_id and opening_amount are required)'
    }),
    endpoint(
      'cash.close',
      'POST',
      () => '/api/cash-register/close',
      PERMISSIONS.closeCashRegister,
      {
        mode: 'write',
        body: {},
        authorizedStatus: 422,
        target: 'invalid body (session_id is required)'
      }
    ),
    endpoint(
      'cash.deposits.store',
      'POST',
      () => '/api/cash-register/deposits',
      PERMISSIONS.manageCashDeposit,
      { mode: 'write', body: {}, authorizedStatus: 422, target: 'invalid body' }
    ),
    endpoint(
      'pending.pay',
      'POST',
      () => `/api/pending-payments/${targets.appointmentId}/pay`,
      PERMISSIONS.payPendingPayment,
      { mode: 'write', body: {}, authorizedStatus: 422, target: 'invalid body' }
    ),
    endpoint(
      'transactions.store',
      'POST',
      () => '/api/transactions',
      PERMISSIONS.createTransaction,
      {
        mode: 'write',
        body: {},
        authorizedStatus: 422,
        target: 'invalid body'
      }
    ),
    endpoint(
      'transactions.void',
      'POST',
      () => `/api/transactions/${targets.closedTransactionId}/void`,
      PERMISSIONS.voidTransaction,
      {
        mode: 'write',
        body: { reason: 'AUDIT-U5 matrix probe' },
        authorizedStatus: 422,
        target: 'a transaction of a closed session (refused before any write)'
      }
    ),
    endpoint(
      'transactions.receipt',
      'POST',
      () => `/api/transactions/${targets.closedTransactionId}/receipt`,
      PERMISSIONS.createTransaction,
      {
        mode: 'write',
        authorizedStatus: 200,
        gate: 'cash.session',
        target: 'the draft of an existing transaction; no data written'
      }
    ),
    endpoint(
      'cashMovements.store',
      'POST',
      () => '/api/cash-movements',
      PERMISSIONS.manageCashMovement,
      {
        mode: 'write',
        body: {},
        authorizedStatus: 422,
        gate: 'cash.session',
        target: 'invalid body, or the drawer gate'
      }
    ),
    endpoint(
      'cash.reportExport',
      'POST',
      () => '/api/cash-register/reports/export/csv',
      PERMISSIONS.exportCashReport,
      {
        mode: 'write',
        body: { start_date: day, end_date: day, report_type: 'period' },
        authorizedStatus: 200,
        target: 'the CSV stream of the period; no data written'
      }
    ),
    endpoint(
      'billing.generateQuotation',
      'POST',
      () => `/api/appointments/${targets.quotationAppointmentId}/generate-quotation`,
      PERMISSIONS.createQuotationFromAppointment,
      {
        mode: 'write',
        body: {},
        authorizedStatus: 201,
        target: 'an appointment that already has a quotation: the service returns it'
      }
    )
  ]
}

/** The drawer each role actually owns, read once through its own token. */
const readDrawer = async (request, role, grants) => {
  const granted = (grants[PERMISSIONS.viewCashRegister] ?? []).includes(role)

  if (!granted) {
    return {
      granted: false,
      status: null,
      sessionId: null,
      owned: false,
      reason: 'no view.cash_register'
    }
  }

  const session = await loginAs(request, role)
  const response = await call(request, session, 'GET', '/api/cash-register/current')
  const payload = response.body?.data
  const drawer = payload?.session
    ? { id: payload.session.id, userId: payload.session.user_id, status: payload.session.status }
    : null

  return {
    granted: true,
    status: response.status,
    sessionId: drawer?.id ?? null,
    ownerId: drawer?.userId ?? null,
    owned: drawer ? drawer.userId === session.user.id : false,
    drawerStatus: drawer?.status ?? null
  }
}

const resolveClosedTransaction = async (request, admin) => {
  const sessions = await call(
    request,
    admin,
    'GET',
    '/api/cash-register/sessions?status=closed&per_page=5'
  )
  const rows = Array.isArray(sessions.body?.data) ? sessions.body.data : []

  for (const row of rows) {
    const transactions = await call(
      request,
      admin,
      'GET',
      `/api/transactions?cash_register_session_id=${row.id}&per_page=1`
    )
    const list = Array.isArray(transactions.body?.data) ? transactions.body.data : []

    if (list.length > 0) {
      return {
        sessionId: row.id,
        transactionId: list[0].id,
        status: transactions.status
      }
    }
  }

  return { sessionId: null, transactionId: null, status: null }
}

test('@U5 · api-matrix · rol × endpoint', async ({ request }) => {
  test.setTimeout(600000)

  const fixtures = await ensureFixtures(request)
  const admin = await loginAs(request, 'administrador')
  const grants = dumpGrants()
  const sessionPolicy = dumpSessionPolicy()
  const closed = await resolveClosedTransaction(request, admin)

  const evidence = {
    session: 'U5',
    routes: [CASH_ROUTE, RTB_ROUTE],
    ...stamp(),
    grantsSource: 'App\\Authorization\\Permissions::GRANTS via ReflectionClassConstant',
    policySource: 'App\\Policies\\CashRegisterPolicy::view via instance call',
    grants,
    sessionPolicy,
    roles: [...ROLES],
    endpoints: [],
    targets: null,
    drawer: {},
    rows: [],
    summary: null,
    skipped: [],
    fixtures: fixtureSummary(fixtures)
  }

  const drawer = {}

  for (const role of ROLES) {
    drawer[role] = await readDrawer(request, role, grants)
  }

  evidence.drawer = drawer

  // A session id every role can be pointed at: its own when it has one, the
  // administrator's view otherwise.
  const fallbackSession = Object.values(drawer).find(entry => entry.sessionId)?.sessionId ?? null
  const sessionIds = Object.fromEntries(
    ROLES.map(role => [role, drawer[role].sessionId ?? fallbackSession])
  )

  // The appointment that already has a quotation: the one the ready-to-bill row
  // generated. If that row did not run, the probe is skipped with its reason.
  const quotations = await call(request, admin, 'GET', '/api/quotations?per_page=100')
  const quotationRows = Array.isArray(quotations.body?.data) ? quotations.body.data : []
  const fromFixture = (fixtures.appointments ?? []).find(appointment =>
    quotationRows.some(row => row.appointment_id === appointment.id)
  )
  // The ready-to-bill row may have generated a quotation for a previous
  // fixture population. Any appointment that already has one makes the door
  // inert, so the first quotation whose appointment still exists is used.
  let withQuotation = fromFixture ? { id: fromFixture.id, source: 'fixture' } : null

  if (!withQuotation) {
    for (const row of quotationRows) {
      if (row.appointment_id === null || row.appointment_id === undefined) continue
      const exists = await call(request, admin, 'GET', `/api/appointments/${row.appointment_id}`)
      if (exists.status === 200) {
        withQuotation = { id: row.appointment_id, source: 'existing quotation' }
        break
      }
    }
  }
  const quotationCountBefore = Number(quotations.body?.meta?.total ?? quotationRows.length)

  const targets = {
    sessionIds,
    transactionId: closed.transactionId,
    closedTransactionId: closed.transactionId,
    appointmentId:
      appointmentByKey(fixtures, 'a3')?.id ?? appointmentByKey(fixtures, 'a1')?.id ?? null,
    quotationAppointmentId: withQuotation?.id ?? null
  }

  evidence.targets = {
    ...targets,
    closedSessionId: closed.sessionId,
    quotationAppointment: withQuotation
      ? { id: withQuotation.id, source: withQuotation.source }
      : null,
    quotationCountBefore
  }

  if (!targets.transactionId) {
    evidence.skipped.push({
      endpoints: ['transactions.show', 'transactions.void', 'transactions.receipt'],
      reason: 'no transaction of a closed session was resolvable'
    })
  }

  if (!targets.quotationAppointmentId) {
    evidence.skipped.push({
      endpoints: ['billing.generateQuotation'],
      reason: 'no appointment with a quotation was resolvable, so the door cannot be proved inert'
    })
  }

  const endpoints = buildEndpoints(targets).filter(entry => {
    if (entry.key === 'billing.generateQuotation' && !targets.quotationAppointmentId) return false
    if (
      ['transactions.show', 'transactions.void', 'transactions.receipt'].includes(entry.key) &&
      !targets.transactionId
    ) {
      return false
    }
    return true
  })

  evidence.endpoints = endpoints.map(entry => ({
    key: entry.key,
    method: entry.method,
    permission: entry.permission,
    mode: entry.mode,
    authorizedStatus: entry.authorizedStatus,
    gate: entry.gate,
    policy: entry.policy,
    target: entry.target
  }))

  for (const entry of endpoints) {
    const grantedTo = grants[entry.permission] ?? []

    if (!Object.hasOwn(grants, entry.permission)) {
      evidence.rows.push({
        endpoint: entry.key,
        role: null,
        permission: entry.permission,
        expected: 'permission not in GRANTS',
        observed: null,
        ok: false,
        reason: 'the endpoint names a permission the catalog does not grant to anyone'
      })
      continue
    }

    for (const role of ROLES) {
      const granted = grantedTo.includes(role)
      let expected = granted ? entry.authorizedStatus : 403
      let gateNote = null

      if (granted && entry.policy === 'CashRegisterPolicy::view') {
        const own = drawer[role].owned === true
        const allowed = sessionPolicy[role]?.[own ? 'own' : 'other'] === true
        expected = allowed ? 200 : 403
        gateNote = own ? 'own drawer' : 'another user’s drawer'
      }

      if (granted && entry.gate === 'cash.session') {
        const hasDrawer = drawer[role].sessionId !== null && drawer[role].owned === true
        expected = hasDrawer ? entry.authorizedStatus : 422
        gateNote = hasDrawer ? 'open drawer' : 'no open drawer (422 NO_ACTIVE_SESSION)'
      }

      const path = typeof entry.path === 'function' ? entry.path(role) : entry.path
      const session = await loginAs(request, role)
      const body = entry.mode === 'write' ? entry.body : undefined
      const response = await call(request, session, entry.method, path, body)

      evidence.rows.push({
        endpoint: entry.key,
        method: entry.method,
        role,
        permission: entry.permission,
        granted,
        expected,
        observed: response.status,
        ok: response.status === expected,
        gate: gateNote,
        path,
        target: entry.target,
        message: response.message,
        contentType: response.contentType,
        ...(response.status === expected ? {} : { body: response.body })
      })
    }
  }

  // The quotation door is inert only if the count did not move.
  if (targets.quotationAppointmentId) {
    const after = await call(request, admin, 'GET', '/api/quotations?per_page=1')
    const quotationCountAfter = Number(after.body?.meta?.total ?? 0)
    evidence.targets.quotationCountAfter = quotationCountAfter
    evidence.targets.quotationDoorWrote =
      quotationCountAfter !== Number(evidence.targets.quotationCountBefore)
  }

  const live = evidence.rows.filter(row => row.ok !== null)
  const expected403 = live.filter(row => row.expected === 403)

  evidence.summary = {
    rows: evidence.rows.length,
    live: live.length,
    ok: live.filter(row => row.ok === true).length,
    mismatches: live
      .filter(row => row.ok !== true)
      .map(row => ({
        endpoint: row.endpoint,
        role: row.role,
        expected: row.expected,
        observed: row.observed
      })),
    skipped: evidence.rows.length - live.length
  }

  evidence.expected403s = {
    total: expected403.length,
    observed403: expected403.filter(row => row.observed === 403).length,
    missing: expected403
      .filter(row => row.observed !== 403)
      .map(row => ({ endpoint: row.endpoint, role: row.role, observed: row.observed }))
  }

  evidence.verdict = evidence.summary.mismatches.length === 0 ? 'OK' : 'DEFECTO'

  audit('api-matrix.json', evidence)

  expect(evidence.summary.rows, 'every endpoint probed for every role').toBe(
    endpoints.length * ROLES.length
  )
  expect(
    evidence.rows.filter(row => row.permission && !Object.hasOwn(grants, row.permission))
  ).toEqual([])
  expect(evidence.expected403s.observed403, 'every expected 403 was observed').toBe(
    evidence.expected403s.total
  )
})
