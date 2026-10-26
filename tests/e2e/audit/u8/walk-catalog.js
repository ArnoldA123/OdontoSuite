// Plan #13 (issue #371, U8) — layer 4: the catalogue list, action by action.
//
// The walkthrough drives `/procedure-catalog` the way an administrator does:
// search by name and by code, filter by specialty and status, page through the
// 46 rows, submit the empty form, create a procedure with applicability and a
// public nomenclator code, edit it, deactivate and reactivate it, duplicate a
// procedure that declares both, try the CSV importer without a file, and
// compare this table against the one the statistics page renders.
//
// Two facts the issue names are measured, not assumed:
//  * the list search filters on `q` (`ProcedureCatalogController@index`), so it
//    works — and the reception consult, which sends `search`, filters nothing;
//  * the list is a hand-written `<table>` while `/procedure-stats` mounts
//    `UiDataTable`, so the two lists of one module do not share pagination,
//    empty state or the scroll affordance.
//
// Every write goes through the product's own doors on a row this run created;
// the seeded catalogue is only read.

import { test, expect } from 'playwright/test'

import {
  classifyFailures,
  collectDiagnostics,
  loginAs,
  primeSession,
  settleSurface
} from '../harness.js'

import { ensureFixtures, fixtureSummary, registerCreated } from './fixtures.js'

import {
  CATALOG_ROUTE,
  DESKTOP,
  LOAD_TIMEOUT,
  MOBILE,
  RECEPTION_ROUTE,
  STATS_ROUTE,
  THRESHOLD,
  UI,
  audit,
  call,
  classifyConsole,
  closeAllDialogs,
  exists,
  fillControl,
  markExpected,
  openSelect,
  readCatalog,
  readReception,
  readStats,
  recorder,
  settleCatalog,
  settleReception,
  settleStats,
  stamp,
  toastText,
  walkTotals,
  watchRequests
} from './helpers.js'

const listCalls = entries =>
  entries.filter(entry => entry.method === 'GET' && entry.path === '/api/procedure-catalog')

const lastListCall = entries => listCalls(entries).at(-1) ?? null

const rowByCode = (page, code) => page.locator(UI.catalog.rows).filter({ hasText: code }).first()

const waitForList = (page, matcher, action, timeout = LOAD_TIMEOUT) => {
  const pending = page
    .waitForResponse(
      response => {
        if (response.request().method() !== 'GET') return false
        try {
          const url = new URL(response.url())
          return url.pathname === '/api/procedure-catalog' && matcher(url.searchParams)
        } catch {
          return false
        }
      },
      { timeout }
    )
    .catch(() => null)

  return Promise.resolve(action()).then(() => pending)
}

const rowCodes = async page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[data-procedure-row]')).map(row =>
      (row.querySelector('td')?.innerText ?? '').trim()
    )
  )

/**
 * Returns to the unfiltered catalogue.
 *
 * The page keeps its filters *and its current page* between reloads and
 * mutations (`onSaved` reloads with whatever is applied), so a step that needs
 * the whole catalogue reloads the route: a fresh mount is the only state where
 * page 1, no search and no select are guaranteed at once.
 */
const resetFilters = async page => {
  await page.goto(CATALOG_ROUTE)
  await settleCatalog(page)
  await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
  return readCatalog(page)
}

/**
 * Searches the list by code and returns the row, so a step never depends on
 * where the alphabetical pagination happened to place the row it owns: the
 * catalogue sorts by name and thirteen audit rows of previous runs already sit
 * between "Alineadores" and the row this run creates.
 */
const focusRow = async (page, code) => {
  await waitForList(
    page,
    params => params.get('q') === code,
    async () => {
      await fillControl(page, UI.catalog.search, code)
      await page.waitForTimeout(400)
    }
  )
  await settleCatalog(page)
  const row = rowByCode(page, code)
  const visible = await row
    .waitFor({ state: 'visible', timeout: 8000 })
    .then(() => true)
    .catch(() => false)

  return { row, visible }
}

const dialogButton = (page, label) =>
  page.locator('[role="alertdialog"] button').filter({ hasText: label }).first()

test('@U8 · capa 4 — el catálogo de procedimientos, acción por acción', async ({
  page,
  request
}) => {
  test.setTimeout(300000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const { surface } = fixtures.procedures
  const specialty = fixtures.specialties[0]
  const runCode = `AUDIT-U8-${fixtures.token}-UI`

  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const state = { pageErrors: diagnostics.pageErrors, currentStep: null }
  const evidence = {
    ...stamp(),
    route: CATALOG_ROUTE,
    role: 'administrador',
    shotDir: 'walk/catalog',
    steps: [],
    findings: [],
    fixtures: fixtureSummary(fixtures),
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }
  const step = recorder(page, evidence, state)

  try {
    await page.setViewportSize(DESKTOP)
    // A broken step must cost seconds, not the whole session budget: every
    // locator action inside this walk is bounded.
    page.setDefaultTimeout(7000)
    await primeSession(page, session)
    await page.goto(CATALOG_ROUTE)
    await settleCatalog(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    await step(
      'index',
      'Abrir /procedure-catalog como administración',
      'La lista pinta 20 filas, la tira de KPIs y la paginación del total del catálogo',
      async entry => {
        const data = await readCatalog(page)
        const call = lastListCall(requests)
        const totalFromCount = Number(String(data.filterCount).match(/de (\d+)/)?.[1] ?? NaN)
        const kpiTotal = Number(String(data.kpis.total).replace(/[^\d]/g, ''))
        entry.obtained = `${data.rowCount} filas · «${data.filterCount}» · KPI total ${data.kpis.total} · paginación ${data.pagination.labels.length ? 'presente' : 'ausente'}`
        entry.requestStatus = call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.detail = {
          kpis: data.kpis,
          rowCount: data.rowCount,
          firstCode: data.firstCode,
          lastCode: data.lastCode,
          rowsPerPage: lastListCall(requests)?.query?.per_page ?? null,
          tables: data.tables
        }
        entry.verdict =
          data.rowCount === 20 && kpiTotal === totalFromCount && data.pagination.present
            ? 'OK'
            : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : 'la fila, el total o la paginación no coinciden con lo que publica la API'
        return entry
      }
    )

    await step(
      'search-name',
      'Buscar por nombre con el término del procedimiento de la sesión',
      'El servidor filtra por nombre: queda una fila y la petición lleva q',
      async entry => {
        const term = surface.name
        const response = await waitForList(
          page,
          params => params.get('q') === term,
          () => fillControl(page, UI.catalog.search, term)
        )
        await settleCatalog(page)
        const data = await readCatalog(page)
        const call = lastListCall(requests)
        entry.obtained = `${data.rowCount} fila(s) · códigos ${JSON.stringify(data.rows.map(row => row.code))} · q=${call?.query?.q ?? '—'}`
        entry.requestStatus = call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.detail = { serverStatus: response?.status() ?? null, filterCount: data.filterCount }
        entry.verdict =
          data.rowCount === 1 && data.rows[0]?.code === surface.code && response?.status() === 200
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'search-code',
      'Buscar por código del procedimiento',
      'El código también se busca en el servidor y devuelve su fila',
      async entry => {
        const response = await waitForList(
          page,
          params => params.get('q') === surface.code,
          () => fillControl(page, UI.catalog.search, surface.code)
        )
        await settleCatalog(page)
        const data = await readCatalog(page)
        const call = lastListCall(requests)
        entry.obtained = `${data.rowCount} fila(s) ${JSON.stringify(data.rows.map(row => row.code))} · q=${call?.query?.q ?? '—'}`
        entry.requestStatus = call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.detail = { serverStatus: response?.status() ?? null }
        entry.verdict =
          data.rowCount === 1 && data.rows[0]?.code === surface.code ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'search-empty',
      'Buscar un término que no existe',
      'La lista explica que no hay resultados y nombra el término buscado',
      async entry => {
        const term = 'ZZZ-NO-EXISTE-U8'
        await waitForList(
          page,
          params => params.get('q') === term,
          () => fillControl(page, UI.catalog.search, term)
        )
        await settleCatalog(page)
        const data = await readCatalog(page)
        const body = await page.evaluate(() => document.querySelector('main')?.innerText ?? '')
        entry.obtained = `filas ${data.rowCount} · estado vacío: ${JSON.stringify(data.empty)}`
        entry.requestStatus = lastListCall(requests)?.status ?? null
        entry.detail = { emptyTitle: data.empty, mentionsTerm: body.includes(term) }
        entry.verdict = data.rowCount === 0 && data.empty === 'Sin resultados' ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'filter-specialty',
      `Filtrar por la especialidad «${specialty.name}»`,
      'El filtro viaja al servidor y las filas quedan en esa especialidad',
      async entry => {
        await fillControl(page, UI.catalog.search, '')
        await waitForList(
          page,
          params => params.get('specialty') === specialty.code,
          () => openSelect(page, `${UI.catalog.specialty} button`, specialty.name)
        )
        await settleCatalog(page)
        const data = await readCatalog(page)
        const call = lastListCall(requests)
        const specialties = [...new Set(data.rows.map(row => row.specialty))]
        entry.obtained = `${data.rowCount} filas · especialidades visibles ${JSON.stringify(specialties)} · specialty=${call?.query?.specialty ?? '—'}`
        entry.requestStatus = call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.verdict =
          call?.query?.specialty === specialty.code &&
          data.rowCount > 0 &&
          specialties.every(label => label === specialty.name)
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'filter-status',
      'Filtrar por estado inactivo y volver a activos',
      'Cada estado responde su propia población y todas sus filas comparten el estado pedido',
      async entry => {
        const base = await resetFilters(page)

        const inert = () => page.waitForTimeout(500)
        await waitForList(
          page,
          params => params.get('is_active') === 'false' || params.get('is_active') === '0',
          async () => {
            await openSelect(page, `${UI.catalog.status} button`, 'Inactivos')
            await inert()
          }
        )
        await page
          .waitForFunction(
            () => {
              const rows = Array.from(document.querySelectorAll('[data-procedure-row]'))
              return (
                rows.length === 0 ||
                rows.every(row => /Inactivo/.test(row.querySelectorAll('td')[5]?.innerText ?? ''))
              )
            },
            undefined,
            { timeout: 8000, polling: 120 }
          )
          .catch(() => {})
        await settleCatalog(page)
        const inactive = await readCatalog(page)
        const inactiveBody = await page.evaluate(
          () => document.querySelector('main')?.innerText ?? ''
        )
        const inactiveBadges = inactive.rows.map(row => row.status)
        const allInactive = inactiveBadges.every(label => /Inactivo/.test(label ?? ''))
        const inactiveQuery = lastListCall(requests)?.query ?? {}

        await waitForList(
          page,
          params => params.get('is_active') === 'true' || params.get('is_active') === '1',
          async () => {
            await openSelect(page, `${UI.catalog.status} button`, 'Activos')
            await inert()
          }
        )
        await page
          .waitForFunction(
            () => {
              const rows = Array.from(document.querySelectorAll('[data-procedure-row]'))
              return (
                rows.length === 0 ||
                rows.every(row => /^Activo/.test(row.querySelectorAll('td')[5]?.innerText ?? ''))
              )
            },
            undefined,
            { timeout: 8000, polling: 120 }
          )
          .catch(() => {})
        await settleCatalog(page)
        const active = await readCatalog(page)
        const activeBadges = active.rows.map(row => row.status)
        const allActive = activeBadges.every(label => /^Activo/.test(label ?? ''))
        const activeQuery = lastListCall(requests)?.query ?? {}

        // The same filter, measured at the door: `(bool) 'false'` is true in
        // PHP, so `is_active=false` answers the active population.
        const apiInactive = await call(
          request,
          session,
          'GET',
          '/api/procedure-catalog?is_active=false&per_page=100'
        )
        const apiInactiveRows = Array.isArray(apiInactive.body?.data) ? apiInactive.body.data : []
        const apiInactiveCount = apiInactiveRows.filter(row => row.is_active === false).length

        entry.obtained = `inactivos: ${inactive.rowCount} fila(s), todas inactivas ${allInactive}${inactive.rowCount === 0 ? ` (${JSON.stringify(inactive.empty)})` : ''} · activos: ${active.rowCount} filas «${active.filterCount}», todas activas ${allActive} · is_active ${inactiveQuery.is_active ?? '—'} → ${activeQuery.is_active ?? '—'}`
        entry.requestStatus = lastListCall(requests)?.status ?? null
        entry.request = { method: 'GET', path: '/api/procedure-catalog', query: activeQuery }
        entry.detail = {
          unfilteredTotal: base.filterCount,
          inactiveRows: inactive.rowCount,
          inactiveEmpty: inactiveBody.includes('No hay procedimientos inactivos'),
          inactiveBadges: inactiveBadges.slice(0, 6),
          activeRows: active.rowCount,
          activeBadges: activeBadges.slice(0, 6),
          api: {
            status: apiInactive.status,
            total: apiInactive.body?.meta?.total ?? null,
            inactiveRows: apiInactiveCount,
            note: 'ProcedureCatalogService::paginate aplica (bool) al valor del filtro: la cadena "false" es true en PHP'
          }
        }
        const inactiveOk =
          inactive.rowCount === 0
            ? inactiveBody.includes('No hay procedimientos inactivos')
            : allInactive
        entry.verdict = inactiveOk && apiInactiveCount === 0 ? 'OK' : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : `el filtro «Inactivos» pinta ${inactive.rowCount} filas con estado ${JSON.stringify(inactiveBadges.slice(0, 3))} y la API responde ${apiInactiveCount} filas inactivas de ${apiInactiveRows.length}: is_active=false se casta a true`
        return entry
      }
    )

    await step(
      'pagination',
      'Pasar a la página 2 del listado',
      'La segunda página pide page=2 y muestra filas distintas de la primera',
      async entry => {
        await resetFilters(page)
        const firstPage = await rowCodes(page)
        const responsePromise = page
          .waitForResponse(
            candidate => {
              if (candidate.request().method() !== 'GET') return false
              try {
                const url = new URL(candidate.url())
                return (
                  url.pathname === '/api/procedure-catalog' && url.searchParams.get('page') === '2'
                )
              } catch {
                return false
              }
            },
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await page
          .locator('main nav button, main nav a')
          .filter({ hasText: /^2$/ })
          .first()
          .click({ timeout: 8000 })
          .catch(() => {})
        const response = await responsePromise
        await page.waitForTimeout(600)
        await settleCatalog(page)

        const secondPage = await rowCodes(page)
        const call = lastListCall(requests)
        const paginationInfo = await page
          .evaluate(() =>
            (document.querySelector('main .pagination-text')?.innerText ?? '')
              .trim()
              .replace(/\s+/g, ' ')
          )
          .catch(() => null)
        entry.obtained = `página 1: ${firstPage[0]}… · página 2: ${secondPage[0]}… (${secondPage.length} filas) · «${paginationInfo}»`
        entry.requestStatus = call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.detail = {
          responseStatus: response?.status() ?? null,
          pageQuery: call?.query?.page ?? null,
          perPageQuery: call?.query?.per_page ?? null,
          paginationInfo,
          overlap: secondPage.filter(code => firstPage.includes(code)).length
        }
        entry.verdict =
          secondPage.length > 0 && secondPage[0] !== firstPage[0] && call?.query?.page === '2'
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'pagination-info',
      'Leer la información de paginación con la página completa',
      'El texto «Mostrando 1 a N de M» usa el tamaño de página que la lista pide',
      async entry => {
        await resetFilters(page)
        const measure = await page.evaluate(() => {
          const info = document.querySelector('main .pagination-text')
          const buttons = Array.from(
            document.querySelectorAll('main .pagination-nav-button, main .pagination-page-button')
          ).map(node => (node.innerText ?? '').trim())
          return {
            info: (info?.innerText ?? '').trim().replace(/\s+/g, ' '),
            buttons: buttons.filter(Boolean),
            rows: document.querySelectorAll('[data-procedure-row]').length
          }
        })
        const call = lastListCall(requests)
        const perPage = Number(call?.query?.per_page ?? 0)
        const claimed = Number(String(measure.info).match(/a (\d+) de/)?.[1] ?? 0)
        entry.obtained = `«${measure.info}» · filas pintadas ${measure.rows} · per_page solicitado ${perPage} · botones ${JSON.stringify(measure.buttons)}`
        entry.requestStatus = call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.detail = {
          info: measure.info,
          rows: measure.rows,
          perPage,
          claimed,
          buttons: measure.buttons
        }
        entry.verdict = claimed === measure.rows && measure.rows === perPage ? 'OK' : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : `el texto de paginación dice «1 a ${claimed}» con ${measure.rows} filas pintadas y per_page ${perPage}`
        return entry
      }
    )

    await step(
      'create-empty',
      'Enviar el alta vacía desde «Nuevo procedimiento» → «Crear»',
      'El formulario marca los campos obligatorios y no crea nada (sin POST 201)',
      async entry => {
        await resetFilters(page)
        const before = listCalls(requests).length
        await page.locator(UI.catalog.create).click({ timeout: 8000 })
        await page
          .locator('[role="dialog"]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})
        await page.locator('[data-form-submit]').first().click({ timeout: 8000 })
        await page.waitForTimeout(700)

        const form = await page.evaluate(() => {
          const dialog = document.querySelector('[role="dialog"]')
          return {
            open: Boolean(dialog),
            errors: Array.from(dialog?.querySelectorAll('p') ?? [])
              .map(node => (node.innerText ?? '').trim())
              .filter(text => /Requerido|obligatorio|mínimo/i.test(text)),
            inputs: Array.from(dialog?.querySelectorAll('input') ?? []).length
          }
        })
        const writes = requests.filter(
          row => row.method === 'POST' && row.path === '/api/procedure-catalog'
        )
        entry.obtained = `diálogo abierto ${form.open} · errores ${JSON.stringify(form.errors)} · POST /api/procedure-catalog ${writes.length}`
        entry.requestStatus = writes.at(-1)?.status ?? null
        entry.detail = {
          errors: form.errors,
          writes: writes.length,
          lists: listCalls(requests).length - before
        }
        entry.verdict =
          form.open && form.errors.length >= 2 && writes.length === 0 ? 'OK' : 'DEFECTO'
        await closeAllDialogs(page)
        return entry
      }
    )

    await step(
      'create-full',
      'Crear un procedimiento con aplicabilidad y una equivalencia',
      'El alta guarda site_type, el código externo y los requisitos, y la fila aparece en la lista',
      async entry => {
        await resetFilters(page)
        await page.locator(UI.catalog.create).click({ timeout: 8000 })
        await page
          .locator('[role="dialog"]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})

        const dialog = page.locator('[role="dialog"]').last()
        const inputs = dialog.locator('input')
        await inputs
          .nth(0)
          .fill(runCode, { timeout: 8000 })
          .catch(() => {})
        await inputs.nth(1).fill(`AUDIT-U8 ${fixtures.token} procedimiento de la caminata`, {
          timeout: 8000
        })
        // Cost and duration are the two numeric fields before the checkboxes.
        await page
          .locator('[role="dialog"] input[type="number"]')
          .nth(0)
          .fill('150', { timeout: 8000 })
          .catch(() => {})
        await page
          .locator('[role="dialog"] input[type="number"]')
          .nth(1)
          .fill('40', { timeout: 8000 })
          .catch(() => {})

        const sitePick = await openSelect(
          page,
          '[role="dialog"] [data-field="site_type"] button',
          'Cuadrante'
        )

        await dialog
          .locator('[data-external-code-add]')
          .click({ timeout: 8000 })
          .catch(() => {})
        const codeInputs = dialog.locator('[data-external-code-row] input')
        const codeInputCount = await codeInputs.count().catch(() => 0)
        if (codeInputCount > 0) {
          await codeInputs
            .nth(0)
            .fill(`AUD-${fixtures.token}-EXT`, { timeout: 8000 })
            .catch(() => {})
        }

        const requirement = dialog.locator('textarea').first()
        await requirement
          .fill(`AUDIT-U8 ${fixtures.token} requisito medido en la caminata`, { timeout: 8000 })
          .catch(() => {})

        const pendingCreate = page
          .waitForResponse(
            response =>
              response.request().method() === 'POST' &&
              new URL(response.url()).pathname === '/api/procedure-catalog',
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await page.locator('[data-form-submit]').first().click({ timeout: 8000 })
        const created = await pendingCreate
        const status = created?.status() ?? null
        const body = created ? await created.json().catch(() => null) : null
        const id = body?.data?.id ?? null

        if (id) registerCreated('procedure', { id, code: runCode, step: 'create-full' })

        const toast = await toastText(page)
        const focused = await focusRow(page, runCode)
        const listed = focused.visible

        entry.obtained = `POST ${status} · id ${id ?? '—'} · site_type ${sitePick.selected ?? '—'} · toast «${toast}» · fila en la lista ${listed}`
        entry.requestStatus = status
        entry.request = {
          method: 'POST',
          path: '/api/procedure-catalog',
          body: body?.data?.code ?? null
        }
        entry.detail = {
          id,
          code: body?.data?.code ?? null,
          siteType: body?.data?.site_type ?? null,
          externalCodes: body?.data?.external_codes ?? null,
          requirements: body?.data?.requirements ?? null,
          sitePick,
          toast
        }
        entry.verdict =
          status === 201 &&
          body?.data?.site_type === 'quadrant' &&
          (body?.data?.external_codes ?? []).length === 1 &&
          listed
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'edit',
      'Editar el procedimiento recién creado y guardar',
      'El PUT guarda el nombre nuevo y la lista lo muestra',
      async entry => {
        const focused = await focusRow(page, runCode)
        if (!focused.visible) {
          entry.obtained = `la fila ${runCode} no aparece al buscarla por su código`
          entry.verdict = 'NO PROBADO'
          entry.reason = 'no se pudo abrir la fila de la sesión'
          return entry
        }
        await focused.row.locator('[data-row-action="edit"]').click({ timeout: 8000 })
        await page
          .locator('[role="dialog"]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})

        const newName = `AUDIT-U8 ${fixtures.token} procedimiento editado`
        await page
          .locator('[role="dialog"] input')
          .nth(1)
          .fill(newName, { timeout: 8000 })
          .catch(() => {})

        const pendingPut = page
          .waitForResponse(
            response =>
              response.request().method() === 'PUT' &&
              /\/api\/procedure-catalog\/\d+$/.test(new URL(response.url()).pathname),
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await page.locator('[data-form-submit]').first().click({ timeout: 8000 })
        const updated = await pendingPut
        const status = updated?.status() ?? null
        const body = updated ? await updated.json().catch(() => null) : null
        const toast = await toastText(page)
        const refocused = await focusRow(page, runCode)
        const listed =
          refocused.visible && (await refocused.row.innerText().catch(() => '')).includes(newName)

        entry.obtained = `PUT ${status} · nombre «${body?.data?.name ?? '—'}» · fila con el nombre nuevo ${listed} · toast «${toast}»`
        entry.requestStatus = status
        entry.request = { method: 'PUT', path: updated ? new URL(updated.url()).pathname : null }
        entry.detail = { toast, listed, name: body?.data?.name ?? null }
        entry.verdict = status === 200 && listed ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'deactivate',
      'Desactivar el procedimiento de la caminata desde la confirmación',
      'La confirmación ejecuta el DELETE, la fila queda inactiva y se ofrece reactivarla',
      async entry => {
        const focused = await focusRow(page, runCode)
        if (!focused.visible) {
          entry.obtained = `la fila ${runCode} no aparece al buscarla por su código`
          entry.verdict = 'NO PROBADO'
          entry.reason = 'no se pudo abrir la fila de la sesión'
          return entry
        }
        await focused.row.locator('[data-row-action="deactivate"]').click({ timeout: 8000 })
        await page
          .locator('[role="alertdialog"]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})
        const confirmText = await page
          .locator('[role="alertdialog"]')
          .first()
          .innerText()
          .catch(() => '')

        const pendingDelete = page
          .waitForResponse(
            response =>
              response.request().method() === 'DELETE' &&
              /\/api\/procedure-catalog\/\d+$/.test(new URL(response.url()).pathname),
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await dialogButton(page, 'Desactivar').click({ timeout: 8000 })
        const deleted = await pendingDelete
        const status = deleted?.status() ?? null
        const toast = await toastText(page)
        await page.waitForTimeout(800)
        await settleCatalog(page)

        await focusRow(page, runCode)
        const rowText = await rowByCode(page, runCode)
          .innerText()
          .catch(() => '')
        entry.obtained = `confirmación «${confirmText.split('\n')[0]}» · DELETE ${status} · fila inactiva ${/Inactivo/.test(rowText)} · toast «${toast}»`
        entry.requestStatus = status
        entry.request = { method: 'DELETE', path: deleted ? new URL(deleted.url()).pathname : null }
        entry.detail = {
          confirmText: confirmText.slice(0, 200),
          rowText: rowText.slice(0, 200),
          toast
        }
        entry.verdict = status === 200 && /Inactivo/.test(rowText) ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'reactivate',
      'Reactivar el procedimiento inactivo',
      'La confirmación ejecuta el PUT is_active y la fila vuelve a «Activo»',
      async entry => {
        const focused = await focusRow(page, runCode)
        if (!focused.visible) {
          entry.obtained = `la fila ${runCode} no aparece al buscarla por su código`
          entry.verdict = 'NO PROBADO'
          entry.reason = 'no se pudo abrir la fila inactiva de la sesión'
          return entry
        }
        await focused.row.locator('[data-row-action="reactivate"]').click({ timeout: 8000 })
        await page
          .locator('[role="alertdialog"]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})

        const pendingPut = page
          .waitForResponse(
            response =>
              response.request().method() === 'PUT' &&
              /\/api\/procedure-catalog\/\d+$/.test(new URL(response.url()).pathname),
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await dialogButton(page, 'Reactivar').click({ timeout: 8000 })
        const updated = await pendingPut
        const status = updated?.status() ?? null
        await focusRow(page, runCode)
        const rowText = await rowByCode(page, runCode)
          .innerText()
          .catch(() => '')

        entry.obtained = `PUT ${status} · fila activa ${/Activo/.test(rowText)}`
        entry.requestStatus = status
        entry.request = { method: 'PUT', path: updated ? new URL(updated.url()).pathname : null }
        entry.detail = { rowText: rowText.slice(0, 200) }
        entry.verdict =
          status === 200 && /Activo/.test(rowText) && !/Inactivo/.test(rowText) ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'duplicate',
      'Duplicar el procedimiento que declara aplicabilidad y equivalencia',
      'La copia conserva aplicabilidad, equivalencias y receta de insumos del original',
      async entry => {
        const focused = await focusRow(page, surface.code)
        if (!focused.visible) {
          entry.obtained = `la fila ${surface.code} no aparece al buscarla por su código`
          entry.verdict = 'NO PROBADO'
          entry.reason = 'no se pudo abrir la fila del procedimiento de la sesión'
          return entry
        }
        await focused.row.locator('[data-row-action="duplicate"]').click({ timeout: 8000 })
        await page
          .locator('[role="dialog"]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})
        await page.waitForTimeout(300)

        const defaultCode = await page
          .locator('[role="dialog"] input')
          .nth(0)
          .inputValue()
          .catch(() => null)

        // First: the code the modal proposes, untouched. The column is
        // varchar(20) while the request allows 50 characters, so the proposed
        // `${code}-COPIA` overflows and the API answers 500 instead of 422.
        const pendingDefault = page
          .waitForResponse(
            response =>
              response.request().method() === 'POST' &&
              new URL(response.url()).pathname === '/api/procedure-catalog',
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await page
          .locator('[role="dialog"] button')
          .filter({ hasText: 'Duplicar' })
          .last()
          .click({ timeout: 8000 })
        const defaultResponse = await pendingDefault
        const defaultStatus = defaultResponse?.status() ?? null
        const defaultBody = defaultResponse ? await defaultResponse.json().catch(() => null) : null
        const defaultToast = await toastText(page)

        // Second: a code that fits the column, so the field comparison below has
        // a copy to read.
        const shortCode = `A8-${fixtures.token.slice(-4)}-C`
        const codeField = page.locator('[role="dialog"] input').nth(0)
        const stillOpen = await codeField.isVisible().catch(() => false)
        if (stillOpen) {
          await codeField.fill(shortCode, { timeout: 8000 }).catch(() => {})
        }

        const pendingCreate = page
          .waitForResponse(
            response =>
              response.request().method() === 'POST' &&
              new URL(response.url()).pathname === '/api/procedure-catalog',
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await page
          .locator('[role="dialog"] button')
          .filter({ hasText: 'Duplicar' })
          .last()
          .click({ timeout: 8000 })
          .catch(() => {})
        const created = await pendingCreate
        const status = created?.status() ?? null
        const body = created ? await created.json().catch(() => null) : null
        const copyId = body?.data?.id ?? null
        if (copyId) {
          registerCreated('procedure', {
            id: copyId,
            code: body?.data?.code ?? shortCode,
            step: 'duplicate'
          })
        }

        const [source, copy] = await Promise.all([
          call(request, session, 'GET', `/api/procedure-catalog/${surface.id}`),
          copyId
            ? call(request, session, 'GET', `/api/procedure-catalog/${copyId}`)
            : Promise.resolve({ body: null, status: null })
        ])

        const sourceData = source.body?.data ?? null
        const copyData = copy.body?.data ?? null
        const comparison = {
          siteType: {
            source: sourceData?.site_type ?? null,
            copy: copyData?.site_type ?? null,
            equal: sourceData?.site_type === copyData?.site_type
          },
          externalCodes: {
            source: (sourceData?.external_codes ?? []).length,
            copy: (copyData?.external_codes ?? []).length,
            equal:
              (sourceData?.external_codes ?? []).length === (copyData?.external_codes ?? []).length
          },
          materialRecipe: {
            source: (sourceData?.material_recipe ?? []).length,
            copy: (copyData?.material_recipe ?? []).length,
            equal:
              (sourceData?.material_recipe ?? []).length ===
              (copyData?.material_recipe ?? []).length,
            comparable: (sourceData?.material_recipe ?? []).length > 0
          },
          requirements: {
            source: sourceData?.requirements ?? null,
            copy: copyData?.requirements ?? null,
            equal: (sourceData?.requirements ?? null) === (copyData?.requirements ?? null)
          }
        }
        const lost = ['siteType', 'externalCodes']
          .concat(comparison.materialRecipe.comparable ? ['materialRecipe'] : [])
          .filter(key => !comparison[key].equal)

        entry.obtained = `código propuesto «${defaultCode}» → POST ${defaultStatus} (${defaultBody?.message ?? '—'}) · copia «${copyData?.code ?? '—'}» POST ${status} · site_type ${comparison.siteType.source} → ${comparison.siteType.copy} · equivalencias ${comparison.externalCodes.source} → ${comparison.externalCodes.copy} · receta ${comparison.materialRecipe.source} → ${comparison.materialRecipe.copy} · requisitos ${comparison.requirements.equal ? 'copiados' : 'perdidos'}`
        entry.requestStatus = status ?? defaultStatus
        entry.request = { method: 'POST', path: '/api/procedure-catalog', copyId }
        entry.detail = {
          default: {
            code: defaultCode,
            codeLength: String(defaultCode ?? '').length,
            status: defaultStatus,
            message: defaultBody?.message ?? null,
            toast: defaultToast,
            cause:
              'procedure_catalog.code es varchar(20) (database/migrations/2025_10_24_202849_create_procedure_catalog_table.php:16) mientras StoreProcedureCatalogRequest permite max:50'
          },
          copyId,
          copyCode: copyData?.code ?? null,
          comparison,
          payloadOmits: ['site_type', 'external_codes', 'material_recipe'],
          source:
            'resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue (submitDuplicate copia una lista fija de campos)'
        }
        entry.verdict = lost.length === 0 && defaultStatus !== 500 ? 'OK' : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : `${defaultStatus === 500 ? 'el código propuesto desborda la columna (500); ' : ''}la copia pierde ${lost.join(', ')}`
        return entry
      }
    )

    await step(
      'import-empty',
      'Abrir «Importar CSV» y pulsar «Importar» sin archivo',
      'El importador no dispara ninguna petición y el botón no está disponible',
      async entry => {
        const before = requests.filter(
          row => row.method === 'POST' && row.path === '/api/admin/procedure-catalog/import'
        ).length

        const opened = await page
          .locator(UI.catalog.import)
          .click({ timeout: 7000 })
          .then(() => true)
          .catch(() => false)
        const dropzone = await page
          .locator('[data-import-dropzone]')
          .waitFor({ state: 'visible', timeout: 8000 })
          .then(() => true)
          .catch(() => false)
        if (!opened || !dropzone) {
          entry.obtained = `«Importar CSV» abrible ${opened} · zona de arrastre visible ${dropzone}`
          entry.detail = { opened, dropzone }
          entry.verdict = 'NO PROBADO'
          entry.reason = 'la cabecera no abre el importador en este estado de la página'
          return entry
        }

        const importDialog = page
          .locator('[role="dialog"]')
          .filter({ has: page.locator('[data-import-dropzone]') })
          .last()
        const importButton = importDialog
          .locator('button')
          .filter({ hasText: /^Importar$/ })
          .first()
        const footer = await importDialog
          .evaluate(dialog =>
            Array.from(dialog.querySelectorAll('button')).map(button => ({
              text: (button.innerText ?? '').trim(),
              disabled: button.disabled
            }))
          )
          .catch(() => null)
        const disabled = await importButton.isDisabled({ timeout: 4000 }).catch(() => null)
        const diagnostics = await importDialog
          .evaluate(dialog => ({
            role: dialog.getAttribute('role'),
            footer: dialog.querySelector('.modal-footer')?.innerText ?? null,
            buttons: Array.from(dialog.querySelectorAll('button')).map(button => ({
              text: (button.innerText ?? '').trim(),
              aria: button.getAttribute('aria-label'),
              html: button.outerHTML.slice(0, 120)
            })),
            text: (dialog.innerText ?? '').slice(-200)
          }))
          .catch(thrown => ({ error: String(thrown?.message ?? thrown).slice(0, 200) }))
        const importControl = footer?.find(button => button.text === 'Importar') ?? null
        await page.waitForTimeout(400)

        const after = requests.filter(
          row => row.method === 'POST' && row.path === '/api/admin/procedure-catalog/import'
        ).length
        const result = await exists(page, '[data-import-result]')

        entry.obtained = `botones del pie ${JSON.stringify(footer)} · POST import ${before} → ${after} · resultado pintado ${result}`
        entry.requestStatus = after > before ? (requests.at(-1)?.status ?? null) : null
        entry.detail = { footer, importControl, disabled, diagnostics, posts: after, result }
        const disabledFromDom = importControl?.disabled ?? null
        entry.verdict =
          disabledFromDom === null
            ? 'NO PROBADO'
            : disabledFromDom === true && after === before && !result
              ? 'OK'
              : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : disabledFromDom === null
              ? 'no se encontró el botón «Importar» en el pie del importador'
              : 'el importador sin archivo permite importar o dispara una petición'
        await closeAllDialogs(page)
        return entry
      }
    )

    await step(
      'list-vs-stats',
      'Comparar la tabla del catálogo con la tabla de estadísticas',
      'Las dos listas del módulo comparten paginación, vacío y affordance de scroll',
      async entry => {
        const catalogData = await readCatalog(page)
        await page.goto(STATS_ROUTE)
        await settleStats(page)
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        const statsData = await readStats(page)

        const comparison = {
          catalog: {
            primitive: catalogData.tables.dataTable > 0 ? 'UiDataTable' : 'tabla propia',
            nativeTables: catalogData.tables.native,
            dataTables: catalogData.tables.dataTable,
            scrollHooks: catalogData.scrollable.map(entry => entry.hook),
            affordance: catalogData.scrollable.some(entry => entry.affordance),
            paginationLabels: catalogData.pagination.labels.length
          },
          stats: {
            dataTables: statsData.scroll.dataTable,
            affordance: statsData.scroll.affordance,
            scrollWidth: statsData.scroll.scrollWidth,
            clientWidth: statsData.scroll.clientWidth
          }
        }
        const samePrimitive = comparison.catalog.dataTables > 0 && comparison.stats.dataTables > 0
        entry.obtained = `catálogo: ${comparison.catalog.primitive} (data-table-scroll ${comparison.catalog.dataTables}, affordance ${comparison.catalog.affordance}) · estadísticas: UiDataTable (data-table-scroll ${comparison.stats.dataTables}, affordance ${comparison.stats.affordance})`
        entry.detail = comparison
        entry.verdict = samePrimitive ? 'OK' : 'DEFECTO'
        entry.reason = samePrimitive
          ? null
          : 'la lista del catálogo no usa la primitiva UiDataTable que sí monta estadísticas'
        return entry
      }
    )

    await step(
      'reception-search',
      'Escribir el código del procedimiento en la búsqueda de recepción',
      'La consulta de recepción filtra por el término escrito',
      async entry => {
        await page.goto(RECEPTION_ROUTE)
        await settleReception(page)
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        const loaded = await page
          .locator(UI.reception.cards)
          .first()
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const before = await readReception(page)
        if (!loaded || before.cardCount === 0) {
          entry.obtained = `tarjetas visibles ${before.cardCount} · estado de error ${JSON.stringify(before.error)}`
          entry.detail = { loaded, before }
          entry.verdict = 'NO PROBADO'
          entry.reason = 'la consulta de recepción no pintó tarjetas que comparar'
          return entry
        }

        const responsePromise = page
          .waitForResponse(
            candidate => {
              if (candidate.request().method() !== 'GET') return false
              try {
                return new URL(candidate.url()).pathname === '/api/procedure-catalog'
              } catch {
                return false
              }
            },
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await fillControl(page, UI.reception.search, surface.code)
        const response = await responsePromise
        await page.waitForTimeout(1200)
        await settleReception(page)
        const after = await readReception(page)
        const call = lastListCall(requests)
        const query = call?.query ?? {}
        const unchanged = after.cardCount === before.cardCount && after.codes[0] === before.codes[0]

        entry.obtained = `petición ${JSON.stringify(query)} · tarjetas ${before.cardCount} → ${after.cardCount} · primera tarjeta ${before.codes[0]} → ${after.codes[0]} · el conjunto ${unchanged ? 'no cambió' : 'cambió'}`
        entry.requestStatus = response?.status() ?? call?.status ?? null
        entry.request = { method: 'GET', path: '/api/procedure-catalog', query }
        entry.detail = {
          query,
          before: { count: before.cardCount, first: before.codes[0] },
          after: { count: after.cardCount, first: after.codes[0] },
          sendsSearch: 'search' in query,
          controllerReads: 'q',
          cause:
            'resources/js/modules/reception-procedures/ReceptionProceduresPage.vue:227 (filters.search) mientras ProcedureCatalogController@index lee sólo q (app/Http/Controllers/Api/ProcedureCatalogController.php:29-37)'
        }
        entry.verdict = unchanged && 'search' in query ? 'DEFECTO' : 'OK'
        entry.reason =
          entry.verdict === 'DEFECTO'
            ? 'la búsqueda de recepción envía search y el índice sólo filtra por q: la lista no cambia'
            : null
        return entry
      }
    )

    await step(
      'mobile-table',
      'Medir la tabla del catálogo a 390 px',
      'El documento no desborda y la tabla se desplaza dentro de su marco',
      async entry => {
        await page.setViewportSize(MOBILE)
        await page.goto(CATALOG_ROUTE)
        await settleCatalog(page)
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        const measure = await page.evaluate(() => {
          const wrapper = document.querySelector('main .overflow-x-auto')
          return {
            documentOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
            scrollWidth: document.documentElement.scrollWidth,
            innerWidth: window.innerWidth,
            wrapper: wrapper
              ? {
                  clientWidth: wrapper.clientWidth,
                  scrollWidth: wrapper.scrollWidth,
                  affordance: Boolean(wrapper.querySelector('[data-table-scroll-affordance]')),
                  hint: Boolean(wrapper.querySelector('[data-table-scroll-hint]'))
                }
              : null,
            rows: document.querySelectorAll('[data-procedure-row]').length
          }
        })
        entry.obtained = `desborde del documento ${measure.documentOverflow} (${measure.scrollWidth}/${measure.innerWidth}) · marco ${measure.wrapper?.clientWidth}/${measure.wrapper?.scrollWidth} · affordance ${measure.wrapper?.affordance} · filas ${measure.rows}`
        entry.detail = measure
        entry.verdict =
          measure.documentOverflow === false && (measure.wrapper?.scrollWidth ?? 0) > 0
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )
  } finally {
    // Evidence first: a timeout can close the page, and the cleanup below then
    // throws before the session would have written what it measured.
    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.consoleClassified = classifyConsole(diagnostics.consoleErrors, 'admin', [
      {
        pattern: /Failed to load resource: .*500/,
        reason:
          'the duplicate probe sends the 25-character code the modal proposes: the 500 is U8-F06, provoked and declared'
      }
    ])
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    evidence.writes = requests
      .filter(entry => entry.method !== 'GET')
      .map(entry => ({ method: entry.method, path: entry.path, status: entry.status }))
    markExpected(
      evidence.failedRequests,
      failure => /\/api\/procedure-catalog\?.*search=/.test(failure.url ?? ''),
      'the reception consult sends `search`; the controller ignores it, not the request'
    )
    markExpected(
      evidence.failedRequests,
      failure => failure.status === 500 && /\/api\/procedure-catalog$/.test(failure.url ?? ''),
      'the duplicate probe submits the code the modal proposes (25 chars) on purpose: the 500 is U8-F06, measured and declared'
    )
    evidence.totals = walkTotals(evidence)
    audit('walk-catalog.json', evidence)

    await closeAllDialogs(page).catch(() => {})
    await page.setViewportSize(DESKTOP).catch(() => {})
  }

  expect(evidence.steps.length, 'catalogue walkthrough steps').toBe(18)
  expect(evidence.steps.filter(stepEntry => stepEntry.shot).length, 'steps with a capture').toBe(
    evidence.steps.length
  )
  expect(evidence.pageErrors, 'page errors during the catalogue walkthrough').toEqual([])
  expect(evidence.totals.notProbed, 'steps that could not be probed').toBe(0)
})
