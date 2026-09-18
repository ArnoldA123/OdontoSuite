#!/usr/bin/env node
/**
 * cluster-failures.mjs — axis A2 of plan #12
 * (docs/mejoras/12-programa-auditoria-integral-2026-08.md).
 *
 * The A2 distribution — "45 of 71 failures are one root class", identical on
 * MariaDB and MySQL 8.0 — was measured once by an ad-hoc classifier living
 * outside the repository (odd/tasks/audit-axis-a2-mysql-failures.md §Method).
 * A figure without a command is prose, so this script promotes that sweep to a
 * command: it reads the raw MySQL-runner output captured by
 * scripts/audit/baseline.sh (the mysql.txt under .atl/qa-evidence/audit)
 * and groups the FAILED blocks by root-cause signature.
 *
 * Method (the same four steps the document describes):
 *   1. Strip ANSI escapes.
 *   2. Split the output into FAILED blocks (^ FAILED Tests\… > <name>).
 *   3. Take the first error-like line of each block and reduce it to a
 *      signature: the SQLSTATE code plus the message up to the (Connection:
 *      tail with digit runs collapsed to #, except that Field 'X' and
 *      Duplicate-entry key names are kept verbatim — they distinguish
 *      sub-causes of one root class instead of merging them.
 *   4. Group blocks by signature, most failures first, ties in first-seen
 *      order.
 *
 * Usage:
 *   node scripts/audit/cluster-failures.mjs <raw-mysql.txt> [--json]
 *
 * The default output is a Markdown table (citable straight into the axis
 * documents); --json emits the same groups with the full affected-test lists.
 * A green run (no FAILED lines) reports 0 failures and exits 0.
 */
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const [, , inputArg, flag] = process.argv
if (!inputArg || inputArg === '-h' || inputArg === '--help') {
  console.error('Usage: node scripts/audit/cluster-failures.mjs <raw-mysql.txt> [--json]')
  process.exit(2)
}
if (flag !== undefined && flag !== '--json') {
  console.error(
    `Unknown flag ${flag}. Usage: node scripts/audit/cluster-failures.mjs <raw-mysql.txt> [--json]`
  )
  process.exit(2)
}

let raw
try {
  raw = readFileSync(resolve(inputArg), 'utf8')
} catch {
  console.error(`Cannot read ${inputArg}`)
  process.exit(1)
}

// The raw runner output carries ANSI colours; strip them first, the way the
// sed in baseline.sh does. The control character below is intentional.
// eslint-disable-next-line no-control-regex
const text = raw.replace(/\x1b\[[0-9;]*[A-Za-z]/g, '').replace(/\r/g, '')

const collapseDigits = value => value.replace(/[0-9]+/g, '#')

const truncate = (value, max) => (value.length > max ? `${value.slice(0, max)}…` : value)

const sqlSignature = line => {
  const code = line.match(/SQLSTATE\[([^\]]+)\]/)?.[1] ?? '?'
  const errno = line.match(/(?:General error|Integrity constraint violation|Warning):\s*(\d+)/)?.[1]
  const prefix = errno ? `SQLSTATE[${code}] ${errno}` : `SQLSTATE[${code}]`
  const message = line.includes('(Connection:')
    ? line.slice(0, line.indexOf('(Connection:')).trim()
    : line.trim()
  const key = message.match(/Duplicate entry .* for key '([^']+)'/)
  if (key) {
    // MySQL qualifies the index with the table (`users.users_username_unique`)
    // while MariaDB does not (`users_username_unique`): the same root class
    // measured on either engine must land in one signature.
    const index = key[1].replace(/^[^.]+\./, '')
    return `${prefix} Duplicate entry for key '${index}'`
  }
  const field = message.match(/Field '([^']+)' doesn't have a default value/)
  if (field) return `${prefix} Field '${field[1]}' doesn't have a default value`
  const column = message.match(/Data truncated for column '([^']+)'/)
  if (column) return `${prefix} Data truncated for column '${column[1]}'`
  const bare = message.replace(/SQLSTATE\[[^\]]+\]:\s*/, '')
  return truncate(`${prefix} ${collapseDigits(bare)}`, 140)
}

const firstMatch = (lines, pattern) => lines.find(line => pattern.test(line))

const signatureFor = bodyLines => {
  const sql = firstMatch(bodyLines, /SQLSTATE\[/)
  if (sql) return sqlSignature(sql.trim())
  const tooFew = firstMatch(bodyLines, /Too few arguments to function/)
  if (tooFew) {
    const fn = tooFew.match(/Too few arguments to function ([^,(]+)/)?.[1]?.trim() ?? 'unknown'
    return `Too few arguments to function ${fn}()`.replace(/\(\)\(\)$/, '()')
  }
  const undefinedMethod = firstMatch(bodyLines, /Call to undefined method/)
  if (undefinedMethod) {
    return collapseDigits(
      undefinedMethod.match(/Call to undefined method \S+/)?.[0] ?? undefinedMethod.trim()
    )
  }
  const nullCall = firstMatch(bodyLines, /Call to a member function .* on null/)
  if (nullCall)
    return nullCall.match(/Call to a member function \S+ on null/)?.[0] ?? nullCall.trim()
  const missingClass = firstMatch(bodyLines, /Class "[^"]+" not found/)
  if (missingClass) return missingClass.match(/Class "[^"]+" not found/)?.[0] ?? missingClass.trim()
  const expected = firstMatch(bodyLines, /^\s*(Expected |Failed asserting that )/)
  if (expected) return truncate(collapseDigits(expected.trim()), 120)
  const fallback = bodyLines.find(
    line =>
      line.trim() !== '' &&
      !/^\s*at\s/.test(line) &&
      !/^\s*\d+\s/.test(line) &&
      !/^[─━]+$/.test(line.trim()) &&
      !/^\s*\d+▕/.test(line) &&
      !/Stack trace:/.test(line) &&
      !/The following exception occurred/.test(line)
  )
  return fallback ? truncate(collapseDigits(fallback.trim()), 120) : '(no error line captured)'
}

const headerOf = line => {
  const trimmed = line.replace(/^FAILED\s+/, '').trimEnd()
  const kind =
    trimmed.match(/^(?<test>.*?)(?:\s{2,}(?<kind>[A-Za-z_\\]+(?:Exception|Error)))?\s*$/) ?? null
  return { test: (kind?.groups?.test ?? trimmed).trim(), kind: kind?.groups?.kind ?? '' }
}

const locationOf = (bodyLines, test) => {
  // Collision prints the throw site as `at <frame>` and the call chain as
  // numbered frames; the first frame inside tests/ is the one the triage
  // needs, whichever form it takes.
  const frame = firstMatch(bodyLines, /^\s*(at|\d+)\s+(.*[\\/])?tests[\\/]/)
  const parsed = frame?.match(/tests[\\/]([^:]+):(\d+)/)
  if (parsed) return `tests/${parsed[1].replace(/\\/g, '/')}:${parsed[2]}`
  // Vendor-only traces (QueryException via seeders) print no tests/ frame at
  // all; the class in the header still names the file, without the line.
  const klass = test.match(/^Tests\\(.*?)\s*>/)?.[1] ?? test.match(/^Tests\\(\S+)/)?.[1]
  return klass ? `tests/${klass.replace(/\\/g, '/')}.php` : ''
}

const chunks = text.split(/^\s*FAILED\s+/m)
const blocks = chunks.slice(1)

const groups = new Map()
blocks.forEach(block => {
  const lines = block.split('\n')
  const { test } = headerOf(`FAILED ${lines[0]}`)
  const body = lines.slice(1)
  const signature = signatureFor(body)
  const location = locationOf(body, test)
  if (!groups.has(signature))
    groups.set(signature, { signature, count: 0, example: test, tests: [] })
  const group = groups.get(signature)
  group.count += 1
  group.tests.push({ test, location })
})

const ranked = [...groups.values()].sort((a, b) => b.count - a.count)

if (flag === '--json') {
  console.log(
    JSON.stringify({ input: inputArg, failures: blocks.length, signatures: ranked }, null, 2)
  )
} else {
  console.log(`Failures: ${blocks.length} in ${ranked.length} signatures (${inputArg})`)
  console.log('')
  console.log('| # | Count | Signature | Example |')
  console.log('| --- | --- | --- | --- |')
  ranked.forEach((group, index) => {
    console.log(`| ${index + 1} | ${group.count} | \`${group.signature}\` | ${group.example} |`)
  })
}
