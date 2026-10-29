// Plan #13 (issue #368, U5) — the money session's own rows.
//
// The audit database is disposable, but the seeded appointments are not the
// session's: the money rows need completed appointments that no transaction has
// touched yet (the Cobros tab and the ready-to-bill list both walk away from an
// appointment as soon as it has one). The public API cannot create a completed
// appointment, so this is the one module that writes the database directly, and
// it does so through a guarded `php artisan tinker` child process:
//
//   * the first statement of the PHP script asserts the connection answers
//     `odontosuite_audit` and throws otherwise, so the development database can
//     never be written by a stray `.env`;
//   * every row is marked `AUDIT-U5` and carries branch 2, professional 5 and
//     the audit's own totals;
//   * the teardown deletes only these ids, and only while they have no
//     transaction (the ledger is immutable, ADR-0015).
//
// The `final_amount` is set to the fixture total on purpose: `readyToBill()`
// requires `final_amount > 0`, the seeded completed appointments carry 0, and
// the session has to measure the surface rather than its seed. The fact is
// declared in `fixtures.json` and reported as a measured observation.
//
// A refused fixture returns `{ ok: false, reason }` instead of throwing: a
// skipped probe with its reason is a measurement, an exception is not.
//
// Playwright re-evaluates the modules per test, so the fixtures travel through
// `globalThis` (fast path) and mirror to `fixtures-state.json` (worker-restart
// path), the same registry U3/U4 use. A stored state is adopted only when
// `fixtures-state.js` confirms its session, day, shape and database; anything
// else is rebuilt, so a stale state can never reach the walks.

import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'

import { execFileSync } from 'node:child_process'

import { test } from 'playwright/test'

import { loginAs } from '../harness.js'

import { U5_EVIDENCE_DIR, audit, call, localDay, round2, stamp } from './helpers.js'
import { isReusableFixturesState } from './fixtures-state.js'

export const MARKER = 'AUDIT-U5'

const RUN_TOKEN = String(Date.now()).slice(-6)

// The child process runs `artisan`, which reads `.env`; these values override
// it so the script can only ever talk to the audit database.
const PHP_ENV = Object.freeze({
  APP_ENV: 'local',
  DB_DATABASE: 'odontosuite_audit',
  BROADCAST_CONNECTION: 'null',
  MAIL_MAILER: 'log'
})

export const FIXTURE_TOTALS = Object.freeze([60, 80, 90])
export const FIXTURE_TYPES = Object.freeze([1, 7, 7])
export const FIXTURE_BRANCH = 2
export const FIXTURE_PROFESSIONAL = 5
export const FIXTURE_CREATOR = 1

const GUARD = `
$database = DB::connection()->getDatabaseName();
if ($database !== 'odontosuite_audit') {
    throw new RuntimeException('refusing to touch database "'.$database.'": the audit writes only odontosuite_audit');
}
`

const php = script => {
  const output = execFileSync('php', ['artisan', 'tinker', '--execute', `${GUARD}${script}`], {
    cwd: process.cwd(),
    encoding: 'utf8',
    timeout: 90000,
    env: { ...process.env, ...PHP_ENV }
  })

  // `tinker --execute` echoes whatever the script prints; keep the last JSON
  // line so a framework notice before it cannot break the parse.
  const line = output
    .split('\n')
    .map(row => row.trim())
    .filter(row => row.startsWith('{') || row.startsWith('['))
    .at(-1)

  if (!line) throw new Error(`tinker produced no JSON: ${output.slice(0, 400)}`)

  return JSON.parse(line)
}

const buildScript = () => `
$marker = '${MARKER}';
$totals = ${JSON.stringify(FIXTURE_TOTALS)};
$types = ${JSON.stringify(FIXTURE_TYPES)};
$patients = App\\Models\\Patient::query()
    ->where('is_active', true)
    ->whereNotExists(function ($q) {
        $q->from('transactions')
            ->whereColumn('transactions.patient_id', 'patients.id')
            ->where('transactions.type', 'payment')
            ->where('transactions.status', 'completed');
    })
    ->orderBy('id')
    ->limit(3)
    ->get(['id', 'first_name', 'last_name', 'document_number']);

if ($patients->count() < 3) {
    throw new RuntimeException('need three patients without a completed payment, found '.$patients->count());
}

// The appointments table carries a unique key on (user_id, scheduled_at,
// ends_at): the three fixtures share the professional, so each one takes its
// own slot. The base stays a few minutes in the past so every row is "today"
// and therefore inside the pending-payments population.
$base = now()->subMinutes(9);

$created = DB::transaction(function () use ($patients, $totals, $types, $base, $marker) {
    $rows = [];

    foreach ($patients->values() as $index => $patient) {
        $total = $totals[$index];
        $start = $base->copy()->addMinutes($index * 3);
        $appointment = App\\Models\\Appointment::create([
            'patient_id' => $patient->id,
            'user_id' => ${FIXTURE_PROFESSIONAL},
            'dental_chair_id' => 1,
            'appointment_type_id' => $types[$index],
            'scheduled_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'duration_minutes' => 30,
            'status' => 'completed',
            'notes' => $marker.' fixture '.($index + 1),
            'total_cost' => $total,
            'paid_amount' => 0,
            'balance' => $total,
            'final_amount' => $total,
            'requires_payment' => true,
            'completed_at' => now(),
            'created_by' => ${FIXTURE_CREATOR},
            'branch_id' => ${FIXTURE_BRANCH},
        ]);

        $rows[] = [
            'id' => $appointment->id,
            'scheduled_at' => $start->toDateTimeString(),
            'patient' => [
                'id' => $patient->id,
                'name' => trim($patient->first_name.' '.$patient->last_name),
                'last_name' => $patient->last_name,
                'document' => $patient->document_number,
            ],
            'type' => $types[$index],
            'total' => $total,
        ];
    }

    return $rows;
});

echo json_encode([
    'database' => $database,
    'marker' => $marker,
    'appointments' => $created,
    'counts' => [
        'appointments' => App\\Models\\Appointment::where('notes', 'like', $marker.'%')->count(),
    ],
]);
`

const retireScript = ids => `
$ids = ${JSON.stringify(ids)};
$appointments = App\\Models\\Appointment::whereIn('id', $ids)->get(['id']);
$deleted = [];
$retained = [];

foreach ($appointments as $appointment) {
    $hasTransaction = App\\Models\\Transaction::where('appointment_id', $appointment->id)->exists();

    if ($hasTransaction) {
        $retained[] = $appointment->id;
        continue;
    }

    $appointment->delete();
    $deleted[] = $appointment->id;
}

echo json_encode([
    'database' => $database,
    'deleted' => $deleted,
    'retained' => $retained,
]);
`

/** Reads one appointment through the API so the fixture's reach is verified. */
const verifyReach = async (request, admin, appointments) => {
  const [ready, pending] = await Promise.all([
    call(request, admin, 'GET', '/api/appointments/ready-to-bill?per_page=100'),
    call(request, admin, 'GET', '/api/cash-register/pending-payments?per_page=100')
  ])

  const readyRows = Array.isArray(ready.body?.data) ? ready.body.data : []
  const pendingRows = Array.isArray(pending.body?.data) ? pending.body.data : []
  const readyIds = new Set(readyRows.map(row => row.id))
  const pendingIds = new Set(pendingRows.map(row => row.id))

  return {
    readyToBill: {
      status: ready.status,
      rows: readyRows.length,
      found: appointments.filter(row => readyIds.has(row.id)).length
    },
    pendingPayments: {
      status: pending.status,
      rows: pendingRows.length,
      found: appointments.filter(row => pendingIds.has(row.id)).length
    },
    rows: readyRows
      .filter(row => appointments.some(fixture => fixture.id === row.id))
      .map(row => ({
        id: row.id,
        finalAmount: row.final_amount,
        paid: row.paid_amount,
        balance: row.balance,
        hasQuotation: row.has_quotation
      }))
  }
}

const buildFixtures = async request => {
  const built = {
    marker: MARKER,
    token: RUN_TOKEN,
    day: localDay(),
    ok: false,
    reason: 'not built',
    database: null,
    appointments: [],
    reseeded: false,
    sessions: {},
    reach: {},
    residues: [],
    teardown: [],
    leftovers: []
  }

  const admin = await loginAs(request, 'administrador')
  built.sessions.administrador = {
    id: admin.user.id,
    name: admin.user.name,
    username: admin.user.username
  }

  for (const role of ['finanzas', 'recepcionista']) {
    const session = await loginAs(request, role)
    built.sessions[role] = {
      id: session.user.id,
      name: session.user.name,
      username: session.user.username
    }
  }

  let scripted

  try {
    scripted = php(buildScript())
  } catch (thrown) {
    built.reason = `fixture build refused: ${String(thrown.message).slice(0, 400)}`
    return built
  }

  built.database = scripted.database
  built.appointments = scripted.appointments.map((row, index) => ({
    key: `a${index + 1}`,
    id: row.id,
    patient: row.patient,
    patientName: row.patient.name,
    patientLastName: row.patient.last_name ?? null,
    patientDocument: row.patient.document,
    typeId: row.type,
    scheduledAt: row.scheduled_at ?? null,
    total: round2(row.total),
    purpose: ['cash-collection', 'electronic-collection', 'refund-cap-and-quotation'][index],
    marker: `${MARKER} fixture ${index + 1}`
  }))

  for (const appointment of built.appointments) {
    const detail = await call(request, admin, 'GET', `/api/appointments/${appointment.id}`)
    appointment.apiStatus = detail.status
    appointment.api = detail.ok
      ? { status: detail.body?.data?.status }
      : { message: detail.message }
  }

  built.reach = await verifyReach(request, admin, built.appointments)

  if (built.appointments.length !== 3) {
    built.reason = `only ${built.appointments.length} appointments were created`
    return built
  }

  built.ok = true
  built.reason = null

  return built
}

const publishFixtures = fixtures =>
  audit('fixtures.json', {
    route: '/api/appointments',
    slug: 'cash-register',
    role: 'administrador',
    ...stamp(),
    ...fixtures,
    settlement: (fixtures.appointments ?? []).map(appointment => ({
      key: appointment.key,
      id: appointment.id,
      patient: appointment.patient,
      total: appointment.total,
      typeId: appointment.typeId
    }))
  })

const STATE_FILE = resolve(process.cwd(), U5_EVIDENCE_DIR, 'fixtures-state.json')

const REGISTRY = (globalThis.__AUDIT_U5_FIXTURES__ ??= {
  promise: null,
  fixtures: null,
  state: null
})

const readState = () => {
  if (REGISTRY.state) return REGISTRY.state

  try {
    if (!existsSync(STATE_FILE)) return null
    REGISTRY.state = JSON.parse(readFileSync(STATE_FILE, 'utf8'))
    return REGISTRY.state
  } catch {
    return null
  }
}

const writeState = patch => {
  const next = {
    session: 'U5',
    out: U5_EVIDENCE_DIR,
    updatedAt: new Date().toISOString(),
    ...(readState() ?? {}),
    ...patch
  }

  REGISTRY.state = next
  mkdirSync(dirname(STATE_FILE), { recursive: true })
  writeFileSync(STATE_FILE, `${JSON.stringify(next, null, 2)}\n`)
  return next
}

const reuseStoredFixtures = async (request, stored) => {
  // A state from another run, day or database is rebuilt, never adopted: its
  // appointments may no longer exist and its shape may predate `sessions`.
  if (!isReusableFixturesState(stored, localDay())) return null

  const { fixtures } = stored
  const admin = await loginAs(request, 'administrador')

  for (const appointment of fixtures.appointments) {
    const current = await call(request, admin, 'GET', `/api/appointments/${appointment.id}`)
    if (current.status !== 200) return null

    // A previous run already collected or quoted this one: the list no longer
    // offers it, so the session builds a fresh population instead.
    const pending = await call(
      request,
      admin,
      'GET',
      `/api/appointments/${appointment.id}/payment-preview`
    )
    const preview = pending.body?.data
    if (pending.status !== 200) return null
    if ((preview?.payments ?? []).length > 0 || (preview?.quotations ?? []).length > 0) return null
  }

  publishFixtures(fixtures)
  REGISTRY.fixtures = fixtures
  return fixtures
}

/** The `{ ok: false, reason }` shape every consumer row can skip with. */
const refused = thrown => ({
  marker: MARKER,
  token: RUN_TOKEN,
  day: localDay(),
  ok: false,
  reason: `fixture build failed: ${String(thrown?.message ?? thrown).slice(0, 400)}`,
  database: null,
  appointments: [],
  sessions: {},
  reach: {},
  residues: [],
  teardown: [],
  leftovers: []
})

/**
 * Builds the U5 fixtures once per worker and hands them to every row that needs
 * them. A fixture problem is a reason in the evidence, never an exception: the
 * whole promise is caught, so a stale state, a refused login or a failed write
 * lands as `{ ok: false, reason }` for every consumer row.
 */
export const ensureFixtures = request => {
  REGISTRY.promise ??= (async () => {
    const stored = readState()
    const reused = stored ? await reuseStoredFixtures(request, stored) : null
    if (reused) return reused

    const fixtures = await buildFixtures(request).catch(refused)

    writeState({
      createdAt: new Date().toISOString(),
      day: fixtures.day,
      retired: false,
      fixtures
    })
    publishFixtures(fixtures)
    REGISTRY.fixtures = fixtures
    return fixtures
  })().catch(refused)

  return REGISTRY.promise
}

export const appointmentByKey = (fixtures, key) =>
  (fixtures?.appointments ?? []).find(appointment => appointment.key === key) ?? null

/** Declares a row this session created that the ledger does not let it retire. */
export const registerResidue = residue => {
  if (!REGISTRY.fixtures) return null

  const entry = { declaredAt: new Date().toISOString(), ...residue }
  REGISTRY.fixtures.residues = [...(REGISTRY.fixtures.residues ?? []), entry]
  publishFixtures(REGISTRY.fixtures)
  writeState({ fixtures: REGISTRY.fixtures })
  return entry
}

/** The session the walkthroughs opened; declared as residue either way. */
export const registerSession = session => {
  if (!REGISTRY.fixtures) return null

  REGISTRY.fixtures.openedSessions = [...(REGISTRY.fixtures.openedSessions ?? []), session]
  publishFixtures(REGISTRY.fixtures)
  writeState({ fixtures: REGISTRY.fixtures })
  return session
}

const retire = async (request, fixtures) => {
  const steps = []
  const leftovers = [...(fixtures.residues ?? [])]

  try {
    if (fixtures.database !== 'odontosuite_audit') {
      steps.push({ action: 'refused', reason: 'fixtures did not confirm the audit database' })
    } else {
      const scripted = php(retireScript((fixtures.appointments ?? []).map(row => row.id)))
      steps.push({
        action: 'delete-appointments-without-transactions',
        database: scripted.database,
        deleted: scripted.deleted,
        retained: scripted.retained
      })

      for (const id of scripted.retained ?? []) {
        leftovers.push({
          type: 'appointment',
          id,
          reason: 'the row carries a transaction, so it is not deleted (the ledger is immutable)'
        })
      }
    }
  } catch (thrown) {
    steps.push({
      action: 'delete-appointments-without-transactions',
      error: String(thrown.message)
    })
  }

  fixtures.teardown = steps
  fixtures.leftovers = leftovers
  publishFixtures(fixtures)
  writeState({ retired: true, teardown: steps, leftovers })
  return steps
}

export const retireFixtures = request => retire(request, REGISTRY.fixtures)

// Best effort on purpose: the audit database is disposable and a row that
// cannot be retired must not turn a measurement into a failed run.
test.afterAll(async ({ request }) => {
  if (!REGISTRY.fixtures || REGISTRY.fixtures.teardownDone) return
  REGISTRY.fixtures.teardownDone = true
  await retire(request, REGISTRY.fixtures).catch(() => {})
})

/** The slice of the fixtures every evidence row carries. */
export const fixtureSummary = fixtures => ({
  marker: fixtures?.marker ?? MARKER,
  token: fixtures?.token ?? null,
  ok: fixtures?.ok === true,
  reason: fixtures?.reason ?? null,
  appointments: (fixtures?.appointments ?? []).map(appointment => ({
    key: appointment.key,
    id: appointment.id,
    patient: appointment.patient,
    total: appointment.total,
    typeId: appointment.typeId,
    purpose: appointment.purpose
  })),
  reach: fixtures?.reach ?? {},
  openedSessions: fixtures?.openedSessions ?? [],
  residues: fixtures?.residues ?? [],
  teardown: fixtures?.teardown ?? [],
  leftovers: fixtures?.leftovers ?? []
})
