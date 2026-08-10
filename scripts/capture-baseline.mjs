/**
 * Visual baseline capture — uses headless Chrome to screenshot a URL.
 *
 * Used by the PR2 regression gate (task 2.4.1) to commit pre-PR2 PNG
 * baselines to tests/visual/baselines/. The directory is gitignored
 * (these are local comparison artifacts, not shipped code).
 *
 * Usage: node scripts/capture-baseline.mjs <url> <out-png> [auth-cookie]
 */
import { spawn } from 'node:child_process'
import { writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const projectRoot = resolve(__dirname, '..')

const [, , url, outPath, authCookieJson] = process.argv
if (!url || !outPath) {
  console.error('Usage: node scripts/capture-baseline.mjs <url> <out-png> [auth-cookie-json]')
  process.exit(1)
}

const chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
const userDataDir = resolve(projectRoot, '.tmp_chrome_profile')
const args = [
  '--headless=new',
  '--no-sandbox',
  '--disable-gpu',
  '--disable-dev-shm-usage',
  '--hide-scrollbars',
  '--window-size=800,600',
  '--virtual-time-budget=4000',
  `--screenshot=${outPath}`,
  `--user-data-dir=${userDataDir}`,
  url
]

// Optionally inject auth cookie via a small HTML probe page. Not needed
// for the public /login page; for /dashboard we would need a real session
// cookie from the API. PR2 baseline capture only needs /login, /404, and
// the post-login /dashboard via XHR-driven session — kept simple here.
if (authCookieJson) {
  const cookieFile = resolve(projectRoot, '.tmp_cookies.json')
  writeFileSync(cookieFile, authCookieJson)
  args.push(`--load-cookies-file=${cookieFile}`)
}

const child = spawn(chrome, args, { stdio: 'inherit' })
child.on('exit', (code) => {
  if (code === 0) {
    console.log(`Captured ${url} -> ${outPath}`)
  } else {
    console.error(`Chrome exited with code ${code}`)
  }
  process.exit(code)
})
