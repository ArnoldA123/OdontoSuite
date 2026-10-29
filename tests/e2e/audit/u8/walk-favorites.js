// Plan #13 (issue #371, U8) — layer 4 on `/my-procedures` and the reception desk.
//
// The clinical desk marks, orders and removes favourites; the reception consult
// reads the catalogue and must not edit it. The issue asks for both halves of
// that sentence, so the walk measures the desk as the clinician who owns it and
// then measures the reception role twice: its own surface (no write control) and
// the catalogue list, where the write affordances are rendered even though the
// API answers 403.
//
// Every write goes through the product doors and is retired at teardown.

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
  MY_PROCEDURES_ROUTE,
  RECEPTION_ROUTE,
  THRESHOLD,
  UI,
  audit,
  call,
  classifyConsole,
  closeAllDialogs,
  fillControl,
  readCatalog,
  readFavorites,
  readReception,
  readShell,
  recorder,
  settleCatalog,
  settleFavorites,
  settleReception,
  stamp,
  walkTotals,
  watchRequests
} from './helpers.js'

const explore = async (page, term) => {
  await fillControl(page, UI.favorites.exploreSearch, term)
  await page
    .waitForFunction(
      () =>
        !/Buscando procedimientos/.test(document.querySelector('main')?.innerText ?? '') &&
        Boolean(document.querySelector('[data-explorer-row], [data-explorer-error]')),
      undefined,
      { timeout: LOAD_TIMEOUT, polling: 120 }
    )
    .catch(() => {})
  return readFavorites(page)
}

test('@U8 · capa 4 — los favoritos clínicos y el escritorio de recepción', async ({
  page,
  request
}) => {
  test.setTimeout(300000)

  const odontologo = await loginAs(request, 'odontologo')
  const recepcionista = await loginAs(request, 'recepcionista')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const { surface } = fixtures.procedures
  const { delta } = fixtures.procedures
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const state = { pageErrors: diagnostics.pageErrors, currentStep: null }
  const evidence = {
    ...stamp(),
    route: MY_PROCEDURES_ROUTE,
    role: 'odontologo',
    shotDir: 'walk/favorites',
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
    page.setDefaultTimeout(7000)
    await primeSession(page, odontologo)
    await page.goto(MY_PROCEDURES_ROUTE)
    await settleFavorites(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    await step(
      'favorites-initial',
      'Abrir /my-procedures como odontología',
      'La lista abre con los favoritos de la sesión en su orden y con sugerencias para el resto',
      async entry => {
        const data = await readFavorites(page)
        const call = requests.filter(row => row.path === '/api/procedure-catalog-favorites').at(-1)
        entry.obtained = `contador «${data.count}» · favoritos ${JSON.stringify(data.favoriteCodes)} · sugerencias ${data.suggestions.length} · error ${Boolean(data.error)}`
        entry.requestStatus = call?.status ?? null
        entry.detail = { favorites: data.favorites, count: data.count, order: data.favoriteCodes }
        entry.verdict =
          data.favoriteCodes[0] === surface.code &&
          data.favoriteCodes.includes(fixtures.procedures.beta.code)
            ? 'OK'
            : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : `los favoritos de la sesión no aparecen en el orden creado (${JSON.stringify(data.favoriteCodes)})`
        return entry
      }
    )

    await step(
      'favorite-add',
      'Buscar en «Explorar catálogo» y marcar un procedimiento como favorito',
      'El explorador devuelve la fila y el alta del favorito se confirma con su toast',
      async entry => {
        const before = await readFavorites(page)
        const results = await explore(page, delta.code)
        const row = results.explorer.rows.find(candidate => candidate.code === delta.code) ?? null
        entry.obtained = `explorador ${results.explorer.rows.length} fila(s) ${JSON.stringify(results.explorer.rows.map(candidate => candidate.code))} · antes ${before.favoriteCodes.length}`
        entry.detail = { before: before.favoriteCodes, explorer: results.explorer.rows }

        if (!row) {
          entry.verdict = 'DEFECTO'
          entry.reason =
            'el explorador no encuentra por código un procedimiento activo de la sesión'
          return entry
        }

        const pending = page
          .waitForResponse(
            response =>
              response.request().method() === 'POST' &&
              /\/api\/procedure-catalog\/\d+\/favorite$/.test(new URL(response.url()).pathname),
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await page
          .locator(UI.favorites.exploreRows)
          .filter({ hasText: delta.code })
          .first()
          .locator('button')
          .first()
          .click({ timeout: 8000 })
        const response = await pending
        const status = response?.status() ?? null
        if (status === 201) {
          registerCreated('favorite', {
            role: 'odontologo',
            procedureId: delta.id,
            step: 'favorite-add'
          })
        }
        await page.waitForTimeout(700)
        const after = await readFavorites(page)

        entry.obtained = `POST ${status} · favoritos ${before.favoriteCodes.length} → ${after.favoriteCodes.length} · contador «${after.count}» · incluye ${delta.code}: ${after.favoriteCodes.includes(delta.code)}`
        entry.requestStatus = status
        entry.request = { method: 'POST', path: response ? new URL(response.url()).pathname : null }
        entry.detail = { after: after.favoriteCodes, explorerAfter: after.explorer.rows }
        entry.verdict =
          status === 201 && after.favoriteCodes.includes(delta.code) ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'favorites-persist',
      'Recargar /my-procedures',
      'El favorito recién marcado sigue en la lista después de recargar',
      async entry => {
        await page.reload()
        await settleFavorites(page)
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        const data = await readFavorites(page)
        entry.obtained = `favoritos ${JSON.stringify(data.favoriteCodes)} · contador «${data.count}»`
        entry.requestStatus =
          requests.filter(row => row.path === '/api/procedure-catalog-favorites').at(-1)?.status ??
          null
        entry.detail = { favorites: data.favoriteCodes }
        entry.verdict = data.favoriteCodes.includes(delta.code) ? 'OK' : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : 'el favorito no sobrevive a la recarga: la lista no lo lee del servidor'
        return entry
      }
    )

    await step(
      'favorite-reorder',
      'Bajar el primer favorito y recargar',
      'El reordenamiento se guarda y la lista recargada conserva el orden nuevo',
      async entry => {
        const before = await readFavorites(page)
        const firstBefore = before.favoriteCodes[0]

        const pending = page
          .waitForResponse(
            response =>
              response.request().method() === 'PUT' &&
              new URL(response.url()).pathname === '/api/procedure-catalog-favorites/reorder',
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await page
          .locator(UI.favorites.favorites)
          .first()
          .locator(UI.favorites.moveDown)
          .click({ timeout: 8000 })
        const response = await pending
        const status = response?.status() ?? null
        await page.waitForTimeout(400)

        await page.reload()
        await settleFavorites(page)
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        const after = await readFavorites(page)

        entry.obtained = `PUT ${status} · orden ${JSON.stringify(before.favoriteCodes)} → ${JSON.stringify(after.favoriteCodes)}`
        entry.requestStatus = status
        entry.request = { method: 'PUT', path: '/api/procedure-catalog-favorites/reorder' }
        entry.detail = { before: before.favoriteCodes, after: after.favoriteCodes, firstBefore }
        entry.verdict =
          status === 200 &&
          after.favoriteCodes[0] !== firstBefore &&
          after.favoriteCodes.indexOf(firstBefore) === 1
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'favorite-remove',
      'Quitar de favoritos el procedimiento agregado',
      'La baja se confirma y la lista y su contador bajan en uno',
      async entry => {
        const before = await readFavorites(page)
        const target = page.locator(UI.favorites.favorites).filter({ hasText: delta.code }).first()
        await target.locator(UI.favorites.remove).click({ timeout: 8000 })
        await page.waitForTimeout(800)
        const after = await readFavorites(page)

        entry.obtained = `favoritos ${before.favoriteCodes.length} → ${after.favoriteCodes.length} · queda ${delta.code}: ${after.favoriteCodes.includes(delta.code)}`
        entry.requestStatus =
          requests
            .filter(
              row =>
                row.method === 'DELETE' && /\/api\/procedure-catalog\/\d+\/favorite$/.test(row.path)
            )
            .at(-1)?.status ?? null
        entry.detail = { before: before.favoriteCodes, after: after.favoriteCodes }
        entry.verdict = !after.favoriteCodes.includes(delta.code) ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'favorite-duplicate',
      'Marcar dos veces el mismo procedimiento como favorito por la puerta de la API',
      'El servidor no duplica la fila del favorito',
      async entry => {
        const first = await call(
          request,
          odontologo,
          'POST',
          `/api/procedure-catalog/${delta.id}/favorite`
        )
        const second = await call(
          request,
          odontologo,
          'POST',
          `/api/procedure-catalog/${delta.id}/favorite`
        )
        const list = await call(request, odontologo, 'GET', '/api/procedure-catalog-favorites')
        const rows = (list.body?.data ?? []).filter(row => row.id === delta.id)
        registerCreated('favorite', {
          role: 'odontologo',
          procedureId: delta.id,
          step: 'favorite-duplicate'
        })

        entry.obtained = `POST ${first.status} y ${second.status} · filas del procedimiento en la lista ${rows.length}`
        entry.requestStatus = second.status
        entry.request = { method: 'POST', path: `/api/procedure-catalog/${delta.id}/favorite` }
        entry.detail = { statuses: [first.status, second.status], duplicates: rows.length }
        entry.verdict = rows.length <= 1 ? 'OK' : 'DEFECTO'
        entry.reason = rows.length <= 1 ? null : 'la puerta del favorito permite filas duplicadas'
        return entry
      }
    )

    // --- the reception desk ------------------------------------------------
    await step(
      'reception-read',
      'Abrir /reception-procedures como recepción',
      'La consulta pinta sus tarjetas y no ofrece ningún control de escritura',
      async entry => {
        // The session changes here: the desk walks ran as odontología, and the
        // reception rows must be measured with reception's own token.
        await primeSession(page, recepcionista)
        await page.goto(RECEPTION_ROUTE)
        await settleReception(page)
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        const loaded = await page
          .locator(UI.reception.cards)
          .first()
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const data = await readReception(page)
        const shell = await readShell(page)

        entry.obtained = `tarjetas ${data.cardCount} · controles de escritura ${JSON.stringify(data.writeControls)} · formularios ${data.forms} · búsqueda ${data.search.present} · filas ocultas para el rol ${JSON.stringify(shell.sidebar.filter(link => /my-procedures/.test(link.href)).length)}`
        entry.requestStatus =
          requests.filter(row => row.path === '/api/procedure-catalog').at(-1)?.status ?? null
        entry.detail = {
          loaded,
          cards: data.cardCount,
          writeControls: data.writeControls,
          forms: data.forms,
          buttons: data.buttons.map(button => button.label)
        }
        entry.verdict =
          loaded && data.cardCount > 0 && data.writeControls.length === 0 && data.forms === 0
            ? 'OK'
            : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : `la consulta de recepción ofrece controles de escritura: ${JSON.stringify(data.writeControls)}`
        return entry
      }
    )

    await step(
      'reception-write-gate',
      'Intentar las escrituras del catálogo con el token de recepción',
      'Las puertas de escritura responden 403 a recepción',
      async entry => {
        const attempts = [
          ['POST', '/api/procedure-catalog', { code: 'AUD-U8-GATE', name: 'gate' }],
          ['PUT', `/api/procedure-catalog/${surface.id}`, { name: surface.name }],
          ['DELETE', `/api/procedure-catalog/${surface.id}`],
          ['POST', `/api/procedure-catalog/${surface.id}/favorite`],
          ['DELETE', `/api/procedure-catalog/${surface.id}/favorite`],
          ['PUT', '/api/procedure-catalog-favorites/reorder', { ids: [surface.id] }],
          ['PUT', '/api/branches/1/procedure-prices', { items: [] }],
          ['DELETE', '/api/branches/1/procedure-prices/999999999'],
          ['POST', '/api/admin/procedure-catalog/import', {}]
        ]

        const observed = []

        for (const [method, path, body] of attempts) {
          const response = await call(request, recepcionista, method, path, body)
          observed.push({ method, path, status: response.status })
        }

        const failures = observed.filter(row => row.status !== 403)
        entry.obtained = `${observed.length} puertas · ${failures.length} fuera del 403 ${JSON.stringify(failures)}`
        entry.requestStatus = 403
        entry.detail = { observed }
        entry.verdict = failures.length === 0 ? 'OK' : 'DEFECTO'
        entry.reason =
          failures.length === 0 ? null : `recepción alcanza ${JSON.stringify(failures)}`
        return entry
      }
    )

    await step(
      'catalog-writes-as-reception',
      'Abrir /procedure-catalog como recepción',
      'La lista no ofrece escrituras al rol que la API rechaza',
      async entry => {
        await page.goto(CATALOG_ROUTE)
        await settleCatalog(page)
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        const data = await readCatalog(page)
        const affordances = [
          data.createVisible ? 'Nuevo procedimiento' : null,
          data.importVisible ? 'Importar CSV' : null,
          data.statsVisible ? 'Estadísticas' : null,
          ...data.rowActions.map(action => `fila:${action}`)
        ].filter(Boolean)

        entry.obtained = `${data.rowCount} filas · controles de escritura visibles ${JSON.stringify(affordances)}`
        entry.requestStatus =
          requests.filter(row => row.path === '/api/procedure-catalog').at(-1)?.status ?? null
        entry.detail = {
          affordances,
          rowActions: data.rowActions,
          createVisible: data.createVisible
        }
        // `detail` opens the procedure and stays available: the API refuses the
        // writes, not the read (#501).
        const writeActions = data.rowActions.filter(action => action !== 'detail')
        entry.verdict =
          !data.createVisible && !data.importVisible && writeActions.length === 0 ? 'OK' : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : `la lista ofrece ${JSON.stringify(writeActions)} a un rol cuya escritura responde 403`
        return entry
      }
    )

    await step(
      'role-gate-nav',
      'Leer la navegación de cada rol',
      'Recepción ve la consulta y no los escritorios del catálogo; odontología ve sus favoritos',
      async entry => {
        const shellReception = await readShell(page)
        await primeSession(page, odontologo)
        await page.goto(MY_PROCEDURES_ROUTE)
        await settleFavorites(page)
        const shellOdontologo = await readShell(page)

        const hrefs = shell => shell.sidebar.map(link => link.href)
        const reception = hrefs(shellReception)
        const odontologoHrefs = hrefs(shellOdontologo)

        entry.obtained = `recepción ${JSON.stringify(reception.filter(href => /procedure/.test(href)))} · odontología ${JSON.stringify(odontologoHrefs.filter(href => /procedure/.test(href)))}`
        entry.detail = { reception, odontologo: odontologoHrefs }
        entry.verdict =
          reception.includes(RECEPTION_ROUTE) &&
          !reception.includes(MY_PROCEDURES_ROUTE) &&
          !reception.includes(CATALOG_ROUTE) &&
          odontologoHrefs.includes(MY_PROCEDURES_ROUTE) &&
          !odontologoHrefs.includes(CATALOG_ROUTE)
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )
  } finally {
    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.consoleClassified = classifyConsole(diagnostics.consoleErrors)
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    evidence.totals = walkTotals(evidence)
    audit('walk-favorites.json', evidence)

    await closeAllDialogs(page).catch(() => {})
  }

  expect(evidence.steps.length, 'favourites walkthrough steps').toBe(10)
  expect(evidence.pageErrors, 'page errors during the favourites walkthrough').toEqual([])
  expect(evidence.totals.notProbed, 'steps that could not be probed').toBe(0)
})
