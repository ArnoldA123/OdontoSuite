// Plan #13 (issue #373, U10) — layer 4: the prosthetic laboratory, order by order.
//
// The desk lists the work orders of the scope behind three filters and opens each
// one in a modal that owns the only write the surface has: the phase door. The
// walk measures what the issue's matrix asks of it:
//
//   1. LIST    the four work orders of the session (`fixtures.lab.cases`), the row
//              hooks, the overdue flag of the case promised ten days ago, the
//              pagination that hides rows behind residue and where each of the
//              four rows actually lives. It never asserts a queue total: the
//              database keeps the cases of every earlier run.
//   2. DETAIL  the modal of case A: title, the nine facts, the phase door.
//   3. FILTERS phase, `technician_id=me`, `overdue=1` and the clear, each with the
//              query the page re-issued and the rows the list kept.
//   4. PHASE   A advanced to `try_in`: 200, the offered set shrank and the phase
//              it just left is no longer offered.
//   5. BACK    the backward move forced through the API: 422, `errors.phase[0]`
//              naming current and asked phase, and a `GET` proving it did not move.
//   6. PLACED  case D, already `placed`: the empty state «El caso ya está colocado.»
//              and no phase button.
//   7. WRITE   the edit of a case that belongs to the other technician, measured
//              twice: the surface offers no alta/edición at all, and the API door
//              answers with the real tokens.
//   8. READ    reception's detail: the read-only sentence, no phase button and a
//              403 on the phase door.
//   9. DIALOG  `assertDialogContract` over the detail modal, every check recorded.
//
// `DEFECTO` / `NO-OP` / `CONFUSO` / `NO PROBADO` are evidence, never a thrown
// failure. The hard assertions are that no `pageerror` was raised, that every
// declared step was recorded and that each one left its capture.

import { expect, test } from 'playwright/test'

import {
  assertDialogContract,
  classifyFailures,
  collectDiagnostics,
  primeSession
} from '../harness.js'

import { ensureFixtures } from './fixtures.js'

import {
  DESKTOP,
  SESSION,
  api,
  audit,
  classifyConsole,
  closeDialog,
  dismissToasts,
  gotoSurface,
  lastCall,
  login,
  markExpected,
  pickOption,
  readSelectOptions,
  respondTo,
  settle,
  shot,
  toastText,
  walkCounts,
  walkStep,
  watchRequests
} from './helpers.js'

const SURFACE = '/lab-cases'
const SLUG = 'lab'
const ROLE = 'tecnico_dental'
const ADMIN_ROLE = 'administrador'
const READ_ONLY_ROLE = 'recepcionista'

const DIALOG = '[role="dialog"]:visible'
const LAB = '/api/lab-cases'

// The labels `labCaseMeta.js` prints for the five phases of the machine.
const PHASE_LABELS = {
  in_preparation: 'En preparación',
  in_laboratory: 'En laboratorio',
  try_in: 'Prueba en boca',
  delivered: 'Recibido del taller',
  placed: 'Colocado'
}

// The two labels the issue lists as the surface's actions and the walk does not
// find: measured as zero, never invented as a design verdict.
const WRITE_LABELS = /nuevo|nueva|crear|editar/i

/** Rows of a payload whose list may be `data` or `data.data`. */
const rowsOf = body => {
  const payload = body?.data

  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data

  return []
}

/** The list surface: hero, filters, rows of the mounted page and the pagination. */
const labPanel = page =>
  page.evaluate(() => {
    const text = value => (value ?? '').trim().replace(/\s+/g, ' ')
    const trigger = selector => text(document.querySelector(selector)?.innerText) || null
    const rows = Array.from(document.querySelectorAll('[data-lab-case-row]')).map(node => {
      const row = node.closest('tr')
      const work = row?.querySelector('[data-lab-case-work]')
      const status = row?.querySelector('[data-lab-case-status]')
      const cells = Array.from(row?.querySelectorAll('td') ?? []).map(cell => text(cell.innerText))

      return {
        id: Number(node.getAttribute('data-lab-case-row')),
        patient: text(node.innerText),
        work: text(work?.innerText) || null,
        workTitle: work?.getAttribute('title') ?? null,
        status: text(status?.innerText) || null,
        variant: status?.getAttribute('data-variant') ?? null,
        overdue: Boolean(row?.querySelector('[data-lab-case-overdue]')),
        technician: cells[4] ?? null,
        promised: cells[3] ?? null,
        open: Boolean(row?.querySelector('[data-lab-case-open]')),
        cells
      }
    })

    const empty = document.querySelector('[data-lab-cases-empty]')
    const error = document.querySelector('[data-lab-cases-error]')
    const pagination = document.querySelector('.pagination')
    const active = document.querySelector('main button[aria-current="page"]')
    const info = /Mostrando\s+([\d.]+)\s+a\s+([\d.]+)\s+de\s+([\d.]+)/.exec(
      text(document.querySelector('main')?.innerText)
    )

    return {
      hero: {
        title: text(document.querySelector('[data-lab-cases-title]')?.innerText) || null,
        refresh: Boolean(document.querySelector('[data-lab-cases-refresh]'))
      },
      filters: {
        status: trigger('[data-lab-cases-status] button'),
        technician: trigger('[data-lab-cases-technician] button'),
        overdue: document.querySelector('[data-lab-cases-overdue]')?.checked ?? null,
        clearDisabled: document.querySelector('[data-lab-cases-clear]')?.disabled ?? null,
        bar: Boolean(document.querySelector('[data-lab-cases-filters]'))
      },
      table: Boolean(document.querySelector('[data-lab-cases-table]')),
      loading: Boolean(document.querySelector('[data-lab-cases-loading]')),
      empty: empty ? text(empty.innerText) : null,
      error: error ? text(error.innerText) : null,
      rows,
      pagination: {
        mounted: Boolean(pagination),
        current: active ? Number(text(active.textContent)) : 1,
        pages: Array.from(document.querySelectorAll('.pagination-page-button'))
          .map(node => Number(text(node.textContent)))
          .filter(Boolean),
        showing: info ? `${info[1]}-${info[2]}` : null,
        total: info ? Number(info[3]) : null
      }
    }
  })

const rowFor = (page, id) =>
  page.locator(`tr:has([data-lab-case-row="${id}"]) [data-lab-case-open]`)

const currentPage = page =>
  page.evaluate(() => {
    const active = document.querySelector('main button[aria-current="page"]')

    return active ? Number((active.textContent ?? '').trim()) : 1
  })

/** Walks to a numbered page through the primitive's own control. */
const gotoPage = async (page, target, timeout = 12000) => {
  if ((await currentPage(page)) === target) return { ok: true, moved: false }

  const button = page.locator(`.pagination button[aria-label="Ir a la página ${target}"]`).first()

  if (!(await button.count().catch(() => 0))) {
    return { ok: false, moved: false, reason: `no pagination control for page ${target}` }
  }

  const sent = await respondTo(
    page,
    { method: 'GET', path: LAB },
    () => button.click({ timeout: 8000 }),
    12000
  ).catch(() => ({ status: null }))

  await settle(page)

  const reached = (await currentPage(page)) === target

  return { ok: reached, moved: true, status: sent.status, timeout }
}

/**
 * Puts the row `id` on screen, walking the pagination when residue keeps it off
 * the mounted page. The scan is bounded so a row that is not reachable is a
 * recorded fact instead of an endless walk.
 */
const ensureVisible = async (page, id, maxPages = 8) => {
  let navigations = 0

  if (await page.locator(`[data-lab-case-row="${id}"]`).count()) {
    return { ok: true, page: await currentPage(page), navigations }
  }

  if ((await currentPage(page)) !== 1) {
    const first = await gotoPage(page, 1)
    navigations += 1
    if (!first.ok)
      return { ok: false, page: await currentPage(page), navigations, reason: first.reason }
  }

  for (let guard = 0; guard < maxPages; guard += 1) {
    if (await page.locator(`[data-lab-case-row="${id}"]`).count()) {
      return { ok: true, page: await currentPage(page), navigations }
    }

    const next = (await currentPage(page)) + 1
    const moved = await gotoPage(page, next)

    navigations += 1

    if (!moved.ok)
      return { ok: false, page: await currentPage(page), navigations, reason: moved.reason }
  }

  return {
    ok: false,
    page: await currentPage(page),
    navigations,
    reason: `la fila ${id} no aparece en ${maxPages} páginas`
  }
}

/** Every page of the list: the rows and the pagination the surface publishes. */
const scanPages = async (page, maxPages = 8) => {
  const pages = []
  const first = await gotoPage(page, 1)

  if (!first.ok) return { pages, reason: first.reason ?? 'la primera página no se pudo alcanzar' }

  for (let guard = 0; guard < maxPages; guard += 1) {
    const panel = await labPanel(page)

    pages.push({
      page: panel.pagination.current,
      rows: panel.rows.map(row => ({
        id: row.id,
        work: row.work,
        status: row.status,
        overdue: row.overdue,
        technician: row.technician
      })),
      empty: panel.empty,
      total: panel.pagination.total,
      showing: panel.pagination.showing
    })

    const next = panel.pagination.current + 1
    const moved = await gotoPage(page, next)

    if (!moved.ok) break
  }

  return { pages, reason: null }
}

/** The open detail modal: what it names, what it publishes and what it offers. */
const detail = page =>
  page.evaluate(() => {
    const text = value => (value ?? '').trim().replace(/\s+/g, ' ')
    const visible = node => {
      const box = node.getBoundingClientRect()
      return box.width > 0 && box.height > 0
    }
    const dialog = Array.from(document.querySelectorAll('[role="dialog"]')).filter(visible).at(-1)

    if (!dialog) return null

    const phaseEmpty = dialog.querySelector('[data-section-empty]')

    return {
      backdrop: Boolean(document.querySelector('[data-lab-case-detail]')),
      title: text(dialog.querySelector('[data-lab-case-detail-title]')?.innerText) || null,
      patient: text(dialog.querySelector('[data-lab-case-detail-patient]')?.innerText) || null,
      phase: text(dialog.querySelector('[data-lab-case-detail-phase]')?.innerText) || null,
      overdue: text(dialog.querySelector('[data-lab-case-detail-overdue]')?.innerText) || null,
      factsBox: Boolean(dialog.querySelector('[data-lab-case-facts]')),
      facts: Array.from(dialog.querySelectorAll('[data-lab-case-fact]')).map(node => ({
        key: node.getAttribute('data-lab-case-fact'),
        value: text(node.innerText) || null
      })),
      notes: text(dialog.querySelector('[data-lab-case-notes]')?.innerText) || null,
      phaseDoor: Boolean(dialog.querySelector('[data-lab-case-phase-door]')),
      phases: Array.from(dialog.querySelectorAll('[data-lab-case-phase]')).map(node =>
        node.getAttribute('data-lab-case-phase')
      ),
      readOnly: text(dialog.querySelector('[data-lab-case-read-only]')?.innerText) || null,
      phaseEmpty: phaseEmpty ? text(phaseEmpty.innerText) : null,
      phaseError: text(dialog.querySelector('[data-lab-case-phase-error]')?.innerText) || null,
      close: Boolean(dialog.querySelector('[data-lab-case-close]'))
    }
  })

/** The write-shaped controls of the hero and of the rows, by their own text. */
const writeControls = (page, pattern) =>
  page.evaluate(source => {
    const regex = new RegExp(source, 'i')
    const text = value => (value ?? '').trim().replace(/\s+/g, ' ')
    const visible = node => {
      const box = node.getBoundingClientRect()
      return box.width > 0 && box.height > 0
    }

    const scope = [
      ...Array.from(document.querySelectorAll('[data-lab-cases-hero] *')),
      ...Array.from(document.querySelectorAll('[data-lab-cases-table] tbody *'))
    ]

    return scope
      .filter(
        node => ['BUTTON', 'A'].includes(node.tagName) || node.getAttribute('role') === 'button'
      )
      .filter(visible)
      .map(node => text(node.innerText) || node.getAttribute('aria-label') || '')
      .filter(label => label && regex.test(label))
  }, pattern.source)

/** The offered phase set of a case as the door renders it, sorted for comparison. */
const sorted = values => [...(values ?? [])].sort()

/** Opens a case's detail from its row and returns what the modal publishes. */
const openCase = async (page, id) => {
  const visible = await ensureVisible(page, id)

  if (!visible.ok) return { opened: false, reason: visible.reason, visible }

  const sent = await respondTo(
    page,
    { method: 'GET', path: new RegExp(`^/api/lab-cases/${id}$`) },
    () => rowFor(page, id).click({ timeout: 8000 }),
    15000
  ).catch(() => ({ status: null }))

  const dialog = page.locator(DIALOG).last()
  const opened = await dialog
    .waitFor({ state: 'visible', timeout: 12000 })
    .then(() => true)
    .catch(() => false)

  return {
    opened,
    visible,
    status: sent.status ?? null,
    detail: opened ? await detail(page) : null
  }
}

/**
 * The walk itself.
 *
 * Exported so the session can run it outside the runner — the diagnostic that
 * validates the hooks while `surface.spec.js` still does not import this module
 * — and so a future row can compose it without re-reading the file.
 */
export const walkThroughLabCases = async ({ page, fixtures }) => {
  const technician = await login(ROLE)
  const admin = await login(ADMIN_ROLE)
  const reception = await login(READ_ONLY_ROLE)
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)

  const { token } = fixtures
  const cases = fixtures.lab.cases ?? {}
  const ids = {
    A: cases.A?.id ?? null,
    B: cases.B?.id ?? null,
    C: cases.C?.id ?? null,
    D: cases.D?.id ?? null
  }

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks laboratorio',
    role: ROLE,
    roles: [ROLE, ADMIN_ROLE, READ_ONLY_ROLE],
    steps: [],
    counts: null,
    fixtures: {
      marker: fixtures.marker,
      token,
      day: fixtures.day,
      ok: fixtures.ok,
      cases: Object.fromEntries(
        Object.entries(cases).map(([key, entry]) => [
          key,
          {
            id: entry.id,
            work: entry.work,
            phase: entry.phase,
            technicianId: entry.technicianId,
            technician: entry.technician,
            promisedAt: entry.promisedAt
          }
        ])
      ),
      actors: fixtures.actors,
      labIndex: fixtures.lab.index
    },
    pages: null,
    pageOf: {},
    captures: [],
    created: { advancedTo: null, fetchedAfterRefusal: null },
    pageErrors: [],
    consoleErrors: [],
    consoleClassified: null,
    failedRequests: null,
    traffic: null
  }

  const step = walkStep(page, evidence)

  // The phase door is probed with three real tokens, and the two refusals are the
  // measurement: they travel as declared failures, never as surface errors.
  const declaredFailure = {
    predicate: failure =>
      [403, 422].includes(failure.status) && /\/api\/lab-cases\/\d+\/phase/.test(failure.url ?? ''),
    reason:
      'el paso `backward` fuerza el retroceso de fase (422) y el paso `read-only` prueba la puerta con recepción (403)'
  }

  page.setDefaultTimeout(8000)

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, technician)

    const opened = await gotoSurface(page, SURFACE)

    await page
      .locator('[data-lab-cases-table], [data-lab-cases-empty], [data-lab-cases-loading]')
      .first()
      .waitFor({ state: 'visible', timeout: 15000 })
      .catch(() => {})
    await settle(page)

    await step(
      'list',
      'Abrir /lab-cases como técnico dental con los cuatro casos de la sesión',
      'La tabla pinta una fila por caso con su trabajo, su fase y el aviso «Vencido» del caso prometido hace diez días, y los cuatro casos de la sesión son alcanzables (la base conserva los casos de las corridas anteriores)',
      async entry => {
        const first = await labPanel(page)
        const scan = await scanPages(page)
        const byId = new Map()

        for (const pageScan of scan.pages) {
          for (const row of pageScan.rows) byId.set(row.id, { ...row, page: pageScan.page })
        }

        evidence.pages = scan.pages.map(pageScan => ({
          page: pageScan.page,
          total: pageScan.total,
          showing: pageScan.showing,
          rows: pageScan.rows.length,
          works: pageScan.rows.map(row => row.work)
        }))

        for (const [key, id] of Object.entries(ids)) {
          const found = id !== null ? (byId.get(id) ?? null) : null
          evidence.pageOf[key] = found ? { id, page: found.page, work: found.work } : null
        }

        const ours = Object.fromEntries(
          Object.entries(evidence.pageOf).map(([key, found]) => [
            key,
            found ? found.page : 'ausente'
          ])
        )
        const missing = Object.entries(evidence.pageOf)
          .filter(([, found]) => !found)
          .map(([key]) => key)
        const caseB = ids.B !== null ? (byId.get(ids.B) ?? null) : null
        const tagged = scan.pages.reduce(
          (total, pageScan) =>
            total + pageScan.rows.filter(row => String(row.work ?? '').includes(token)).length,
          0
        )
        const foreign = scan.pages.reduce((total, pageScan) => total + pageScan.rows.length, 0)

        evidence.captures.push(await shot(page, 'shots/walk/lab-list.png'))

        entry.obtained = `HTTP ${opened.status} · tabla ${first.table} · «${
          first.hero.title ?? '—'
        }» · ${foreign} filas en ${scan.pages.length} página(s) (${tagged} con el token de la sesión) · paginación ${
          first.pagination.mounted
        } total «${first.pagination.total ?? '—'}» páginas ${JSON.stringify(
          first.pagination.pages
        )} · casos de la sesión ${JSON.stringify(ours)} · caso B vencido ${caseB?.overdue ?? '—'} (${
          caseB?.status ?? '—'
        }) · ${missing.length ? `ausentes ${JSON.stringify(missing)}` : 'los cuatro alcanzables'}`
        entry.request = lastCall(requests, 'GET', LAB)
          ? {
              method: 'GET',
              path: LAB,
              status: lastCall(requests, 'GET', LAB).status,
              query: lastCall(requests, 'GET', LAB).query
            }
          : null
        entry.detail = {
          opened,
          first: { hero: first.hero, filters: first.filters, pagination: first.pagination },
          scan: evidence.pages,
          ours,
          missing,
          caseB,
          tagged,
          foreign
        }
        entry.verdict =
          first.table &&
          missing.length === 0 &&
          caseB?.overdue === true &&
          caseB?.status === 'En laboratorio'
            ? 'OK'
            : first.table && missing.length === 0
              ? 'DEFECTO'
              : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : `la lista no publica los cuatro casos de la sesión (${JSON.stringify(ours)}) o el caso vencido no lleva su aviso (${JSON.stringify(
                caseB
              )})`

        return entry
      }
    )

    await step(
      'detail',
      'Abrir el caso A desde la acción de su fila',
      'El modal publica el trabajo, el paciente, la fase, los hechos del caso y la puerta de fase con las fases posteriores',
      async entry => {
        const target = evidence.pageOf.A?.id ?? null

        if (!target) {
          entry.obtained = 'el caso A de la sesión no está en la lista'
          entry.verdict = 'NO PROBADO'
          entry.notes = 'sin fila no hay modal de detalle que medir'

          return entry
        }

        const openedCase = await openCase(page, target)

        if (!openedCase.opened) {
          entry.obtained = `el modal de detalle no se abrió (${openedCase.reason ?? 'sin diálogo'})`
          entry.verdict = 'DEFECTO'
          entry.notes = 'la acción «Ver» de la fila no pinta el detalle'

          return entry
        }

        const facts = openedCase.detail?.facts ?? []
        const phases = openedCase.detail?.phases ?? []

        evidence.captures.push(await shot(page, 'shots/walk/lab-detail.png'))

        entry.obtained = `GET ${openedCase.status} · «${openedCase.detail.title}» · paciente «${
          openedCase.detail.patient
        }» · fase «${openedCase.detail.phase}» · hechos ${facts.length} ${JSON.stringify(
          facts.map(fact => fact.key)
        )} · puerta ${openedCase.detail.phaseDoor} · fases ofrecidas ${JSON.stringify(phases)} · solo lectura ${
          openedCase.detail.readOnly ?? 'no'
        }`
        entry.request =
          openedCase.status !== null
            ? { method: 'GET', path: `/api/lab-cases/${target}`, status: openedCase.status }
            : null
        entry.detail = { openedCase, facts, phases }
        entry.verdict =
          String(openedCase.detail.title ?? '').trim() === String(cases.A?.work ?? '').trim() &&
          openedCase.detail.factsBox &&
          facts.length >= 8 &&
          openedCase.detail.phaseDoor &&
          phases.length > 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el detalle no publica el trabajo, los hechos del caso o la puerta de fase'

        await closeDialog(page)

        return entry
      }
    )

    await step(
      'filters',
      'Filtrar la lista por fase, por «Mis casos» y por vencidos, y limpiar',
      'Cada control reemite la lectura con su query (`status=…`, `technician_id=me`, `overdue=1`) y la lista sigue al filtro; «Mis casos» excluye el caso del otro técnico y limpiar vuelve a la lectura sin filtros',
      async entry => {
        const stages = []

        const read = async (label, action, expectQuery) => {
          const sent = await respondTo(page, { method: 'GET', path: LAB }, action, 15000).catch(
            () => ({ status: null })
          )
          await settle(page)

          const panel = await labPanel(page)
          const call = lastCall(requests, 'GET', LAB)
          const query = call?.query ?? {}
          const matches = Object.entries(expectQuery ?? {}).every(
            ([key, value]) => String(query[key] ?? '') === String(value)
          )

          stages.push({
            label,
            status: sent.status,
            query,
            expectQuery,
            matches,
            filters: panel.filters,
            rows: panel.rows.map(row => ({
              id: row.id,
              status: row.status,
              technician: row.technician,
              overdue: row.overdue
            })),
            empty: panel.empty
          })

          return { panel, query, matches, sent }
        }

        const phaseOptions = await readSelectOptions(page, '[data-lab-cases-status] button')

        await read(
          'status',
          () => pickOption(page, '[data-lab-cases-status] button', PHASE_LABELS.in_laboratory),
          { status: 'in_laboratory' }
        )
        await read('clear', () => page.locator('[data-lab-cases-clear]').click({ timeout: 8000 }), {
          status: ''
        })
        await read(
          'technician',
          () => pickOption(page, '[data-lab-cases-technician] button', 'Mis casos'),
          {
            technician_id: 'me'
          }
        )

        const mine = await api(technician, 'GET', `${LAB}?technician_id=me&per_page=100`)
        const mineIds = rowsOf(mine.body).map(row => row.id)

        await read(
          'overdue',
          () => page.locator('[data-lab-cases-overdue]').check({ timeout: 8000 }),
          { overdue: '1', technician_id: 'me' }
        )
        await read(
          'clear-again',
          () => page.locator('[data-lab-cases-clear]').click({ timeout: 8000 }),
          { status: '' }
        )

        evidence.captures.push(await shot(page, 'shots/walk/lab-filters.png'))

        const statusStage = stages.find(stage => stage.label === 'status') ?? null
        const technicianStage = stages.find(stage => stage.label === 'technician') ?? null
        const overdueStage = stages.find(stage => stage.label === 'overdue') ?? null
        const clearStage = stages.find(stage => stage.label === 'clear-again') ?? null

        const statusHolds = (statusStage?.rows ?? []).every(
          row => row.status === PHASE_LABELS.in_laboratory
        )
        const technicianExcludesOwn = ids.C === null || !mineIds.includes(ids.C)
        const technicianKeepsOwn = [ids.A, ids.B, ids.D]
          .filter(id => id !== null)
          .every(id => mineIds.includes(id))
        const technicianStageClean = (technicianStage?.rows ?? []).every(
          row => !ids.C || row.id !== ids.C
        )
        const overdueHolds = (overdueStage?.rows ?? []).every(row => row.overdue === true)
        const cleared = clearStage
          ? clearStage.query.status === undefined || clearStage.query.status === ''
          : false
        const everyStageMoved = stages.every(stage => stage.matches)

        entry.obtained = `opciones de fase ${JSON.stringify(phaseOptions.options)} · ${stages
          .map(
            stage =>
              `${stage.label}: ${JSON.stringify(stage.query)} → ${stage.rows.length} fila(s)${
                stage.empty ? ` vacío «${stage.empty.slice(0, 40)}…»` : ''
              }`
          )
          .join(
            ' · '
          )} · API technician_id=me ${mine.status} ${mineIds.length} caso(s), con el caso C ${
          technicianExcludesOwn ? 'ausente' : 'PRESENTE'
        }`
        entry.request = lastCall(requests, 'GET', LAB)
          ? {
              method: 'GET',
              path: LAB,
              status: lastCall(requests, 'GET', LAB).status,
              query: lastCall(requests, 'GET', LAB).query
            }
          : null
        entry.detail = {
          phaseOptions,
          stages,
          statusHolds,
          mine: {
            status: mine.status,
            ids: mineIds,
            excludesC: technicianExcludesOwn,
            keepsOwn: technicianKeepsOwn
          },
          technicianStageClean,
          overdueHolds,
          cleared,
          everyStageMoved
        }
        entry.verdict =
          everyStageMoved &&
          statusHolds &&
          technicianExcludesOwn &&
          technicianKeepsOwn &&
          technicianStageClean &&
          overdueHolds &&
          cleared
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : `algún filtro no reemite su query o la lista no sigue al filtro (${JSON.stringify(
                stages.map(stage => `${stage.label}:${stage.matches}`)
              )})`

        return entry
      }
    )

    await step(
      'phase',
      'Avanzar el caso A a «Prueba en boca» desde la puerta de fase',
      'El POST responde 200, la fase del caso cambia y el conjunto ofrecido se reduce: la fase que acaba de dejar ya no se ofrece',
      async entry => {
        const target = evidence.pageOf.A?.id ?? null

        if (!target) {
          entry.obtained = 'el caso A de la sesión no está en la lista'
          entry.verdict = 'NO PROBADO'
          entry.notes = 'sin fila no hay puerta de fase que medir'

          return entry
        }

        const openedCase = await openCase(page, target)

        if (!openedCase.opened) {
          entry.obtained = `el detalle del caso A no se abrió (${openedCase.reason ?? 'sin diálogo'})`
          entry.verdict = 'DEFECTO'
          entry.notes = 'la acción «Ver» de la fila no pinta el detalle'

          return entry
        }

        const before = openedCase.detail.phases
        const clicked = await respondTo(
          page,
          { method: 'POST', path: `/api/lab-cases/${target}/phase` },
          () => page.locator('[data-lab-case-phase="try_in"]').click({ timeout: 8000 }),
          20000
        ).catch(() => ({ status: null, body: null }))

        await page
          .waitForFunction(
            () =>
              Array.from(document.querySelectorAll('[data-lab-case-phase]')).every(
                node => node.getAttribute('data-lab-case-phase') !== 'try_in'
              ),
            undefined,
            { timeout: 10000 }
          )
          .catch(() => {})
        await settle(page)

        const after = await detail(page)
        const toast = await toastText(page)
        // A forward skip is legal (S2): from `in_preparation` the door offers
        // `try_in` directly, so the set shrinks by every phase up to and
        // including the one just entered, never by exactly one.
        const afterPhases = sorted(after?.phases)
        const shrank =
          afterPhases.length < sorted(before).length &&
          afterPhases.every(phase => sorted(before).includes(phase)) &&
          !afterPhases.includes('try_in') &&
          afterPhases.includes('delivered')

        if (clicked.status === 200) evidence.created.advancedTo = after?.phase ?? null

        evidence.captures.push(await shot(page, 'shots/walk/lab-phase.png'))

        entry.obtained = `fases antes ${JSON.stringify(before)} · POST ${
          clicked.status ?? 'sin petición'
        } «${clicked.body?.meta?.message ?? '—'}» · fase del modal «${after?.phase ?? '—'}» · fases después ${JSON.stringify(
          after?.phases
        )} · reducción ${shrank} · toast «${toast}»`
        entry.request =
          clicked.status !== null
            ? { method: 'POST', path: `/api/lab-cases/${target}/phase`, status: clicked.status }
            : null
        entry.detail = {
          before,
          after,
          shrank,
          status: clicked.status,
          body: clicked.body ?? null,
          toast
        }
        entry.verdict =
          clicked.status === 200 &&
          after?.phase === PHASE_LABELS.try_in &&
          shrank &&
          (after?.phases ?? []).length > 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la puerta no avanza la fase, el modal no la refleja o sigue ofreciendo la fase recién dejada'

        await closeDialog(page)

        return entry
      }
    )

    await step(
      'backward',
      'Forzar por la API el retroceso del caso A a «En preparación»',
      'La puerta responde 422 con el mensaje que nombra la fase actual y la pedida en `errors.phase`, y el caso conserva su fase',
      async entry => {
        const target = evidence.pageOf.A?.id ?? null

        if (!target) {
          entry.obtained = 'el caso A de la sesión no está en la lista'
          entry.verdict = 'NO PROBADO'
          entry.notes = 'sin caso no hay retroceso que medir'

          return entry
        }

        const refused = await api(technician, 'POST', `/api/lab-cases/${target}/phase`, {
          phase: 'in_preparation'
        })
        const message = refused.body?.errors?.phase?.[0] ?? null
        const readBack = await api(technician, 'GET', `/api/lab-cases/${target}`)
        const current = readBack.body?.data?.status ?? null

        evidence.created.fetchedAfterRefusal = current

        const namesBoth = /in_preparation/.test(String(message)) && /try_in/.test(String(message))

        entry.obtained = `POST ${refused.status ?? 'sin respuesta'} · errors.phase[0] «${
          message ?? '—'
        }» · GET ${readBack.status} fase «${current ?? '—'}»`
        entry.request =
          refused.status !== null
            ? { method: 'POST', path: `/api/lab-cases/${target}/phase`, status: refused.status }
            : null
        entry.detail = {
          status: refused.status,
          body: refused.body ?? null,
          message,
          namesBoth,
          readBack: { status: readBack.status, phase: current }
        }
        entry.verdict =
          refused.status === 422 && namesBoth && current === 'try_in'
            ? 'OK'
            : refused.status === 422 && current === 'try_in'
              ? 'CONFUSO'
              : 'DEFECTO'
        entry.notes =
          refused.status === 422 && current === 'try_in'
            ? namesBoth
              ? ''
              : `el 422 no nombra las dos fases: «${message}»`
            : 'el retroceso no se rechaza con 422 o el caso cambia de fase'

        return entry
      }
    )

    await step(
      'placed',
      'Abrir el caso D, ya colocado, como administración',
      'El detalle declara «El caso ya está colocado.» y no ofrece ninguna fase',
      async entry => {
        await primeSession(page, admin)
        await gotoSurface(page, SURFACE)
        await settle(page)

        const target = ids.D

        if (!target) {
          entry.obtained = 'el caso D de la sesión no existe'
          entry.verdict = 'NO PROBADO'
          entry.notes = 'sin caso colocado no hay estado final que medir'

          return entry
        }

        const openedCase = await openCase(page, target)

        if (!openedCase.opened) {
          entry.obtained = `el detalle del caso D no se abrió (${openedCase.reason ?? 'sin diálogo'})`
          entry.verdict = 'DEFECTO'
          entry.notes = 'la acción «Ver» de la fila no pinta el detalle'

          return entry
        }

        const phases = openedCase.detail.phases ?? []
        const emptyText = openedCase.detail.phaseEmpty ?? ''

        evidence.captures.push(await shot(page, 'shots/walk/lab-placed.png'))

        entry.obtained = `fase «${openedCase.detail.phase}» · fases ofrecidas ${JSON.stringify(
          phases
        )} · vacío de la puerta «${emptyText}»`
        entry.request =
          openedCase.status !== null
            ? { method: 'GET', path: `/api/lab-cases/${target}`, status: openedCase.status }
            : null
        entry.detail = { openedCase, phases, emptyText }
        entry.verdict =
          openedCase.detail.phase === PHASE_LABELS.placed &&
          /El caso ya está colocado\./.test(emptyText) &&
          phases.length === 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el caso colocado no publica su estado final, no explica el vacío de la puerta o sigue ofreciendo fases'

        await closeDialog(page)

        return entry
      }
    )

    await step(
      'no-edit',
      'Buscar en la lista la acción de alta o de edición de un caso',
      'La superficie no ofrece alta ni edición: el héroe publica solo «Actualizar» y cada fila solo «Ver», así que la edición de un caso vive fuera de esta pantalla',
      async entry => {
        const panel = await labPanel(page)
        const matches = await writeControls(page, WRITE_LABELS)
        const rowActions = await page.evaluate(() =>
          Array.from(document.querySelectorAll('[data-lab-cases-table] tbody tr')).map(row =>
            Array.from(row.querySelectorAll('button, a')).map(node =>
              (node.innerText ?? '').trim().replace(/\s+/g, ' ')
            )
          )
        )
        const distinctRowActions = [...new Set(rowActions.flat().filter(Boolean))]

        entry.obtained = `héroe «${panel.hero.title}» · controles de escritura ${JSON.stringify(
          matches
        )} · acciones por fila ${JSON.stringify(distinctRowActions)} · filas ${rowActions.length}`
        entry.request = null
        entry.detail = { hero: panel.hero, matches, distinctRowActions, rowActions }
        entry.verdict = matches.length === 0 ? 'NO-OP' : 'DEFECTO'
        entry.notes =
          matches.length === 0
            ? 'la superficie no ofrece alta ni edición; la issue las lista entre sus acciones y el formulario vive en la ficha del paciente y en el cierre de la atención'
            : `aparecieron controles de escritura no declarados: ${JSON.stringify(matches)}`

        return entry
      }
    )

    await step(
      'foreign-put',
      'Editar por la API el caso C, que pertenece al otro técnico',
      'La puerta no distingue propiedad: el técnico autenticado recibe un estado que dice si puede escribir el caso ajeno, y recepción responde 403',
      async entry => {
        const target = ids.C

        if (!target) {
          entry.obtained = 'el caso C de la sesión no existe'
          entry.verdict = 'NO PROBADO'
          entry.notes = 'sin caso ajeno no hay edición que medir'

          return entry
        }

        // An empty payload: the door still answers with the row, and the session
        // does not rewrite the work order of the other technician to measure it.
        const asTechnician = await api(technician, 'PUT', `/api/lab-cases/${target}`, {})
        const asReception = await api(reception, 'PUT', `/api/lab-cases/${target}`, {})

        const owner = asTechnician.body?.data?.technician_id ?? cases.C?.technicianId ?? null
        const actor = technician.user?.id ?? null
        const authentication = asTechnician.body?.message ?? null

        entry.obtained = `caso C (id ${target}, técnico ${owner}) · PUT con técnico ${
          asTechnician.status ?? 'sin respuesta'
        } «${authentication ?? '—'}» · PUT con recepción ${asReception.status ?? 'sin respuesta'} «${
          asReception.body?.message ?? '—'
        }» · actor técnico ${actor}`
        entry.request =
          asTechnician.status !== null
            ? { method: 'PUT', path: `/api/lab-cases/${target}`, status: asTechnician.status }
            : null
        entry.detail = {
          target,
          owner,
          actor,
          asTechnician: {
            status: asTechnician.status,
            body: asTechnician.body ?? null
          },
          asReception: {
            status: asReception.status,
            message: asReception.body?.message ?? null
          }
        }
        entry.verdict =
          asTechnician.status === 200 && asReception.status === 403
            ? 'CONFUSO'
            : asTechnician.status === 403 && asReception.status === 403
              ? 'OK'
              : 'DEFECTO'
        entry.notes =
          entry.verdict === 'CONFUSO'
            ? 'la puerta PUT acepta al técnico sobre el caso de otro técnico (200) con el mismo permiso `manage.lab_case`: no hay regla de propiedad documentada, y recepción sí recibe 403; el cuerpo vacío no modificó la orden de trabajo'
            : entry.verdict === 'OK'
              ? ''
              : `las dos puertas no responden como declara el catálogo de permisos (técnico ${
                  asTechnician.status
                }, recepción ${asReception.status})`

        return entry
      }
    )

    await step(
      'read-only',
      'Abrir el caso B como recepción: la superficie lee y no escribe',
      'El detalle publica la frase de solo lectura y ninguna fase; la puerta de fase responde 403 con el token de recepción',
      async entry => {
        await primeSession(page, reception)
        await gotoSurface(page, SURFACE)
        await settle(page)

        const target = ids.B

        if (!target) {
          entry.obtained = 'el caso B de la sesión no existe'
          entry.verdict = 'NO PROBADO'
          entry.notes = 'sin caso no hay detalle de solo lectura que medir'

          return entry
        }

        const openedCase = await openCase(page, target)

        if (!openedCase.opened) {
          entry.obtained = `el detalle del caso B no se abrió (${openedCase.reason ?? 'sin diálogo'})`
          entry.verdict = 'DEFECTO'
          entry.notes = 'la acción «Ver» de la fila no pinta el detalle para recepción'

          return entry
        }

        const phases = openedCase.detail.phases ?? []
        const door = await api(reception, 'POST', `/api/lab-cases/${target}/phase`, {
          phase: 'try_in'
        })

        evidence.captures.push(await shot(page, 'shots/walk/lab-read-only.png'))

        entry.obtained = `fase «${openedCase.detail.phase}» · solo lectura «${
          openedCase.detail.readOnly ?? 'ausente'
        }» · fases ofrecidas ${JSON.stringify(phases)} · POST puerta ${
          door.status ?? 'sin respuesta'
        } «${door.body?.message ?? '—'}»`
        entry.request =
          door.status !== null
            ? { method: 'POST', path: `/api/lab-cases/${target}/phase`, status: door.status }
            : null
        entry.detail = {
          openedCase,
          phases,
          door: { status: door.status, body: door.body ?? null }
        }
        entry.verdict =
          Boolean(openedCase.detail.readOnly) && phases.length === 0 && door.status === 403
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'recepción no ve la frase de solo lectura, ve fases o la puerta no responde 403'

        return entry
      }
    )

    await step(
      'contract',
      'Medir el contrato del diálogo de detalle',
      'Centrado o anclado, un solo h1, foco atrapado, scroll bloqueado, nombre accesible y Escape lo cierra: una fila por comprobación',
      async entry => {
        let dialog = page.locator(DIALOG).last()
        let isOpen = await dialog.isVisible().catch(() => false)
        let reopened = false

        if (!isOpen && ids.B) {
          const openedCase = await openCase(page, ids.B)
          isOpen = openedCase.opened
          reopened = openedCase.opened
          dialog = page.locator(DIALOG).last()
        }

        if (!isOpen) {
          entry.obtained = 'ningún detalle de caso quedó abierto para medir el contrato'
          entry.verdict = 'NO PROBADO'
          entry.notes =
            'el paso `read-only` no dejó el modal abierto y el caso B no se pudo reabrir'

          return entry
        }

        evidence.captures.push(await shot(page, 'shots/walk/lab-dialog.png'))

        const contract = await assertDialogContract(page, { dialog })
        const closed = !(await dialog.isVisible().catch(() => true))
        const failed = contract.checks.filter(check => !check.ok).map(check => check.id)

        entry.obtained = `«${contract.dialog.title}» ${JSON.stringify(contract.dialog.box)} · ${contract.checks
          .map(check => `${check.id}=${check.ok ? 'ok' : 'FALLA'}`)
          .join(' · ')} · reabierto ${reopened} · cerrado tras Escape ${closed}`
        entry.detail = { contract, closed, reopened }
        entry.verdict = failed.length === 0 && closed ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : `el diálogo incumple ${JSON.stringify(failed)}${closed ? '' : ' y Escape no lo cierra'}`

        return entry
      }
    )

    await step(
      'absent-create',
      'Contar en el héroe y en las filas los controles de alta y edición',
      'Cero controles con «Nuevo», «Nueva», «Crear» o «Editar»: la issue las lista entre las acciones de la superficie y la pantalla no las ofrece',
      async entry => {
        const matches = await writeControls(page, WRITE_LABELS)
        const panel = await labPanel(page)
        const heroButtons = await page.evaluate(() =>
          Array.from(
            document.querySelectorAll('[data-lab-cases-hero] button, [data-lab-cases-hero] a')
          ).map(node => (node.innerText ?? '').trim().replace(/\s+/g, ' '))
        )
        const rowButtons = await page.evaluate(() =>
          Array.from(
            document.querySelectorAll(
              '[data-lab-cases-table] tbody button, [data-lab-cases-table] tbody a'
            )
          ).map(node => (node.innerText ?? '').trim().replace(/\s+/g, ' '))
        )

        evidence.captures.push(await shot(page, 'shots/walk/lab-absent-create.png'))

        entry.obtained = `coincidencias ${JSON.stringify(matches)} · héroe ${JSON.stringify([
          ...new Set(heroButtons.filter(Boolean))
        ])} · filas ${JSON.stringify([...new Set(rowButtons.filter(Boolean))])} · filas montadas ${
          panel.rows.length
        }`
        entry.request = null
        entry.detail = { matches, heroButtons, rowButtons, rows: panel.rows.length }
        entry.verdict = matches.length === 0 ? 'NO-OP' : 'DEFECTO'
        entry.notes =
          matches.length === 0
            ? 'alta y edición no existen en esta pantalla: la issue las lista entre sus acciones; el formulario vive en la ficha del paciente y en el cierre de la atención'
            : `la superficie publica controles que la medición no esperaba: ${JSON.stringify(matches)}`

        return entry
      }
    )
  } finally {
    await dismissToasts(page).catch(() => {})
    await closeDialog(page).catch(() => {})

    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.consoleErrors = diagnostics.consoleErrors
    // The backward move is forced through the API on purpose, and the browser
    // logs that 422 as a console error: it is the measurement, not a defect.
    evidence.consoleClassified = classifyConsole(diagnostics.consoleErrors, ROLE, [
      {
        pattern: /Failed to load resource: the server responded with a status of 422/,
        reason: 'el recorrido provoca el 422 del retroceso de fase'
      }
    ])
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, ROLE)

    markExpected(evidence.failedRequests, declaredFailure.predicate, declaredFailure.reason)

    evidence.traffic = {
      api: requests.length,
      listReads: requests.filter(row => row.path === LAB).length,
      writes: requests
        .filter(row => !['GET'].includes(row.method))
        .map(row => `${row.method} ${row.path} → ${row.status ?? 'sin respuesta'}`),
      phaseDoors: requests
        .filter(row => /^\/api\/lab-cases\/\d+\/phase$/.test(row.path))
        .map(row => `${row.method} ${row.path} → ${row.status ?? 'sin respuesta'}`),
      withoutStatus: requests.filter(row => row.status === null).length
    }
    evidence.counts = walkCounts(evidence.steps)

    audit('walk-lab', evidence)
  }

  expect(evidence.pageErrors, 'page errors del recorrido de laboratorio').toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    'pasos declarados del recorrido de laboratorio'
  ).toEqual([
    '01-list',
    '02-detail',
    '03-filters',
    '04-phase',
    '05-backward',
    '06-placed',
    '07-no-edit',
    '08-foreign-put',
    '09-read-only',
    '10-contract',
    '11-absent-create'
  ])
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    'cada paso del recorrido de laboratorio deja su captura'
  ).toBe(true)

  return evidence
}

test('@U10 · capa 4 — Laboratorio: la lista, la fase y el caso colocado', async ({ page }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  await walkThroughLabCases({ page, fixtures })
})
