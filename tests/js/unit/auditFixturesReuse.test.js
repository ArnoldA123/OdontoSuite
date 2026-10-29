import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

import { describe, expect, it } from 'vitest'

import {
  AUDIT_DATABASE,
  isReusableFixturesState
} from '../../../tests/e2e/audit/u5/fixtures-state.js'

const DAY = '2026-10-28'

// #501 S3: the U5 session adopts a stored `fixtures-state.json` only when its
// shape and its database are the ones this run wrote. Anything else returns
// null so the session rebuilds instead of handing `undefined` to the walks.
const state = patch => {
  const base = {
    session: 'U5',
    day: DAY,
    retired: false,
    fixtures: {
      ok: true,
      database: AUDIT_DATABASE,
      appointments: [
        { id: 7, key: 'a1' },
        { id: 8, key: 'a2' },
        { id: 9, key: 'a3' }
      ],
      sessions: { administrador: { id: 1 } },
      reach: { readyToBill: { status: 200, rows: 3, found: 3 } }
    }
  }

  return {
    ...base,
    ...patch,
    fixtures: { ...base.fixtures, ...(patch?.fixtures ?? {}) }
  }
}

describe('isReusableFixturesState adopts only the state this run wrote (#501 S3)', () => {
  it('accepts a successful state of the same session, day and database', () => {
    expect(isReusableFixturesState(state(), DAY)).toBe(true)
  })

  it('refuses a state without the appointments the walks consume', () => {
    expect(isReusableFixturesState(state({ fixtures: { appointments: undefined } }), DAY)).toBe(
      false
    )
    expect(isReusableFixturesState(state({ fixtures: { appointments: [] } }), DAY)).toBe(false)
    expect(isReusableFixturesState(state({ fixtures: { appointments: { a1: 7 } } }), DAY)).toBe(
      false
    )
  })

  it('refuses a state of another session, day or database', () => {
    expect(isReusableFixturesState(state({ session: 'U3' }), DAY)).toBe(false)
    expect(isReusableFixturesState(state(), '2026-10-29')).toBe(false)
    expect(isReusableFixturesState(state({ day: undefined }), DAY)).toBe(false)
    expect(isReusableFixturesState(state({ fixtures: { database: 'odontosuite' } }), DAY)).toBe(
      false
    )
    expect(isReusableFixturesState(state({ fixtures: { database: null } }), DAY)).toBe(false)
  })

  it('refuses a retired or unsuccessful state', () => {
    expect(isReusableFixturesState(state({ retired: true }), DAY)).toBe(false)
    expect(isReusableFixturesState(state({ fixtures: { ok: false } }), DAY)).toBe(false)
    expect(isReusableFixturesState(state({ fixtures: { ok: 'true' } }), DAY)).toBe(false)
  })

  it('refuses a state whose published maps are not plain objects', () => {
    expect(isReusableFixturesState(state({ fixtures: { sessions: null } }), DAY)).toBe(false)
    expect(isReusableFixturesState(state({ fixtures: { sessions: [] } }), DAY)).toBe(false)
    expect(isReusableFixturesState(state({ fixtures: { reach: 'ok' } }), DAY)).toBe(false)
  })

  it('refuses anything that is not a stored state at all', () => {
    for (const value of [null, undefined, 42, 'U5', []]) {
      expect(isReusableFixturesState(value, DAY)).toBe(false)
    }
  })

  it('keeps the predicate free of Playwright so vitest can import it', () => {
    const source = readFileSync(
      resolve(process.cwd(), 'tests/e2e/audit/u5/fixtures-state.js'),
      'utf8'
    )

    expect(source).not.toMatch(/from\s+['"]playwright/)
  })
})
