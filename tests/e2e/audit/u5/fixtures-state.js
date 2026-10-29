// Plan #13 (issue #368, U5) — the shape a stored `fixtures-state.json` must have
// before the session reuses it.
//
// A state written by an earlier run, or by a run against a database that was
// recreated since, hands `ensureFixtures` a population that no longer exists:
// `publishFixtures` read `fixtures.appointments.map` and every consumer row
// failed out of `ensureFixtures` instead of skipping with a reason (#501 S3).
//
// The predicate is pure and imports nothing, so a vitest unit pins it without
// Playwright.

/** The database the module's PHP guard writes; a reused state must confirm it. */
export const AUDIT_DATABASE = 'odontosuite_audit'

const isPlainObject = value => typeof value === 'object' && value !== null && !Array.isArray(value)

/**
 * Whether a stored state belongs to this run: same session, not retired, same
 * local day, a successful build against the audit database, the appointments
 * the walks consume, and the two maps the evidence publishes.
 */
export const isReusableFixturesState = (stored, day) => {
  if (!isPlainObject(stored)) return false
  if (stored.session !== 'U5') return false
  if (stored.retired === true) return false
  if (stored.day !== day) return false

  const { fixtures } = stored
  if (!isPlainObject(fixtures)) return false
  if (fixtures.ok !== true) return false
  if (fixtures.database !== AUDIT_DATABASE) return false
  if (!Array.isArray(fixtures.appointments) || fixtures.appointments.length === 0) return false
  if (!isPlainObject(fixtures.sessions)) return false
  if (!isPlainObject(fixtures.reach)) return false

  return true
}
