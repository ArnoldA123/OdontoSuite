// Plan #13 (issue #370, U7) — measurement helpers shared by the clinical rows.
//
// Plain readers and small drivers: no `test()` is registered here, so
// `--grep @U7` selects exactly the rows the session modules declare. Every
// reader returns what it observed; a reader that cannot observe a node returns
// `null` or an explicit `{ ok: false, reason }` so the caller records
// `NO PROBADO` instead of throwing.
//
// Evidence this session writes lives in `U7_EVIDENCE_DIR`; `.atl/` is
// gitignored and every JSON carries `generatedAt` plus the instrument revision.
// The upload probes build their PNG payloads in memory: nothing is written
// outside the session's evidence directory and the browser receives the bytes
// through `setInputFiles`.

import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import { isAbsolute, resolve } from 'node:path'
import { deflateSync } from 'node:zlib'

import { BASE_URL, capture, settleLayout, writeEvidence } from '../harness.js'

export const U7_EVIDENCE_DIR = process.env.AUDIT_OUT || '.atl/qa-evidence/audit/ui/U7'

export const RECORDS_ROUTE = '/medical-records'
export const SPECIALTIES_ROUTE = '/specialty-records'
export const AI_ROUTE = '/ai-analysis'

export const DESKTOP = { width: 1440, height: 900 }
export const MOBILE = { width: 390, height: 844 }

export const THRESHOLD = 120
export const API_TIMEOUT = 30000
export const LOAD_TIMEOUT = 25000
export const ACTION_TIMEOUT = 20000
export const TOAST_TIMEOUT = 10000
export const DIALOG_TIMEOUT = 6000

export const DIALOG_SELECTOR = '[role="dialog"]:visible, [role="alertdialog"]:visible'

export const audit = (filePath, payload) =>
  writeEvidence(filePath, payload, { dir: U7_EVIDENCE_DIR })
export const shot = (page, filePath) => capture(page, filePath, { dir: U7_EVIDENCE_DIR })

export const evidencePath = relPath =>
  isAbsolute(relPath) ? relPath : resolve(process.cwd(), U7_EVIDENCE_DIR, relPath)

export const readEvidenceFile = relPath => readFileSync(evidencePath(relPath), 'utf8')

export const sleep = ms => new Promise(resolve => setTimeout(resolve, ms))

/** Local calendar day; the API compares dates locally, not in UTC. */
export const localDay = (date = new Date()) => {
  const pad = value => String(value).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

export const addDaysLocal = (day, days) => {
  const [year, month, date] = day.split('-').map(Number)
  return localDay(new Date(year, month - 1, date + days))
}

const revision = () => {
  try {
    return execFileSync('git', ['rev-parse', 'HEAD'], {
      cwd: process.cwd(),
      encoding: 'utf8'
    }).trim()
  } catch {
    return null
  }
}

const REVISION = revision()

/** Metadata every evidence file carries. */
export const stamp = () => ({
  session: 'U7',
  generatedAt: new Date().toISOString(),
  revision: REVISION,
  base: BASE_URL
})

export const messageOf = body => {
  if (typeof body?.message === 'string') return body.message
  if (typeof body?.error?.message === 'string') return body.error.message
  if (typeof body?.error === 'string') return body.error
  if (typeof body?.meta?.message === 'string') return body.meta.message
  return null
}

/**
 * One authenticated API call.
 *
 * A network or parse failure becomes a row with `status: null`, never an
 * exception: an unreachable probe is evidence.
 */
export const call = async (request, session, method, path, data, extraHeaders = {}) => {
  const isMultipart = typeof FormData !== 'undefined' && data instanceof FormData
  const options = {
    method,
    headers: {
      Authorization: `Bearer ${session.token}`,
      Accept: 'application/json',
      ...(data !== undefined && !isMultipart ? { 'Content-Type': 'application/json' } : {}),
      ...extraHeaders
    },
    timeout: API_TIMEOUT
  }

  if (data !== undefined) options.data = data

  try {
    const response = await request.fetch(`${BASE_URL}${path}`, options)
    const contentType = response.headers()['content-type'] ?? ''
    const isJson = contentType.includes('json')
    const body = isJson ? await response.json().catch(() => null) : null
    const text = isJson ? null : await response.text().catch(() => '')

    return {
      method,
      path,
      status: response.status(),
      ok: response.ok(),
      contentType,
      message: response.ok() ? null : messageOf(body),
      errors: body?.errors ?? null,
      body,
      bytes: text === null ? null : text.length
    }
  } catch (thrown) {
    return {
      method,
      path,
      status: null,
      ok: false,
      contentType: null,
      message: String(thrown?.message ?? thrown),
      errors: null,
      body: null,
      bytes: null
    }
  }
}

/** Multipart through Playwright's own file payload; no bytes touch the disk. */
export const callUpload = async (
  request,
  session,
  path,
  { fields = {}, file = null, fileField = 'file' } = {}
) => {
  const multipart = {}

  for (const [key, value] of Object.entries(fields)) {
    multipart[key] = String(value)
  }

  if (file) {
    multipart[fileField] = {
      name: file.name,
      mimeType: file.mimeType,
      buffer: file.buffer
    }
  }

  try {
    const response = await request.fetch(`${BASE_URL}${path}`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${session.token}`, Accept: 'application/json' },
      multipart,
      timeout: API_TIMEOUT
    })
    const contentType = response.headers()['content-type'] ?? ''
    const body = contentType.includes('json') ? await response.json().catch(() => null) : null

    return {
      method: 'POST',
      path,
      status: response.status(),
      ok: response.ok(),
      contentType,
      message: response.ok() ? null : messageOf(body),
      errors: body?.errors ?? null,
      body,
      bytes: null
    }
  } catch (thrown) {
    return {
      method: 'POST',
      path,
      status: null,
      ok: false,
      contentType: null,
      message: String(thrown?.message ?? thrown),
      errors: null,
      body: null,
      bytes: null
    }
  }
}

// --- in-memory upload payloads ---------------------------------------------

const CRC_TABLE = (() => {
  const table = []
  for (let index = 0; index < 256; index += 1) {
    let value = index
    for (let bit = 0; bit < 8; bit += 1) {
      value = value & 1 ? 0xedb88320 ^ (value >>> 1) : value >>> 1
    }
    table[index] = value >>> 0
  }
  return table
})()

const crc32 = buffer => {
  let value = 0xffffffff
  for (const byte of buffer) value = CRC_TABLE[(value ^ byte) & 0xff] ^ (value >>> 8)
  return (value ^ 0xffffffff) >>> 0
}

const pngChunk = (type, data) => {
  const length = Buffer.alloc(4)
  length.writeUInt32BE(data.length)
  const typed = Buffer.concat([Buffer.from(type, 'latin1'), data])
  const crc = Buffer.alloc(4)
  crc.writeUInt32BE(crc32(typed))
  return Buffer.concat([length, typed, crc])
}

/** A real RGB PNG: `getimagesize` and the browser both accept it. */
export const pngBuffer = (width, height, rgb = [210, 180, 140]) => {
  const header = Buffer.alloc(13)
  header.writeUInt32BE(width, 0)
  header.writeUInt32BE(height, 4)
  header[8] = 8
  header[9] = 2

  const stride = 1 + width * 3
  const raw = Buffer.alloc(height * stride)
  for (let row = 0; row < height; row += 1) {
    const offset = row * stride
    raw[offset] = 0
    for (let column = 0; column < width; column += 1) {
      raw[offset + 1 + column * 3] = rgb[0]
      raw[offset + 2 + column * 3] = rgb[1]
      raw[offset + 3 + column * 3] = rgb[2]
    }
  }

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    pngChunk('IHDR', header),
    pngChunk('IDAT', deflateSync(raw)),
    pngChunk('IEND', Buffer.alloc(0))
  ])
}

export const uploadPayload = (name, buffer, mimeType = 'image/png') => ({
  name,
  mimeType,
  buffer
})

/**
 * A PNG over the 20 MB gate the AI sheet declares. 2800×2800 RGB with stored
 * deflate blocks is ~23.5 MB of bytes the browser accepts as an image.
 */
export const oversizePng = () => {
  const width = 2800
  const height = 2800
  const header = Buffer.alloc(13)
  header.writeUInt32BE(width, 0)
  header.writeUInt32BE(height, 4)
  header[8] = 8
  header[9] = 2

  const stride = 1 + width * 3
  const raw = Buffer.alloc(height * stride)
  for (let row = 0; row < height; row += 1) {
    const offset = row * stride
    raw[offset] = 0
    for (let column = 0; column < width; column += 1) {
      raw[offset + 1 + column * 3] = (row + column) % 256
      raw[offset + 2 + column * 3] = (row * 3) % 256
      raw[offset + 3 + column * 3] = (column * 7) % 256
    }
  }

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    pngChunk('IHDR', header),
    pngChunk('IDAT', deflateSync(raw, { level: 0 })),
    pngChunk('IEND', Buffer.alloc(0))
  ])
}

/** A text file the image gate must refuse. */
export const nonImageFile = () =>
  uploadPayload(
    'audit-u7-not-an-image.txt',
    Buffer.from('AUDIT-U7: this is not a clinical image.\n', 'utf8'),
    'text/plain'
  )

// --- DOM readers ------------------------------------------------------------

export const settle = async (page, selector, timeout = LOAD_TIMEOUT) => {
  await page
    .locator(selector)
    .first()
    .waitFor({ state: 'visible', timeout })
    .catch(() => {})
  await settleLayout(page)
}

/** Landing of `/medical-records`: the recent-histories index. */
export const settleRecordsLanding = async (page, timeout = LOAD_TIMEOUT) => {
  await settle(page, '[data-medical-records-hero]', timeout)
  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-recent-list]') ||
          document.querySelector('[data-state="empty-records"]') ||
          document.querySelector('[data-state="error-records"]') ||
          document.querySelector('[data-recent-list] li')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})
  await settleLayout(page)
}

/** Workspace of `/medical-records?patient_id=` and `/medical-records/:id`. */
export const settleRecordsWorkspace = async (page, timeout = LOAD_TIMEOUT) => {
  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-patient-context]') ||
          document.querySelector('[data-state="error-records"]') ||
          document.querySelector('[data-state="empty-records"]') ||
          document.querySelector('[data-medical-records-block="recent"]')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})

  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-record-list]') ||
          document.querySelector('[data-state="empty-records-panel"]') ||
          document.querySelector('[data-state="error-records-panel"]') ||
          document.querySelector('[data-state="error-records"]') ||
          document.querySelector('[data-state="empty-records"]')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})
  await settleLayout(page)
}

export const settleSpecialties = async (page, timeout = LOAD_TIMEOUT) => {
  await settle(page, '[data-specialty-records-hero]', timeout)
  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-specialty-records-panel]') ||
          document.querySelector('[data-specialty-records-no-patient]') ||
          document.querySelector('[data-specialty-records-no-specialties]')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})
  await settleLayout(page)
}

export const settleAi = async (page, timeout = LOAD_TIMEOUT) => {
  await settle(page, '[data-ai-hero]', timeout)
  await page
    .waitForFunction(
      () =>
        Boolean(
          document.querySelector('[data-ai-table]') ||
          document.querySelector('[data-ai-empty]') ||
          document.querySelector('[data-state="error"]')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})
  await settleLayout(page)
}

const READ_HELPER = `
  const text = node => (node?.innerText ?? '').trim().replace(/\\s+/g, ' ')
  const visible = node => {
    const box = node?.getBoundingClientRect()
    return Boolean(box && box.width > 0 && box.height > 0)
  }
  const attr = (node, name) => (node?.getAttribute ? node.getAttribute(name) : null)
  const has = node => Boolean(node)
  const state = selector => {
    const node = document.querySelector(selector)
    return node ? { present: true, text: text(node), visible: visible(node) } : null
  }
`

const read = (page, fn) => page.evaluate(`(() => {${READ_HELPER}; ${fn}})()`)

/** Shell facts: title ownership, the visible page title and the sidebar links. */
export const readShell = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const visible = node => {
      const box = node?.getBoundingClientRect()
      return Boolean(box && box.width > 2 && box.height > 2)
    }
    const topbar = document.querySelector('[data-app-chrome="topbar"]')

    return {
      h1: {
        count: document.querySelectorAll('h1').length,
        texts: Array.from(document.querySelectorAll('h1')).map(text),
        visibleTexts: Array.from(document.querySelectorAll('h1')).filter(visible).map(text)
      },
      topbar: {
        owner: topbar?.getAttribute('data-topbar-title-owner') ?? null,
        context: text(document.querySelector('[data-topbar-context]')) || null
      },
      hero: {
        pageTitle: text(document.querySelector('[data-medical-records-hero] .page-title')) || null,
        title: text(document.querySelector('[data-hero-title]')) || null,
        eyebrow: text(document.querySelector('[data-hero-eyebrow]')) || null,
        caption: text(document.querySelector('[data-hero-caption]')) || null
      },
      sidebar: Array.from(document.querySelectorAll('#primary-sidebar a[href]')).map(link => ({
        href: link.getAttribute('href'),
        label: text(link)
      }))
    }
  })

export const readRecordsLanding = page =>
  read(
    page,
    `
    const kpi = key => text(document.querySelector('[data-stat="' + key + '"]'))
    const rows = Array.from(document.querySelectorAll('[data-recent-list] [data-recent-row]')).map(
      node => ({
        id: Number(attr(node, 'data-recent-row')),
        patient: text(node.querySelector('p')),
        number: text(node.querySelector('[data-row-record-number]')),
        evolutions: text(node.querySelector('[data-row-evolutions]')),
        attachments: text(node.querySelector('[data-row-attachments]')),
        text: text(node)
      })
    )
    return {
      h1: Array.from(document.querySelectorAll('h1')).map(text),
      pageTitle: text(document.querySelector('[data-medical-records-hero] .page-title')),
      caption: text(document.querySelector('.page-title + p')),
      kpis: {
        records: kpi('records'),
        evolutions: kpi('evolutions'),
        attachments: kpi('attachments'),
        followUps: kpi('follow-ups')
      },
      summaryCaptionPresent: has(document.querySelector('[data-medical-records-block="summary"]')),
      newRecordDisabled: document.querySelector('[data-new-record-button]')?.disabled ?? null,
      picker: {
        openButton: has(document.querySelector('[data-open-patient-picker]')),
        selector: has(document.querySelector('[data-landing-picker] .patient-selector'))
      },
      search: has(document.querySelector('[data-recent-search] input')),
      statusFilter: has(document.querySelector('[data-recent-status]')),
      loading: has(document.querySelector('[data-medical-records-block="recent"] .skeleton-container')) ||
        Boolean(text(document.querySelector('[data-medical-records-block="recent"]')).match(/Cargando/)),
      error: state('[data-state="error-records"]'),
      errorRetry: has(document.querySelector('[data-retry-records]')),
      empty: state('[data-state="empty-records"]'),
      rows,
      pagination: has(document.querySelector('[data-medical-records-block="recent"] nav'))
    }
  `
  )

export const readWorkspace = page =>
  read(
    page,
    `
    const tabs = Array.from(document.querySelectorAll('[role="tab"]')).map(tab => ({
      id: attr(tab, 'id'),
      label: text(tab.querySelector('span')),
      selected: attr(tab, 'aria-selected') === 'true',
      visible: visible(tab)
    }))
    const surface = attr(document.querySelector('[data-surface]'), 'data-surface')
    const blank = node => Boolean(node)

    const general = {
      cards: Array.from(document.querySelectorAll('[data-record-list] > *')).map(card => ({
        text: text(card),
        actions: Array.from(card.querySelectorAll('button')).map(button => ({
          label: text(button) || attr(button, 'aria-label'),
          hook: attr(button, 'data-record-action')
        }))
      })),
      empty: state('[data-state="empty-records-panel"]'),
      error: state('[data-state="error-records-panel"]')
    }

    const evolutions = {
      empty: state('[data-state="empty-evolutions"]'),
      noRecord: state('[data-state="no-record-evolutions"]'),
      cards: Array.from(document.querySelectorAll('[data-evolution-card]')).map(card => ({
        id: Number(attr(card, 'data-evolution-id')),
        date: text(card.querySelector('[data-evolution-date]')),
        specialty: text(card.querySelector('[data-evolution-specialty]')),
        author: text(card.querySelector('[data-evolution-author]')),
        soap: Array.from(card.querySelectorAll('[data-soap-block]')).map(block => ({
          block: attr(block, 'data-soap-block'),
          text: text(block)
        })),
        measurements: Array.from(
          card.querySelectorAll('[data-evolution-measurement]')
        ).map(chip => ({
          key: attr(chip, 'data-evolution-measurement'),
          text: text(chip)
        })),
        vitals: text(card.querySelector('[data-evolution-vitals]')) || null,
        addenda: Array.from(card.querySelectorAll('[data-evolution-addenda] [data-addendum]')).map(
          addendum => ({
            id: Number(attr(addendum, 'data-addendum-id')),
            reason: text(addendum.querySelector('[data-addendum-reason]')),
            content: text(addendum.querySelector('[data-addendum-content]')),
            meta: text(addendum.querySelector('[data-addendum-meta]'))
          })
        ),
        actions: Array.from(card.querySelectorAll('button')).map(button => text(button)),
        isEmpty: card.querySelectorAll('[data-soap-block]').length === 0
      })),
      addButton: has(document.querySelector('[data-add-evolution]')),
      addEmptyButton: has(document.querySelector('[data-add-evolution-empty]'))
    }

    const attachments = {
      gallery: has(document.querySelector('[data-attachment-gallery]')),
      count: text(document.querySelector('[data-attachment-count]')),
      cards: Array.from(document.querySelectorAll('[data-attachment-id]')).map(card => ({
        id: Number(attr(card, 'data-attachment-id')),
        category: attr(card, 'data-attachment-category'),
        text: text(card),
        preview: has(card.querySelector('[data-attachment-preview-trigger]')),
        download: has(card.querySelector('[data-download-attachment]')),
        remove: has(card.querySelector('[data-delete-attachment]'))
      })),
      empty: state('[data-state="empty-attachments"]'),
      emptyFiltered: state('[data-state="empty-attachments-filter"]'),
      filter: has(document.querySelector('[data-category-filter]')),
      uploadButton: has(document.querySelector('[data-upload-attachment]')),
      emptyUploadButton: Array.from(document.querySelectorAll('[data-state="empty-attachments"] button')).map(
        text
      ),
      scope: text(document.querySelector('[data-attachments-scope]'))
    }

    const stats = {
      root: has(document.querySelector('[data-medical-record-stats]')),
      empty: state('[data-state="empty-stats"]'),
      lastEvolution: text(document.querySelector('[data-last-evolution-date]')),
      lastAssessment: text(document.querySelector('[data-last-evolution-assessment]')),
      specialties: Array.from(document.querySelectorAll('[data-specialty-chip]')).map(chip =>
        text(chip)
      ),
      bars: Array.from(document.querySelectorAll('[data-attachment-bars] [data-attachment-category]')).map(
        bar => ({ category: attr(bar, 'data-attachment-category'), text: text(bar) })
      ),
      blocks: Array.from(document.querySelectorAll('[data-stats-block]')).map(block =>
        attr(block, 'data-stats-block')
      ),
      scopeNote: text(document.querySelector('[data-stats-scope]'))
    }

    return {
      patient: {
        present: has(document.querySelector('[data-patient-context]')),
        name: text(document.querySelector('[data-patient-name]')),
        document: text(document.querySelector('[data-patient-document]')),
        recordNumbers: text(document.querySelector('[data-patient-record-numbers]')),
        activeRecord: text(document.querySelector('[data-active-record-label]')),
        selector: has(document.querySelector('[data-record-selector]')),
        clear: has(document.querySelector('[data-clear-selection]'))
      },
      fallback: {
        error: state('[data-state="error-records"]'),
        empty: state('[data-state="empty-records"]'),
        retry: has(document.querySelector('[data-retry-records]'))
      },
      tabs,
      activeTab: tabs.find(tab => tab.selected)?.id ?? surface ?? null,
      surface,
      general,
      evolutions,
      attachments,
      stats,
      modals: Array.from(document.querySelectorAll('[role="dialog"]:not([style*="display: none"])')).map(
        node => ({ heading: text(node.querySelector('h1, h2')), text: text(node).slice(0, 200) })
      ),
      editAffordances: Array.from(
        document.querySelectorAll(
          '[data-record-list] button, [data-evolution-card] button, [data-medical-record-detail] button'
        )
      )
        .map(button => text(button) || attr(button, 'aria-label'))
        .filter(Boolean),
      url: window.location.pathname + window.location.search
    }
  `
  )

export const readSpecialties = page =>
  read(
    page,
    `
    const tabs = Array.from(document.querySelectorAll('[role="tab"]')).map(tab => ({
      id: attr(tab, 'id'),
      label: text(tab.querySelector('span.truncate')),
      text: text(tab),
      selected: attr(tab, 'aria-selected') === 'true'
    }))
    const kpi = key => text(document.querySelector('[data-kpi-value="' + key + '"]'))
    return {
      title: text(document.querySelector('[data-hero-title]')),
      caption: text(document.querySelector('[data-hero-caption]')),
      patient: {
        selected: has(document.querySelector('[data-patient-name]')),
        name: text(document.querySelector('[data-patient-name]')),
        meta: text(document.querySelector('[data-patient-meta]')),
        selector: has(document.querySelector('.patient-selector')),
        change: has(document.querySelector('[data-change-patient]'))
      },
      newRecordDisabled: document.querySelector('[data-new-record]')?.disabled ?? null,
      tabs,
      activeTab: tabs.find(tab => tab.selected)?.id ?? null,
      panel: {
        loading: has(document.querySelector('[data-specialty-records-loading]')),
        error: state('[data-specialty-records-error]'),
        retry: has(document.querySelector('[data-specialty-records-retry]')),
        empty: state('[data-specialty-records-empty]'),
        emptyHasAction: Array.from(
          document.querySelectorAll('[data-specialty-records-empty] button')
        ).map(text),
        cards: Array.from(document.querySelectorAll('[data-specialty-record-card]')).map(card => ({
          specialty: attr(card, 'data-specialty'),
          title: text(card.querySelector('[data-record-title]')),
          date: text(card.querySelector('[data-record-date]')),
          status: text(card.querySelector('[class*="inline-flex"]')),
          created: text(card.querySelector('[data-record-created]')),
          summary: Array.from(card.querySelectorAll('[data-summary-field]')).map(field => ({
            key: attr(field, 'data-summary-field'),
            label: text(field.querySelector('dt')),
            value: text(field.querySelector('dd'))
          })),
          actions: Array.from(card.querySelectorAll('[data-record-action]')).map(button => ({
            action: attr(button, 'data-record-action'),
            label: text(button)
          }))
        }))
      },
      noPatient: state('[data-specialty-records-no-patient]'),
      noSpecialties: state('[data-specialty-records-no-specialties]'),
      kpis: {
        total: kpi('total'),
        inProgress: kpi('in_progress'),
        completed: kpi('completed'),
        last: kpi('last')
      },
      modal: has(document.querySelector('[data-specialty-record-form]')),
      detail: has(document.querySelector('[data-specialty-record-detail]'))
    }
  `
  )

export const readAi = page =>
  read(
    page,
    `
    const kpi = key => text(document.querySelector('[data-kpi-value="' + key + '"]'))
    const rows = Array.from(document.querySelectorAll('[data-ai-table] tbody tr')).map(row => {
      const cells = Array.from(row.querySelectorAll('td')).map(text)
      return {
        cells,
        text: text(row),
        actions: Array.from(row.querySelectorAll('[data-row-action]')).map(button => ({
          action: attr(button, 'data-row-action'),
          label: attr(button, 'aria-label'),
          visible: visible(button)
        }))
      }
    })
    return {
      title: text(document.querySelector('[data-hero-title]')),
      caption: text(document.querySelector('[data-hero-caption]')),
      notice: text(document.querySelector('[data-ai-assistive-notice]')),
      kpis: {
        total: kpi('total_analyses'),
        completed: kpi('completed_analyses'),
        pending: kpi('pending_review'),
        rate: kpi('completion_rate')
      },
      presets: Array.from(document.querySelectorAll('[data-preset]')).map(button => ({
        id: attr(button, 'data-preset'),
        label: text(button),
        pressed: attr(button, 'aria-pressed') === 'true'
      })),
      filters: {
        search: has(document.querySelector('[data-filters-search] input')),
        reviewed: has(document.querySelector('[data-ai-filters] [role="button"], [data-ai-filters] button[aria-haspopup]')),
        from: has(document.querySelector('[data-filters-from] input')),
        to: has(document.querySelector('[data-filters-to] input')),
        clear: has(document.querySelector('[data-clear-filters]')),
        clearCount: document.querySelectorAll('[data-clear-filters]').length
      },
      loading: has(document.querySelector('[data-ai-summary][aria-busy="true"]')) ||
        has(document.querySelector('[data-ai-directory] [aria-busy="true"]')),
      error: state('[data-state="error"]'),
      retry: has(document.querySelector('[data-retry]')),
      empty: state('[data-ai-empty]'),
      emptyActions: Array.from(document.querySelectorAll('[data-ai-empty] button')).map(text),
      rows,
      pagination: has(document.querySelector('[data-ai-directory] nav')),
      sheet: has(document.querySelector('[data-ai-analysis-sheet]')),
      detail: has(document.querySelector('[data-ai-detail]'))
    }
  `
  )

/** The AI sheet: steps, gate state and the inline messages it painted. */
export const readSheet = page =>
  read(
    page,
    `
    const root = document.querySelector('[data-ai-analysis-sheet]')
    if (!root) return null
    const button = document.querySelector('[data-sheet-submit]')
    return {
      steps: Array.from(root.querySelectorAll('[data-sheet-step]')).map(step =>
        attr(step, 'data-sheet-step')
      ),
      patientSelected: has(root.querySelector('.patient-selector')),
      fileInput: has(root.querySelector('input[type="file"]')),
      filePreview: has(root.querySelector('.preview-container, .preview-image')),
      fileInfo: text(root.querySelector('.file-info')),
      category: text(root.querySelector('[data-sheet-category]')),
      description: root.querySelector('[data-sheet-description]')?.value ?? null,
      noticeChecked: root.querySelector('[data-sheet-notice]')?.checked ?? null,
      submitDisabled: button?.disabled ?? null,
      errors: Array.from(root.querySelectorAll('[data-step-error], [data-sheet-error]')).map(node => ({
        hook: attr(node, 'data-step-error') || 'sheet',
        text: text(node)
      })),
      text: text(root).slice(0, 600)
    }
  `
  )

/** The AI detail modal: notice, findings, metadata, review and void state. */
export const readAiDetail = page =>
  read(
    page,
    `
    const root = document.querySelector('[data-ai-detail]')
    if (!root) return null
    const dialog = root.closest('[role="dialog"]') ?? root
    const dl = Array.from(root.querySelectorAll('[data-ai-section="metadata"] dl > div')).map(row => ({
      label: text(row.querySelector('dt')),
      value: text(row.querySelector('dd'))
    }))
    return {
      patient: text(dialog.querySelector('[data-ai-detail-patient]')),
      category: text(dialog.querySelector('[data-ai-detail-category]')),
      status: text(dialog.querySelector('[data-ai-detail-status]')),
      assistiveNotice: text(root.querySelector('[data-ai-assistive-notice]')),
      image: has(root.querySelector('[data-ai-image]')),
      imageFallback: text(root.querySelector('[data-ai-image-fallback]')),
      findings: Array.from(root.querySelectorAll('[data-ai-section="findings"] li')).map(text),
      findingsEmpty: text(root.querySelector('[data-ai-section="findings"] p')),
      recommendations: Array.from(root.querySelectorAll('[data-ai-section="recommendations"] li')).map(
        text
      ),
      metadata: dl,
      confidence: text(root.querySelector('[data-ai-confidence]')),
      review: {
        form: has(root.querySelector('[data-ai-review-form]')),
        decision: text(root.querySelector('[data-ai-review-decision]')),
        notes: root.querySelector('[data-ai-review-notes]')?.value ?? null,
        submitDisabled: document.querySelector('[data-ai-review-submit]')?.disabled ?? null,
        outcome: text(root.querySelector('[data-ai-review-outcome]')),
        decisionLabel: text(root.querySelector('[data-ai-review-decision-label]')),
        reviewer: text(root.querySelector('[data-ai-review-reviewer]')),
        denied: text(root.querySelector('[data-ai-review-denied]')),
        voided: text(root.querySelector('[data-ai-review-voided]'))
      },
      void: {
        section: has(root.querySelector('[data-ai-void-section]')),
        form: has(root.querySelector('[data-ai-void-reason]')),
        reason: root.querySelector('[data-ai-void-reason]')?.value ?? null,
        buttonDisabled: document.querySelector('[data-ai-void]')?.disabled ?? null,
        trace: text(root.querySelector('[data-ai-void-trace]')),
        reasonShown: text(root.querySelector('[data-ai-void-reason-value]')),
        by: text(root.querySelector('[data-ai-voided-by]'))
      },
      text: text(root).slice(0, 1200)
    }
  `
  )

/** Every visible dialog, top last, with its measured box. */ export const dialogStack = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    return Array.from(document.querySelectorAll('[role="dialog"], [role="alertdialog"]'))
      .filter(node => {
        const box = node.getBoundingClientRect()
        return box.width > 0 && box.height > 0
      })
      .map(node => {
        const box = node.getBoundingClientRect()
        return {
          role: node.getAttribute('role'),
          heading: text(node.querySelector('h1, h2, h3')),
          hooks: [
            'data-medical-record-form',
            'data-medical-record-detail',
            'data-evolution-form',
            'data-upload-attachment-form',
            'data-addendum-form',
            'data-specialty-record-form',
            'data-specialty-record-detail',
            'data-ai-analysis-sheet',
            'data-ai-detail',
            'data-attachment-preview'
          ].filter(hook => node.querySelector(`[${hook}]`) || node.hasAttribute(hook)),
          box: {
            x: Math.round(box.x),
            y: Math.round(box.y),
            width: Math.round(box.width),
            height: Math.round(box.height)
          },
          text: text(node).slice(0, 160)
        }
      })
  })

// --- drivers ----------------------------------------------------------------

export const closeTop = async page => {
  const dialog = page.locator(DIALOG_SELECTOR).last()

  if (!(await dialog.count().catch(() => 0))) return { ok: true, closed: false }
  if (!(await dialog.isVisible().catch(() => false))) return { ok: true, closed: false }

  await page.keyboard.press('Escape').catch(() => {})
  const closed = await dialog
    .waitFor({ state: 'hidden', timeout: DIALOG_TIMEOUT })
    .then(() => true)
    .catch(() => false)

  return { ok: closed, closed }
}

export const closeAllDialogs = async page => {
  for (let attempt = 0; attempt < 4; attempt += 1) {
    const { closed } = await closeTop(page)
    if (!closed) break
  }
}

export const dismissToasts = async page => {
  const dismissed = []

  for (let attempt = 0; attempt < 6; attempt += 1) {
    const button = page.locator('[aria-live] button[aria-label]:visible').first()

    if ((await button.count().catch(() => 0)) === 0) break

    const label = await button.getAttribute('aria-label').catch(() => null)
    await button.click({ timeout: 2000 }).catch(() => {})
    dismissed.push(label)
    await page.waitForTimeout(120)
  }

  return dismissed
}

export const toastText = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[aria-live="assertive"], [aria-live="polite"]'))
      .map(node => (node.innerText ?? '').trim())
      .filter(Boolean)
      .join(' | ')
  )

export const waitForToast = async (page, expected, timeout = TOAST_TIMEOUT) => {
  const needle = expected ?? null

  await page
    .waitForFunction(
      value =>
        Array.from(document.querySelectorAll('[aria-live="assertive"], [aria-live="polite"]')).some(
          node => (value ? node.innerText.includes(value) : node.innerText.trim().length > 0)
        ),
      needle,
      { timeout, polling: 100 }
    )
    .catch(() => {})

  return toastText(page)
}

/** Picks a patient in a `PatientSelector` by document number. */
export const pickPatient = async (page, scope, documentNumber, timeout = ACTION_TIMEOUT) => {
  const input = page.locator(`${scope} .patient-selector input`).first()
  const visible = await input
    .waitFor({ state: 'visible', timeout })
    .then(() => true)
    .catch(() => false)

  if (!visible) return { ok: false, reason: `no patient selector inside ${scope}` }

  await input.click({ timeout })
  await input.fill(String(documentNumber))

  // The selector debounces 300 ms and then answers with the server list.
  const option = page
    .locator(`${scope} .patient-selector div.cursor-pointer`)
    .filter({ hasText: String(documentNumber) })
    .first()
  const found = await option
    .waitFor({ state: 'visible', timeout })
    .then(() => true)
    .catch(() => false)

  if (!found) return { ok: false, reason: `patient ${documentNumber} not found in ${scope}` }

  await option.click({ timeout })
  await settleLayout(page)

  return { ok: true }
}

/** Opens a `UiSelect` and picks the option whose text matches. */
export const openSelect = async (page, trigger, optionText, options = {}) => {
  const locator = typeof trigger === 'string' ? page.locator(trigger).first() : trigger
  const timeout = options.timeout ?? 8000

  try {
    if (!(await locator.isVisible())) return { ok: false, reason: 'trigger not visible' }
    if (!(await locator.isEnabled())) return { ok: false, reason: 'trigger disabled' }
    await locator.scrollIntoViewIfNeeded({ timeout }).catch(() => {})
    await locator.click({ timeout })
  } catch (thrown) {
    return {
      ok: false,
      reason: `trigger not clickable: ${String(thrown?.message ?? thrown).slice(0, 200)}`
    }
  }

  const listbox = page.locator('[role="listbox"]:visible').first()
  const opened = await listbox
    .waitFor({ state: 'visible', timeout })
    .then(() => true)
    .catch(() => false)

  if (!opened) {
    await page.keyboard.press('Escape').catch(() => {})
    return { ok: false, reason: 'listbox did not open' }
  }

  const optionButtons = listbox.locator('button')
  const labels = (await optionButtons.allInnerTexts()).map(value => value.trim())

  if (options.probeOnly === true) {
    await locator.click({ timeout: 3000, force: true }).catch(() => {})
    await listbox.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})
    return { ok: true, options: labels, probeOnly: true }
  }

  const wanted = String(optionText ?? '').trim()
  const exact = labels.findIndex(label => label === wanted)
  const prefix = labels.findIndex(label => label.startsWith(wanted))
  const contains = labels.findIndex(label => label.includes(wanted))
  const index = exact !== -1 ? exact : prefix !== -1 ? prefix : contains
  const match =
    exact !== -1 ? 'exact' : prefix !== -1 ? 'prefix' : contains !== -1 ? 'contains' : null

  if (index === -1) {
    await locator.click({ timeout: 3000, force: true }).catch(() => {})
    await listbox.waitFor({ state: 'hidden', timeout: 2000 }).catch(() => {})
    return { ok: false, reason: `option "${wanted}" not found`, options: labels }
  }

  await optionButtons.nth(index).click({ timeout })
  await listbox.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})

  return { ok: true, selected: labels[index], match, options: labels }
}

export const fillControl = async (page, selector, value) => {
  const input = page.locator(selector).first()

  if (!(await input.isVisible().catch(() => false))) {
    return { ok: false, reason: `no visible control at ${selector}` }
  }

  await input.click({ timeout: 6000 }).catch(() => {})
  await input.press('ControlOrMeta+a').catch(() => {})
  await input.fill(String(value), { timeout: 8000 }).catch(() => {})
  await settleLayout(page)

  return { ok: true, value: await input.inputValue({ timeout: 4000 }).catch(() => null) }
}

export const fieldText = (page, selector) =>
  page.evaluate(sel => {
    const node = document.querySelector(sel)
    if (!node) return null

    return (node.innerText ?? '').trim().replace(/\s+/g, ' ')
  }, selector)

export const exists = (page, selector) =>
  page
    .locator(selector)
    .count()
    .then(count => count > 0)

/**
 * Clicks every tab of the surface and reads what each one paints.
 *
 * The clinical tabs render their content lazily (`v-if` inside the tab slot),
 * so a single read of the page only sees the active panel. The sweep is what
 * makes "the four tabs are measured" true instead of inferred.
 */
export const sweepTabs = async (page, readSurface) => {
  const tabs = await page.evaluate(() =>
    Array.from(document.querySelectorAll('[role="tab"]')).map(tab => ({
      id: tab.getAttribute('id'),
      label: ((tab.querySelector('span.truncate') ?? tab).innerText ?? '').trim()
    }))
  )

  const surfaces = []

  for (const [index, tab] of tabs.entries()) {
    const locator = page.locator('[role="tab"]').nth(index)
    const clicked = await locator
      .click({ timeout: 8000 })
      .then(() => true)
      .catch(() => false)

    await settleLayout(page)
    const data = clicked ? await readSurface(page) : null
    surfaces.push({ id: tab.id, label: tab.label, clicked, data })
  }

  if (tabs.length > 0) {
    await page
      .locator('[role="tab"]')
      .first()
      .click({ timeout: 5000 })
      .catch(() => {})
    await settleLayout(page)
  }

  return { tabs, surfaces }
}

// --- request instrumentation ------------------------------------------------

const pathOf = url => {
  try {
    return new URL(url).pathname
  } catch {
    return url
  }
}

/** Every request the page issues, with the status once it answers. */
export const watchRequests = page => {
  const entries = []

  page.on('request', request => {
    if (!request.url().includes('/api/')) return
    entries.push({
      method: request.method(),
      url: request.url(),
      path: pathOf(request.url()),
      data: request.postData() ?? null,
      at: Date.now(),
      status: null
    })
  })

  page.on('response', response => {
    if (!response.url().includes('/api/')) return
    const path = pathOf(response.url())
    const method = response.request().method()
    const entry = [...entries]
      .reverse()
      .find(row => row.path === path && row.method === method && row.status === null)

    if (!entry) return
    entry.status = response.status()
  })

  return entries
}

export const callsTo = (entries, method, path, predicate = () => true) =>
  entries.filter(entry => entry.method === method && entry.path === path && predicate(entry))

export const waitForCall = (
  page,
  method,
  pattern,
  predicate = () => true,
  timeout = ACTION_TIMEOUT
) => {
  const regex = pattern instanceof RegExp ? pattern : new RegExp(`${pattern}$`)

  return page
    .waitForResponse(
      response => {
        if (response.request().method() !== method) return false
        try {
          const url = new URL(response.url())
          if (!regex.test(url.pathname)) return false
          return predicate(url.searchParams, url)
        } catch {
          return false
        }
      },
      { timeout }
    )
    .catch(() => null)
}

/**
 * Moves instrument-induced failures out of `unexpected` with their reason: a
 * probe that forces a state or submits a body the API must refuse is exercising
 * a contract, not measuring a surface defect.
 */
export const markExpected = (failedRequests, predicate, reason) => {
  const moved = (failedRequests.unexpected ?? []).filter(predicate)
  failedRequests.unexpected = (failedRequests.unexpected ?? []).filter(
    failure => !predicate(failure)
  )
  failedRequests.expected = [
    ...(failedRequests.expected ?? []),
    ...moved.map(failure => ({ ...failure, reason }))
  ]
  return failedRequests
}

export const VERDICTS = Object.freeze(['OK', 'DEFECTO', 'NO-OP', 'CONFUSO', 'NO PROBADO'])

export const isNotOk = verdict => ['DEFECTO', 'NO-OP', 'CONFUSO'].includes(verdict)

/**
 * The step recorder: one entry per action, with the capture, the verdict, the
 * request status and the observed detail. A probe that throws becomes
 * `NO PROBADO` with its reason instead of ending the walkthrough.
 */
export const recorder = (page, evidence, state) => {
  let index = 0

  return async (id, action, expected, run) => {
    index += 1
    const sequence = String(index).padStart(2, '0')
    const entry = {
      step: `${sequence}-${id}`,
      id,
      action,
      expected,
      obtained: null,
      verdict: 'NO PROBADO',
      reason: null,
      requestStatus: null,
      request: null,
      shot: null,
      detail: null
    }

    state.currentStep = id
    const before = state.pageErrors.length
    let broken = false

    // A toast from the previous action sits over the corner the next control may
    // occupy; dismissing it keeps the step measurable without hiding evidence
    // (the toast of this step is still captured).
    await dismissToasts(page).catch(() => {})

    try {
      const result = (await run(entry)) ?? {}
      Object.assign(entry, result)
    } catch (thrown) {
      broken = true
      entry.verdict = 'NO PROBADO'
      entry.reason = `probe error: ${String(thrown?.message ?? thrown).slice(0, 300)}`
    }

    entry.pageErrors = state.pageErrors.slice(before).map(error => error.message)

    // A probe that died mid-form leaves its dialog over the page; closing it
    // here keeps the next step measurable instead of cascading a timeout.
    if (broken) await closeAllDialogs(page).catch(() => {})

    try {
      entry.shot = await shot(page, `${evidence.shotDir}/${entry.step}.png`)
    } catch {
      entry.shot = null
    }

    evidence.steps.push(entry)

    if (isNotOk(entry.verdict)) {
      evidence.findings.push({
        step: entry.step,
        action: entry.action,
        expected: entry.expected,
        obtained: entry.obtained,
        verdict: entry.verdict,
        requestStatus: entry.requestStatus,
        detail: entry.detail ?? entry.reason ?? null,
        shot: entry.shot
      })
    }

    return entry
  }
}

/** The last non-GET call the page issued, as the step's request evidence. */
export const lastWrite = entries => {
  const writes = entries.filter(entry => entry.method !== 'GET')
  const last = writes.at(-1)

  return last
    ? { method: last.method, path: last.path, status: last.status, data: last.data }
    : null
}

/** The aggregate every walk JSON carries. */
export const walkTotals = evidence => ({
  steps: evidence.steps.length,
  ok: evidence.steps.filter(entry => entry.verdict === 'OK').length,
  defect: evidence.steps.filter(entry => entry.verdict === 'DEFECTO').length,
  noop: evidence.steps.filter(entry => entry.verdict === 'NO-OP').length,
  confusing: evidence.steps.filter(entry => entry.verdict === 'CONFUSO').length,
  notProbed: evidence.steps.filter(entry => entry.verdict === 'NO PROBADO').length,
  findings: evidence.findings.length
})
