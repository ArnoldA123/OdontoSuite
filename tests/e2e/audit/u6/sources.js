// Plan #13 (issue #369, U6) — the SPA source scan.
//
// A surface the issue names but the SPA never renders is proven by the absence of
// the field in the shipped code: the walk cites the file and the line, not memory.

import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, resolve } from 'node:path'

// --- source scans -----------------------------------------------------------

const walkFiles = (path, collected = []) => {
  const stat = statSync(path)

  if (stat.isFile()) {
    if (/\.(js|vue)$/.test(path)) collected.push(path)
    return collected
  }

  for (const entry of readdirSync(path, { withFileTypes: true })) {
    const full = join(path, entry.name)

    if (entry.isDirectory()) walkFiles(full, collected)
    else if (/\.(js|vue)$/.test(entry.name)) collected.push(full)
  }

  return collected
}

/**
 * Reads the shipped SPA sources and reports every match of `pattern`, with the
 * file and line. The session cites code, never memory: a surface the issue names
 * but the SPA never renders is proven by the absence of the field in the code.
 */
export const scanSources = (pattern, dirs) => {
  const regex = new RegExp(pattern, 'i')
  const result = { pattern, files: [], total: 0 }

  for (const dir of dirs) {
    const absolute = resolve(process.cwd(), dir)

    let files = []
    try {
      files = walkFiles(absolute)
    } catch (thrown) {
      result.error = String(thrown?.message ?? thrown)
      continue
    }

    for (const file of files) {
      const lines = readFileSync(file, 'utf8').split('\n')
      const hits = []

      lines.forEach((line, index) => {
        if (regex.test(line)) hits.push({ line: index + 1, text: line.trim().slice(0, 200) })
      })

      if (hits.length) {
        const rel = file.slice(process.cwd().length + 1)
        result.files.push({ file: rel, count: hits.length, hits })
        result.total += hits.length
      }
    }
  }

  return result
}
