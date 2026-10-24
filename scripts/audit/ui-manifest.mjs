#!/usr/bin/env node
// Plan #13 (issue #363, U0) — layer 1: static manifest of the screen surfaces.
//
// Every later audit session reads this manifest instead of inventing a checklist,
// so each row is derived from a source of truth already in the tree:
//
//   routes      resources/js/app.js                 (`const routes`)
//   roles       resources/js/components/layout/AppLayout.vue (`allItems`)
//   widgets     the page plus its imported sources (import graph, depth ≤ 4)
//   permission  routes/api.php + app/Authorization/Permissions.php
//
// Static only: no server, no database, no browser.

import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

import { buildManifest } from './manifest/build.mjs'
import { ROOT } from './manifest/text.mjs'

export { buildManifest, summarize } from './manifest/build.mjs'

// --- CLI --------------------------------------------------------------------

const USAGE = `Usage: node scripts/audit/ui-manifest.mjs [options]

  --all                every screen surface (default)
  --module=<name>      keep surfaces whose page or route belongs to the module
  --route=<path>       keep a single router path (e.g. --route=/patients)
  --out=<file>         write the manifest JSON to <file>
  --json               print the manifest JSON
  --list               print one line per surface
  --help               show this help`

const parseArgv = argv => {
  const options = { all: true, json: false, list: false, help: false, out: null }

  for (let i = 0; i < argv.length; i += 1) {
    const arg = argv[i]

    if (arg === '--help' || arg === '-h') options.help = true
    else if (arg === '--all') options.all = true
    else if (arg === '--json') options.json = true
    else if (arg === '--list') options.list = true
    else if (arg.startsWith('--module=') || arg === '--module') {
      options.module = arg.includes('=') ? arg.slice('--module='.length) : argv[(i += 1)]
    } else if (arg.startsWith('--route=') || arg === '--route') {
      options.route = arg.includes('=') ? arg.slice('--route='.length) : argv[(i += 1)]
    } else if (arg.startsWith('--out=') || arg === '--out') {
      options.out = arg.includes('=') ? arg.slice('--out='.length) : argv[(i += 1)]
    } else {
      return { error: `unknown argument: ${arg}` }
    }

    if ((arg === '--module' || arg === '--route' || arg === '--out') && argv[i] == null) {
      return { error: `missing value for ${arg}` }
    }
  }

  if (options.module && options.route) return { error: 'use either --module or --route' }

  return { options }
}

const printSummary = manifest => {
  const { summary } = manifest
  const kinds = Object.entries(summary.byKind)
    .sort((a, b) => b[1] - a[1])
    .map(([kind, count]) => `${kind}: ${count}`)
    .join(', ')

  console.log(`surfaces: ${summary.surfaces}`)
  console.log(`excluded: ${manifest.excluded.length}`)
  console.log(`actions: ${summary.actions} (${kinds || 'none'})`)
  console.log(`unclassified actions: ${summary.unclassifiedActions}`)
  console.log(`dialogs: ${summary.dialogs}`)
  console.log(`endpoints: ${summary.endpoints}`)
  console.log(`unresolved permissions: ${summary.unresolvedPermissions}`)

  console.log('')
  console.log('route | roles | actions | dialogs | permission')
  for (const surface of manifest.surfaces) {
    console.log(
      `${surface.route} | ${surface.roles.join(',')} | ${surface.actions.length} | ` +
        `${surface.dialogs.length} | ${surface.permission ?? '—'}`
    )
  }
}

const main = () => {
  const parsed = parseArgv(process.argv.slice(2))

  if (parsed.error) {
    console.error(parsed.error)
    console.error('')
    console.error(USAGE)
    process.exitCode = 2
    return
  }

  const { options } = parsed

  if (options.help) {
    console.log(USAGE)
    return
  }

  const manifest = buildManifest({ module: options.module, route: options.route })

  if (options.out) {
    const outPath = resolve(ROOT, options.out)
    mkdirSync(dirname(outPath), { recursive: true })
    writeFileSync(outPath, `${JSON.stringify(manifest, null, 2)}\n`)
  }

  if (options.json) console.log(JSON.stringify(manifest, null, 2))
  else if (options.list) {
    for (const surface of manifest.surfaces) {
      console.log(
        `${surface.route} | ${surface.page} | ${surface.roles.join(',')} | ` +
          `${surface.permission ?? '—'}`
      )
    }
  } else printSummary(manifest)

  if (manifest.summary.unclassifiedActions > 0) process.exitCode = 1
  else if (manifest.surfaces.length === 0) process.exitCode = 1
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) main()
