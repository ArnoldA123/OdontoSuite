// Plan #13 (issue #372, U9) — layer 4: branch transfers, action by action.
//
// `/branch-transfers` is the only surface of the desk with a self-service door
// and a group-scope decision, so it is walked from three seats: the requester
// (reception), administration (gerencia) and a session-owned account affiliated
// to a sede. The two rules #305 wrote are measured where the user meets them:
//  * one open request per user — the second submit answers 409 and the message
//    has to land in the form that caused it;
//  * nobody decides their own request — the surface must not offer the control
//    and the API answers 403 (quoted with its own envelope).
//
// The walk never decides nor cancels a seeded row: the approvals and rejections
// land on the fixture requesters of this session, the cancel lands on the row
// the walk just created, and the pending row of the last step is retired in the
// `finally` block so no request stays open.
//
// `DEFECTO` / `NO-OP` / `CONFUSO` are evidence, never a thrown failure: the only
// hard assertions are that no `pageerror` was raised and that every declared
// step was recorded.

import { test, expect } from 'playwright/test'

import { PASSWORD, classifyFailures, collectDiagnostics, primeSession } from '../harness.js'

import { ensureFixtures, fixtureSummary, loginCredential } from './fixtures.js'

import {
  DESKTOP,
  SESSION,
  api,
  audit,
  classifyConsole,
  closeDialog,
  confirmDialog,
  dismissToasts,
  fieldErrors,
  fillField,
  gotoSurface,
  lastCall,
  login,
  markExpected,
  pickOption,
  readSelectOptions,
  requestOf,
  respondTo,
  settle,
  shot,
  toastText,
  visibleTexts,
  walkCounts,
  walkStep,
  watchRequests
} from './helpers.js'

const SURFACE = '/branch-transfers'
const SLUG = 'transfers'
const MARKER = 'AUDIT-U9'
const ROWS = 'main .data-table-row'
const BRANCH_TRIGGER = '[data-transfer-branch] button'

// The seven columns the table declares, in its own order.
const COLUMNS = [
  'solicitante',
  'trayecto',
  'motivo',
  'estado',
  'solicitada',
  'decidida',
  'acciones'
]

const DECLARED_CONSOLE = [
  {
    pattern: /Failed to load resource: .*409/,
    reason:
      'el paso `second-open` envía una segunda solicitud abierta: el 409 es el contrato que se mide'
  },
  {
    pattern: /Failed to load resource: .*500/,
    reason: 'el paso `states` provoca un 500 para medir el estado de error de la lista'
  }
]

const listRows = session => api(session, 'GET', '/api/branch-transfers?per_page=100')

// The very query the page issues: `useBranchTransfers` sends no `per_page`, so
// the row count the surface paints has to be compared against this page size.
const pageQuery = session => api(session, 'GET', '/api/branch-transfers')

const rowsOf = response => (Array.isArray(response?.body?.data) ? response.body.data : [])

const apiRowForId = async (session, id) =>
  rowsOf(await listRows(session)).find(row => row.id === id) ?? null

/** Finds the table row whose cells quote the API row's reason. */
const uiRowFor = (state, reason) =>
  state.rows.find(row => row.cells.some(cell => cell && cell.includes(String(reason ?? '')))) ??
  null

/**
 * Everything the surface paints about the list, the form and the decisions.
 *
 * The table, the request form and the decision cards are read together because
 * the walkthrough asks about their relationship: which rows the actor may
 * withdraw, which ones it may decide and what the form says when it refuses.
 */
const tableState = page =>
  page.evaluate(() => {
    const norm = value => (value ?? '').toString().trim().replace(/\s+/g, ' ')
    const text = node => norm(node?.innerText)
    const rows = Array.from(document.querySelectorAll('main .data-table-row')).map(row => {
      const cancel = row.querySelector('[data-transfer-cancel]')

      return {
        applicant: text(row.querySelector('[data-transfer-applicant]')) || null,
        route: text(row.querySelector('[data-transfer-route]')) || null,
        cells: Array.from(row.querySelectorAll('td')).map(cell => text(cell)),
        cancelId: cancel ? cancel.getAttribute('data-transfer-cancel') : null,
        hasCancel: Boolean(cancel)
      }
    })
    const decisions = Array.from(document.querySelectorAll('[data-decision-row]')).map(card => ({
      id: card.getAttribute('data-decision-id'),
      applicant: text(card.querySelector('[data-decision-applicant]')) || null,
      reason: text(card.querySelector('[data-decision-reason]')) || null
    }))

    return {
      rows,
      decisions,
      headers: Array.from(document.querySelectorAll('main thead th')).map(text).filter(Boolean),
      currentBranch: text(document.querySelector('[data-branch-current-name]')) || null,
      form: Boolean(document.querySelector('[data-transfer-form]')),
      formError: text(document.querySelector('[data-transfer-form-error]')) || null,
      submitHint: text(document.querySelector('[data-transfer-submit-hint]')) || null,
      decisionsSection: Boolean(document.querySelector('[data-transfers-decisions]')),
      h1: Array.from(document.querySelectorAll('h1')).map(text).filter(Boolean)
    }
  })

test('@U9 walks traslados', async ({ page }) => {
  test.setTimeout(420000)

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const admin = await login('administrador')
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)

  // The two requesters the flow uses: reception reads its own row and probes the
  // empty form, the fixture administrator owns the rows the walk creates and
  // cancels.
  const requester = await loginCredential(fixtures.requesters.cancel.username)
  const selfSession = await loginCredential(fixtures.requesters.self.username)

  const targetId = fixtures.branches.target.id
  const targetName = fixtures.branches.target.name
  const createdReason = `${MARKER}-${fixtures.token} solicitud creada por la caminata`
  const secondReason = `${MARKER}-${fixtures.token} segunda solicitud de la caminata`
  const selfReason = `${MARKER}-${fixtures.token} solicitud propia de la caminata`
  const approveNotes = `${MARKER}-${fixtures.token} aprobación de la caminata`
  const rejectNotes = `${MARKER}-${fixtures.token} rechazo de la caminata`

  const created = {
    sedeAccount: null,
    requests: [],
    approvals: [],
    cancelled: [],
    retired: false
  }

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks traslados',
    role: 'administrador',
    roles: ['administrador', 'recepcionista'],
    steps: [],
    counts: null,
    fixtures: { marker: fixtures.marker, ok: fixtures.ok, token: fixtures.token },
    created,
    pageErrors: [],
    consoleErrors: [],
    consoleClassified: null,
    failedRequests: null,
    cleanup: []
  }

  const step = walkStep(page, evidence)

  page.setDefaultTimeout(8000)

  try {
    await page.setViewportSize(DESKTOP)

    await step(
      'list-requester',
      'Abrir /branch-transfers con la cuenta de recepción que tiene una solicitud abierta',
      'La lista pinta sólo las solicitudes propias, con las siete columnas, el trayecto, el estado y el control de retiro de la propia; la sección de decisión no se ofrece',
      async entry => {
        await primeSession(page, requester)
        const opened = await gotoSurface(page, SURFACE)

        const painted = await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        const state = await tableState(page)
        const listed = await listRows(requester)
        const pageRows = rowsOf(await pageQuery(requester))
        const rows = rowsOf(listed)
        const own = rows.every(row => row.user_id === requester.user.id)
        const pending = rows.filter(row => row.status === 'pending').length
        const cancellable = state.rows.filter(row => row.hasCancel).length
        const headersNamed = COLUMNS.every(label =>
          state.headers.some(header => header.toLowerCase() === label)
        )
        const call = lastCall(requests, 'GET', '/api/branch-transfers')

        entry.obtained = `${state.rows.length} fila(s) en pantalla · GET ${listed.status ?? 'sin respuesta'} total ${listed.body?.meta?.total ?? '—'} (${rows.length} fila(s) leídas, ${pageRows.length} en la página que pide el SPA, todas propias: ${own}) · estados ${JSON.stringify(rows.map(row => row.status))} · cabeceras ${JSON.stringify(state.headers)} · «Mi sede actual» «${state.currentBranch ?? '—'}» · formulario de solicitud ${state.form} · sección de decisión ${state.decisionsSection} · retire ofrecido en ${cancellable} fila(s) de ${pending} pendiente(s) · h1 ${JSON.stringify(state.h1)}`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = {
          painted,
          opened,
          state,
          api: {
            status: listed.status,
            total: listed.body?.meta?.total ?? null,
            rows: rows.map(row => ({
              id: row.id,
              status: row.status,
              userId: row.user_id,
              reason: row.reason
            }))
          },
          headersNamed,
          cancellable,
          pending,
          pageRows: pageRows.length
        }
        entry.verdict =
          painted &&
          listed.status === 200 &&
          rows.length > 0 &&
          own &&
          state.rows.length === pageRows.length &&
          headersNamed &&
          cancellable === pending &&
          !state.decisionsSection &&
          state.form &&
          state.h1.length === 1
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la lista no se limita a las solicitudes propias, no publica las siete columnas o el control de retiro no coincide con lo que el actor puede cerrar'

        return entry
      }
    )

    await step(
      'list-admin',
      'Abrir la misma superficie como administración',
      'La administración lee todas las solicitudes del grupo y sólo ofrece decidir las ajenas pendientes; ninguna fila ajena ofrece «Cancelar»',
      async entry => {
        await primeSession(page, admin)
        await gotoSurface(page, SURFACE)

        const painted = await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        const state = await tableState(page)
        const listed = await listRows(admin)
        const pageRows = rowsOf(await pageQuery(admin))
        const rows = rowsOf(listed)
        const pendingOthers = rows.filter(
          row => row.status === 'pending' && row.user_id !== admin.user.id
        )
        const pendingOwn = rows.filter(
          row => row.status === 'pending' && row.user_id === admin.user.id
        )
        const decisionIds = state.decisions.map(card => String(card.id))
        const onlyForeign = pendingOthers.every(row => decisionIds.includes(String(row.id)))
        const ownCard = pendingOwn.some(row => decisionIds.includes(String(row.id)))
        const cancellable = state.rows.filter(row => row.hasCancel).length
        const call = lastCall(requests, 'GET', '/api/branch-transfers')

        entry.obtained = `${state.rows.length} fila(s) en pantalla · GET ${listed.status ?? 'sin respuesta'} total ${listed.body?.meta?.total ?? '—'} (${pageRows.length} en la página que pide el SPA) · ${pendingOthers.length} ajena(s) pendiente(s) con tarjeta de decisión ${JSON.stringify(decisionIds)} · tarjeta de solicitud propia ${ownCard} · retire ofrecido en ${cancellable} fila(s) (propias pendientes: ${pendingOwn.length}) · sección de decisión ${state.decisionsSection}`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = {
          painted,
          state,
          api: {
            status: listed.status,
            total: listed.body?.meta?.total ?? null,
            pendingOthers: pendingOthers.map(row => row.id),
            pendingOwn: pendingOwn.map(row => row.id)
          },
          decisionIds,
          onlyForeign,
          ownCard,
          cancellable,
          pageRows: pageRows.length
        }
        entry.verdict =
          painted &&
          listed.status === 200 &&
          state.rows.length === pageRows.length &&
          state.decisions.length === pendingOthers.length &&
          onlyForeign &&
          !ownCard &&
          cancellable === pendingOwn.length &&
          state.decisionsSection
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la administración no ve todas las solicitudes, ofrece decidir una ajena que la API no permite o deja de ofrecer el retiro de la propia'

        return entry
      }
    )

    await step(
      'destinations',
      'Crear una cuenta de la sesión con sede propia y abrir su formulario de traslado',
      'El selector de destino ofrece exactamente las sedes activas menos la sede propia del actor, y «Mi sede actual» la nombra',
      async entry => {
        // Every fixture requester has group scope, so the "exclude my own sede"
        // rule has no observable effect for them. This account is written by the
        // session, affiliated to the fixture branch, and retired in `finally`.
        const username = `audit_u9_sede_${fixtures.token}`
        const account = await api(admin, 'POST', '/api/users', {
          name: `${MARKER}-${fixtures.token} solicitante con sede`,
          username,
          email: `audit-u9-sede-${fixtures.token}@audit.local`,
          password: PASSWORD,
          role: 'recepcionista',
          branch_id: targetId,
          is_active: true
        })

        const accountId = account.body?.data?.id ?? null

        if (accountId) {
          created.sedeAccount = { id: accountId, username }
        }

        if (!accountId) {
          const refusal = account.body?.errors
            ? JSON.stringify(account.body.errors)
            : (account.body?.message ?? '—')

          entry.obtained = `POST /api/users con branch_id=${targetId} respondió ${account.status ?? 'sin respuesta'} «${refusal}»: no hay sesión con sede que medir`
          entry.verdict = 'NO PROBADO'
          entry.notes =
            'la sesión no pudo crear su cuenta afiliada, así que la exclusión de la sede actual no se pudo observar'

          return entry
        }

        const sedeSession = await loginCredential(username)

        await primeSession(page, sedeSession)
        await gotoSurface(page, SURFACE)

        const painted = await page
          .locator('[data-transfer-form]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        const catalogue = await api(admin, 'GET', '/api/branch-transfers/destinations')
        const catalogueRows = rowsOf(catalogue)
        const offered = await readSelectOptions(page, BRANCH_TRIGGER)
        const options = offered.options ?? []
        const state = await tableState(page)
        const expected = catalogueRows
          .filter(branch => String(branch.id) !== String(targetId))
          .map(branch => branch.name)
        const sameCount = options.length === expected.length
        const sameNames =
          sameCount &&
          expected.every(name => options.includes(name)) &&
          options.length === expected.length
        const targetOffered = options.includes(targetName)
        const activeRows = catalogueRows.filter(branch => branch.is_active !== false).length

        entry.obtained = `cuenta ${account.status} #${accountId} (branch_id ${account.body?.data?.branch_id ?? '—'}) · catálogo GET /api/branch-transfers/destinations ${catalogue.status ?? 'sin respuesta'} ${catalogueRows.length} sede(s), ${activeRows} activa(s) · selector ${offered.ok ? `${options.length} opción(es) ${JSON.stringify(options)}` : `no abrió (${offered.reason})`} · esperadas ${JSON.stringify(expected)} · sede propia «${state.currentBranch ?? '—'}» ofrecida en el selector ${targetOffered}`
        entry.request = null
        entry.detail = {
          painted,
          account: { id: accountId, branchId: account.body?.data?.branch_id ?? null },
          catalogue: { status: catalogue.status, rows: catalogueRows.length, active: activeRows },
          offered,
          expected,
          targetOffered,
          currentBranch: state.currentBranch
        }
        entry.verdict =
          painted &&
          catalogue.status === 200 &&
          offered.ok &&
          sameNames &&
          !targetOffered &&
          state.currentBranch === targetName
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el selector de destino no coincide con las sedes activas, ofrece la sede propia del actor o «Mi sede actual» no la nombra'

        return entry
      }
    )

    await step(
      'create-empty',
      'Elegir sede de destino y enviar la solicitud sin motivo',
      'El envío sin motivo no registra nada, el botón deshabilitado explica qué falta y la API conserva su 422',
      async entry => {
        await primeSession(page, requester)
        await gotoSurface(page, SURFACE)

        const painted = await page
          .locator('[data-transfer-form]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        const initialHint =
          (await visibleTexts(page, '[data-transfer-submit-hint]'))[0]?.trim() ?? null
        const picked = await pickOption(page, BRANCH_TRIGGER, targetName)
        const hintAfterBranch =
          (await visibleTexts(page, '[data-transfer-submit-hint]'))[0]?.trim() ?? null
        const disabled = await page.locator('[data-transfer-submit]').isDisabled()

        const attempted = await respondTo(
          page,
          { method: 'POST', path: '/api/branch-transfers' },
          () => page.locator('[data-transfer-submit]').click({ timeout: 2500 }),
          2500
        )

        await page.waitForTimeout(300)

        const state = await tableState(page)
        const errors = await fieldErrors(page)
        const before = rowsOf(await listRows(requester)).length

        // The message the server publishes for the field the surface left empty:
        // measured with the requester's own token, and writes nothing (422).
        const probe = await api(requester, 'POST', '/api/branch-transfers', {
          to_branch_id: targetId
        })
        const serverMessage = probe.body?.errors?.reason?.[0] ?? null
        const hintExplains =
          Boolean(initialHint && /sede de destino/i.test(initialHint)) &&
          Boolean(hintAfterBranch && /motivo/i.test(hintAfterBranch))

        entry.obtained = `formulario ${painted} · pista inicial «${initialHint ?? 'ninguna'}» · destino «${picked.selected ?? `no elegido (${picked.reason})`}» · pista tras elegir sede «${hintAfterBranch ?? 'ninguna'}» · motivo vacío → «Enviar solicitud» ${disabled ? 'deshabilitado' : 'habilitado'} · POST desde la pantalla ${attempted.status ?? 'no se envió'} · mensaje en el formulario «${state.formError ?? 'ninguno'}» · errores de campo ${JSON.stringify(errors.messages)} · filas antes ${before} · la API responde ${probe.status ?? 'sin respuesta'} «${serverMessage ?? '—'}»`
        entry.request = requestOf(attempted)
        entry.detail = {
          painted,
          initialHint,
          picked,
          hintAfterBranch,
          hintExplains,
          disabled,
          attempted: { status: attempted.status, acted: attempted.acted },
          formError: state.formError,
          errors,
          serverMessage,
          rowsBefore: before
        }
        const controlClosed = painted && disabled && attempted.status === null
        const apiContract = probe.status === 422 && Boolean(serverMessage)

        entry.verdict =
          controlClosed && hintExplains && apiContract
            ? 'OK'
            : !controlClosed
              ? 'DEFECTO'
              : !hintExplains
                ? 'CONFUSO'
                : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : !controlClosed
              ? 'el envío sin motivo llega al servidor o el control no se apaga'
              : !hintExplains
                ? `el control se apaga sin decir qué falta: la pista «${hintAfterBranch ?? 'ausente'}» no nombra el motivo`
                : `la API no conserva el 422 con el motivo del campo («${serverMessage ?? 'sin mensaje'}»)`

        return entry
      }
    )

    await step(
      'create',
      'Enviar una solicitud válida desde una cuenta de solicitante de la sesión',
      'El POST responde 201, la fila nueva queda «Pendiente» con su motivo y el envío se confirma con un aviso',
      async entry => {
        await primeSession(page, selfSession)
        await gotoSurface(page, SURFACE)

        const picked = await pickOption(page, BRANCH_TRIGGER, targetName)
        const typed = await fillField(page, 'Motivo del traslado', createdReason)

        const sent = await respondTo(page, { method: 'POST', path: '/api/branch-transfers' }, () =>
          page.locator('[data-transfer-submit]').click({ timeout: 8000 })
        )

        await page.waitForTimeout(600)
        await settle(page)

        const toast = await toastText(page)
        const state = await tableState(page)
        const data = sent.body?.data ?? null
        const row = data ? uiRowFor(state, data.reason) : null

        if (data?.id) {
          created.requests.push({ id: data.id, step: 'create', status: data.status })
        }

        entry.obtained = `destino «${picked.selected ?? `no elegido (${picked.reason})`}» · motivo escrito ${typed.ok} · POST ${sent.status ?? 'sin petición'} · id ${data?.id ?? '—'} estado «${data?.status ?? '—'}» · fila en pantalla «${row ? `${row.applicant} ${row.route} ${row.cells[3]}` : 'no encontrada'}» · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          picked,
          typed,
          status: sent.status,
          body: data,
          toast,
          row
        }
        entry.verdict =
          sent.status === 201 &&
          data?.status === 'pending' &&
          Boolean(row) &&
          row?.cells?.[3] === 'Pendiente' &&
          /enviada/i.test(toast)
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la solicitud válida no queda pendiente en la lista o el envío no se confirma'

        return entry
      }
    )

    await step(
      'second-open',
      'Enviar una segunda solicitud mientras la primera sigue pendiente',
      'El 409 se pinta en el formulario que lo provocó, con su mensaje, y la lista no desaparece',
      async entry => {
        // Same session and same page: the row created by the previous step is
        // still the actor's open request.
        const picked = await pickOption(page, BRANCH_TRIGGER, targetName)
        await fillField(page, 'Motivo del traslado', secondReason)

        const sent = await respondTo(page, { method: 'POST', path: '/api/branch-transfers' }, () =>
          page.locator('[data-transfer-submit]').click({ timeout: 8000 })
        )

        await page.waitForTimeout(500)

        const state = await tableState(page)
        const errors = await fieldErrors(page)
        const serverMessage = sent.body?.message ?? null

        entry.obtained = `destino «${picked.selected ?? `no elegido (${picked.reason})`}» · POST ${sent.status ?? 'sin petición'} · mensaje de la API «${serverMessage ?? '—'}» · mensaje en el formulario «${state.formError ?? 'ninguno'}» · errores de campo ${JSON.stringify(errors.messages)} · ${state.rows.length} fila(s) siguen en pantalla`
        entry.request = requestOf(sent)
        entry.detail = {
          picked,
          status: sent.status,
          serverMessage,
          formError: state.formError,
          errors,
          rows: state.rows.length
        }
        entry.verdict =
          sent.status === 409 &&
          /solicitud de traslado en curso/i.test(String(state.formError ?? '')) &&
          state.rows.length > 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el conflicto de solicitud abierta no llega al formulario con su mensaje, o la lista se pierde al rechazarlo'

        return entry
      }
    )

    await step(
      'cancel-own',
      'Retirar la solicitud propia desde el control de su fila',
      'La confirmación ejecuta la cancelación, la fila pasa a «Cancelada» y el control de retiro desaparece',
      async entry => {
        const state = await tableState(page)
        const row = uiRowFor(state, createdReason)
        const cancelId = row?.cancelId ?? null

        if (!row || !cancelId) {
          entry.obtained = `la fila de la solicitud ${createdReason} no publica el control de retiro (fila ${row ? JSON.stringify(row) : 'no encontrada'})`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'la solicitud creada por la caminata no ofrece el control que la API acepta'

          return entry
        }

        await page.locator(`[data-transfer-cancel="${cancelId}"]`).click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const sent = await respondTo(
          page,
          { method: 'POST', path: `/api/branch-transfers/${cancelId}/cancel` },
          () => confirmDialog(page, 'Cancelar solicitud')
        )

        await page.waitForTimeout(600)
        await settle(page)

        const toast = await toastText(page)
        const after = await tableState(page)
        const cancelledRow = uiRowFor(after, createdReason)
        const stillOffered = after.rows.filter(entry => entry.cancelId === String(cancelId)).length

        if (sent.status === 200) {
          created.cancelled.push({ id: Number(cancelId), status: sent.body?.data?.status ?? null })
        }

        entry.obtained = `confirmación «${confirmText}» · POST /cancel ${sent.status ?? 'sin petición'} · estado devuelto «${sent.body?.data?.status ?? '—'}» · fila «${cancelledRow?.cells?.[3] ?? '—'}» · control de retiro en esa fila ${stillOffered} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          cancelId,
          confirmText,
          status: sent.status,
          body: sent.body?.data ?? null,
          toast,
          row: cancelledRow,
          stillOffered
        }
        entry.verdict =
          sent.status === 200 &&
          sent.body?.data?.status === 'cancelled' &&
          cancelledRow?.cells?.[3] === 'Cancelada' &&
          stillOffered === 0 &&
          /cancelada/i.test(toast)
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la cancelación no responde 200, la fila no pasa a «Cancelada» o el control de retiro sigue ofreciéndose'

        return entry
      }
    )

    await step(
      'approved',
      'Aprobar la solicitud pendiente de otro solicitante desde la tarjeta de decisión',
      'El POST responde 200, la fila pasa a «Aprobada» con su fecha de decisión, la tarjeta desaparece y el aviso confirma',
      async entry => {
        await primeSession(page, admin)
        await gotoSurface(page, SURFACE)

        const { id } = fixtures.transfers.approve
        const before = await apiRowForId(admin, id)
        const state = await tableState(page)
        const card = state.decisions.find(entry => String(entry.id) === String(id)) ?? null

        if (!card || !before) {
          entry.obtained = `la solicitud ${id} no tiene tarjeta de decisión (${state.decisions.length} tarjeta(s)) ni fila en la API`
          entry.verdict = 'NO PROBADO'
          entry.notes =
            'la solicitud pendiente del solicitante de fixtures no está disponible para decidir'

          return entry
        }

        await page
          .locator(`[data-decision-notes="${id}"] input`)
          .fill(approveNotes, { timeout: 8000 })
          .catch(() => {})

        const sent = await respondTo(
          page,
          { method: 'POST', path: `/api/branch-transfers/${id}/approve` },
          () => page.locator(`[data-decision-approve="${id}"]`).click({ timeout: 8000 })
        )

        await page.waitForTimeout(600)
        await settle(page)

        const toast = await toastText(page)
        const after = await tableState(page)
        const data = sent.body?.data ?? null
        const row = data ? uiRowFor(after, before.reason) : null
        const cardGone = !after.decisions.some(entry => String(entry.id) === String(id))

        if (sent.status === 200) {
          created.approvals.push({ id, status: data?.status ?? null })
        }

        entry.obtained = `notas «${approveNotes}» · POST /approve ${sent.status ?? 'sin petición'} · estado «${data?.status ?? '—'}» · decided_by ${data?.decided_by ?? '—'} (${data?.decider?.name ?? 'sin nombre'}) · decided_at ${data?.decided_at ?? '—'} · notas devueltas «${data?.decision_notes ?? '—'}» · fila «${row ? `${row.applicant} ${row.cells[3]} decidida «${row.cells[5]}»` : 'no encontrada'}» · tarjeta retirada ${cardGone} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          id,
          notes: approveNotes,
          status: sent.status,
          body: data,
          decider: data?.decider ?? null,
          toast,
          row,
          cardGone,
          decisions: after.decisions.map(entry => entry.id)
        }
        entry.verdict =
          sent.status === 200 &&
          data?.status === 'approved' &&
          data?.decided_by != null &&
          Boolean(data?.decided_at) &&
          row?.cells?.[3] === 'Aprobada' &&
          Boolean(row?.cells?.[5]) &&
          row?.cells?.[5] !== '—' &&
          cardGone &&
          /aprobada/i.test(toast)
            ? 'OK'
            : 'DEFECTO'
        // The row publishes the decision date; who decided travels only in the
        // payload. The fact is recorded here instead of being left implicit.
        entry.notes =
          entry.verdict !== 'OK'
            ? 'la aprobación no responde 200, la fila no publica «Aprobada» con su fecha de decisión o la tarjeta sigue ofreciendo decidirla'
            : 'la fila publica la fecha de decisión pero no al decidor: `decided_by` y su nombre viajan sólo en el payload'

        return entry
      }
    )

    await step(
      'rejected',
      'Rechazar la solicitud pendiente de otro solicitante con notas de la decisión',
      'El POST responde 200, la fila pasa a «Rechazada» con su fecha y la tarjeta desaparece',
      async entry => {
        await primeSession(page, admin)
        await gotoSurface(page, SURFACE)

        const { id } = fixtures.transfers.reject
        const before = await apiRowForId(admin, id)
        const state = await tableState(page)
        const card = state.decisions.find(entry => String(entry.id) === String(id)) ?? null

        if (!card || !before) {
          entry.obtained = `la solicitud ${id} no tiene tarjeta de decisión (${state.decisions.length} tarjeta(s)) ni fila en la API`
          entry.verdict = 'NO PROBADO'
          entry.notes =
            'la solicitud pendiente del solicitante de fixtures no está disponible para rechazar'

          return entry
        }

        await page
          .locator(`[data-decision-notes="${id}"] input`)
          .fill(rejectNotes, { timeout: 8000 })
          .catch(() => {})

        const sent = await respondTo(
          page,
          { method: 'POST', path: `/api/branch-transfers/${id}/reject` },
          () => page.locator(`[data-decision-reject="${id}"]`).click({ timeout: 8000 })
        )

        await page.waitForTimeout(600)
        await settle(page)

        const toast = await toastText(page)
        const after = await tableState(page)
        const data = sent.body?.data ?? null
        const row = data ? uiRowFor(after, before.reason) : null
        const cardGone = !after.decisions.some(entry => String(entry.id) === String(id))

        entry.obtained = `notas «${rejectNotes}» · POST /reject ${sent.status ?? 'sin petición'} · estado «${data?.status ?? '—'}» · decided_by ${data?.decided_by ?? '—'} · decided_at ${data?.decided_at ?? '—'} · notas devueltas «${data?.decision_notes ?? '—'}» · fila «${row ? `${row.applicant} ${row.cells[3]} decidida «${row.cells[5]}»` : 'no encontrada'}» · tarjeta retirada ${cardGone} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          id,
          notes: rejectNotes,
          status: sent.status,
          body: data,
          toast,
          row,
          cardGone
        }
        entry.verdict =
          sent.status === 200 &&
          data?.status === 'rejected' &&
          data?.decision_notes === rejectNotes &&
          Boolean(data?.decided_at) &&
          row?.cells?.[3] === 'Rechazada' &&
          Boolean(row?.cells?.[5]) &&
          row?.cells?.[5] !== '—' &&
          cardGone &&
          /rechazada/i.test(toast)
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el rechazo no responde 200 con sus notas, la fila no publica «Rechazada» con su fecha o la tarjeta sigue ofreciendo decidirla'

        return entry
      }
    )

    await step(
      'self-decision',
      'Crear una solicitud propia como administración e intentar decidirla',
      'La administración no encuentra el control de decisión en su propia solicitud y la API responde 403 al intento',
      async entry => {
        await primeSession(page, selfSession)
        await gotoSurface(page, SURFACE)

        const picked = await pickOption(page, BRANCH_TRIGGER, targetName)
        await fillField(page, 'Motivo del traslado', selfReason)

        const sent = await respondTo(page, { method: 'POST', path: '/api/branch-transfers' }, () =>
          page.locator('[data-transfer-submit]').click({ timeout: 8000 })
        )

        await page.waitForTimeout(600)
        await settle(page)

        const data = sent.body?.data ?? null
        const id = data?.id ?? null

        if (!id) {
          entry.obtained = `destino «${picked.selected ?? `no elegido (${picked.reason})`}» · POST ${sent.status ?? 'no se envió'} «${sent.body?.message ?? '—'}»: no hay solicitud propia que decidir`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'la cuenta de administración de la sesión no pudo abrir su propia solicitud'

          return entry
        }

        created.requests.push({ id, step: 'self-decision', status: data.status })

        const state = await tableState(page)
        const row = uiRowFor(state, selfReason)
        const ownCard = state.decisions.find(card => String(card.id) === String(id)) ?? null
        const rowControls = row ? row.hasCancel : null

        const approve = await api(selfSession, 'POST', `/api/branch-transfers/${id}/approve`, {
          decision_notes: `${MARKER}-${fixtures.token} decisión propia`
        })
        const reject = await api(selfSession, 'POST', `/api/branch-transfers/${id}/reject`, {
          decision_notes: `${MARKER}-${fixtures.token} decisión propia`
        })

        const envelope = approve.body?.message ?? null
        const domain = approve.body?.error ?? reject.body?.error ?? null

        entry.obtained = `solicitud propia #${id} «${data.status}» · tarjetas de decisión ${state.decisions.length} (propia ${ownCard ? 'ofrecida' : 'no ofrecida'}) · fila propia con control de retiro ${rowControls} · POST /approve ${approve.status ?? 'sin respuesta'} «${envelope ?? '—'}» · POST /reject ${reject.status ?? 'sin respuesta'} · mensaje de dominio «${domain ?? 'no publicado'}»`
        entry.request = {
          method: 'POST',
          path: `/api/branch-transfers/${id}/approve`,
          status: approve.status
        }
        entry.detail = {
          id,
          status: data.status,
          requestStatus: sent.status,
          decisions: state.decisions.map(card => card.id),
          ownCard,
          row,
          approve: { status: approve.status, body: approve.body },
          reject: { status: reject.status, body: reject.body }
        }

        if (ownCard) {
          // The surface does offer it: press it and read where the refusal lands.
          const pushed = await respondTo(
            page,
            { method: 'POST', path: `/api/branch-transfers/${id}/approve` },
            () => page.locator(`[data-decision-approve="${id}"]`).click({ timeout: 8000 })
          )
          await page.waitForTimeout(500)

          const after = await tableState(page)
          const message = await visibleTexts(page, `[data-decision-error="${id}"]`)
          const shown = message.join(' ').trim()

          entry.request = requestOf(pushed)
          entry.obtained = `${entry.obtained} · control presente: POST desde la pantalla ${pushed.status ?? 'sin petición'} · mensaje en la tarjeta «${shown || 'ninguno'}»`
          entry.detail.pushed = { status: pushed.status, shown, decisions: after.decisions.length }
          entry.verdict =
            pushed.status === 403 && /propia/i.test(shown)
              ? 'OK'
              : pushed.status === 403
                ? 'CONFUSO'
                : 'DEFECTO'
          entry.notes =
            entry.verdict === 'OK'
              ? ''
              : pushed.status === 403
                ? `la regla se cumple pero la pantalla sólo muestra el sobre genérico («${shown || 'sin mensaje'}»), no la causa: el mensaje de dominio («${domain ?? 'no publicado'}») no llega al usuario`
                : 'el intento de decidir la propia solicitud no responde 403 o no se pinta en la tarjeta'
        } else {
          entry.verdict = 'NO PROBADO'
          entry.notes =
            'la superficie filtra la solicitud propia de «Solicitudes por decidir» (`pendingRequests` excluye `isOwnRequest`) y su fila sólo ofrece «Cancelar»: no hay control de decisión que pulsar, así que el 403 sólo es medible contra la API y no llega a la pantalla. Se registra la observación en lugar del defecto que no existe.'
        }

        return entry
      }
    )

    await step(
      'states',
      'Medir el estado de carga, el vacío del servicio y el error con reintento',
      'La lista muestra su placeholder de carga, un vacío declarado y un error con reintento que recarga',
      async entry => {
        await primeSession(page, admin)

        const detail = { loading: null, empty: null, error: null }

        await page.route('**/api/branch-transfers', async route => {
          await new Promise(resolve => setTimeout(resolve, 1500))
          await route.continue().catch(() => {})
        })

        await page.goto(SURFACE, { waitUntil: 'domcontentloaded' })

        const loading = await page
          .locator('[data-transfers-loading]')
          .waitFor({ state: 'visible', timeout: 6000 })
          .then(() => true)
          .catch(() => false)

        detail.loading = {
          visible: loading,
          busy: await page
            .locator('[data-transfers-loading]')
            .getAttribute('aria-busy')
            .catch(() => null),
          label: await page
            .locator('[data-transfers-loading] [aria-label]')
            .first()
            .getAttribute('aria-label')
            .catch(() => null),
          shot: loading ? await shot(page, `shots/walk/${SLUG}/${entry.id}-loading.png`) : null
        }

        await page.unroute('**/api/branch-transfers')
        await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 15000 })
          .catch(() => {})

        await page.route('**/api/branch-transfers', route =>
          route
            .fulfill({
              status: 200,
              contentType: 'application/json',
              body: JSON.stringify({
                data: [],
                meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 }
              })
            })
            .catch(() => {})
        )

        await page.reload({ waitUntil: 'domcontentloaded' })

        const emptyPainted = await page
          .locator('[data-transfers-empty]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const emptyText = (await visibleTexts(page, '[data-transfers-empty]')).join(' | ')
        const decisionsEmpty = (await visibleTexts(page, '[data-transfers-decisions-empty]')).join(
          ' | '
        )

        detail.empty = {
          painted: emptyPainted,
          text: emptyText,
          decisionsEmpty,
          title: /No hay solicitudes de traslado/.test(emptyText),
          description: /tu alcance puede leer/.test(emptyText),
          shot: await shot(page, `shots/walk/${SLUG}/${entry.id}-empty.png`)
        }

        await page.unroute('**/api/branch-transfers')

        await page.route('**/api/branch-transfers', route =>
          route
            .fulfill({
              status: 500,
              contentType: 'application/json',
              body: JSON.stringify({ message: 'AUDIT-U9 error provocado' })
            })
            .catch(() => {})
        )

        await page.reload({ waitUntil: 'domcontentloaded' })

        const errorPainted = await page
          .locator('[data-transfers-error]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const errorText = (await visibleTexts(page, '[data-transfers-error]')).join(' | ')
        const errorMessage = (await visibleTexts(page, '[data-transfers-error-message]')).join(
          ' | '
        )
        const retry = page.locator('[data-transfers-retry]').first()
        const retryOffered = (await retry.count()) > 0

        detail.error = {
          painted: errorPainted,
          text: errorText,
          message: errorMessage,
          retryOffered,
          shot: await shot(page, `shots/walk/${SLUG}/${entry.id}-error.png`)
        }

        await page.unroute('**/api/branch-transfers')

        if (retryOffered) {
          await respondTo(page, { method: 'GET', path: '/api/branch-transfers' }, () =>
            retry.click({ timeout: 8000 })
          )
          await page.waitForTimeout(800)
          await settle(page)
        }

        const rowsAfterRetry = await page.locator(ROWS).count()

        detail.error.rowsAfterRetry = rowsAfterRetry

        entry.obtained = `carga: ${detail.loading.visible ? `muestra «${detail.loading.label ?? 'Cargando'}» (aria-busy ${detail.loading.busy ?? '—'})` : 'sin placeholder observable'} · vacío: ${detail.empty.title && detail.empty.description ? `«No hay solicitudes de traslado» con su explicación y «${detail.empty.decisionsEmpty || 'sin bloque de decisión'}»` : `sin texto de vacío («${detail.empty.text || '—'}»)`} · error 500: ${detail.error.painted ? `bloque «${detail.error.text.slice(0, 90)}» con mensaje «${detail.error.message}»` : 'sin bloque de error'} · «Reintentar» ${detail.error.retryOffered ? 'ofrecido' : 'ausente'} · tras reintentar ${rowsAfterRetry} fila(s)`
        entry.detail = detail
        entry.verdict =
          detail.loading.visible &&
          detail.loading.busy === 'true' &&
          detail.empty.title &&
          detail.empty.description &&
          detail.error.painted &&
          Boolean(detail.error.message) &&
          detail.error.retryOffered &&
          rowsAfterRetry > 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la lista no cubre carga, vacío y error con reintento, o el reintento no devuelve las solicitudes'

        return entry
      }
    )
  } finally {
    // No request of this walk stays open: the row created by the last step is
    // retired with the requester's own door before the sessions go away.
    const mine = rowsOf(await listRows(selfSession)).filter(
      row => row.status === 'pending' && row.user_id === selfSession.user.id
    )

    for (const row of mine) {
      const cancelled = await api(selfSession, 'POST', `/api/branch-transfers/${row.id}/cancel`)

      evidence.cleanup.push({
        type: 'branch-transfer',
        id: row.id,
        status: cancelled.status,
        finalStatus: cancelled.body?.data?.status ?? null
      })
    }

    if (created.sedeAccount) {
      const removed = await api(admin, 'DELETE', `/api/users/${created.sedeAccount.id}`)

      evidence.cleanup.push({
        type: 'user',
        id: created.sedeAccount.id,
        username: created.sedeAccount.username,
        status: removed.status,
        deleted: removed.status === 200,
        message: removed.body?.message ?? null
      })

      created.retired = removed.status === 200
    }

    await dismissToasts(page).catch(() => {})
    await closeDialog(page).catch(() => {})

    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.consoleClassified = classifyConsole(
      diagnostics.consoleErrors,
      'admin',
      DECLARED_CONSOLE
    )
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')

    markExpected(
      evidence.failedRequests,
      failure => failure.status === 409 && /\/api\/branch-transfers$/.test(failure.url ?? ''),
      'el paso `second-open` envía una segunda solicitud abierta: el 409 es el contrato medido'
    )
    markExpected(
      evidence.failedRequests,
      failure => failure.status === 500 && /\/api\/branch-transfers/.test(failure.url ?? ''),
      'el paso `states` provoca un 500 para medir el estado de error de la lista'
    )

    evidence.fixtures = { ...evidence.fixtures, ...fixtureSummary(fixtures) }
    evidence.created = created
    evidence.counts = walkCounts(evidence.steps)

    audit('walk-transfers', evidence)
  }

  expect(evidence.pageErrors, 'page errors during the branch transfers walkthrough').toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    'declared steps of the branch transfers walkthrough'
  ).toEqual([
    '01-list-requester',
    '02-list-admin',
    '03-destinations',
    '04-create-empty',
    '05-create',
    '06-second-open',
    '07-cancel-own',
    '08-approved',
    '09-rejected',
    '10-self-decision',
    '11-states'
  ])
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    'every step of the branch transfers walkthrough has its capture'
  ).toBe(true)
  expect(
    evidence.failedRequests.unexpected,
    'unexpected HTTP failures during the branch transfers walkthrough'
  ).toEqual([])
  expect(evidence.consoleClassified.unexpected, 'unexpected console errors').toEqual([])
  expect(
    evidence.cleanup.filter(entry => entry.type === 'user' && entry.deleted !== true),
    'the account this walk created is retired'
  ).toEqual([])
})
