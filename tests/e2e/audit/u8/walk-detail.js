// Plan #13 (issue #371, U8) — layers 4 and 5 on `/procedure-catalog/:id`.
//
// Two walks, because the surface has two audiences:
//  * as administración: the identity block, the applicability and the public
//    codes, the four tabs (Datos, Historial, Versiones, Tarifario) and the
//    material recipe question;
//  * as the session's branch administrator: the tariff with a validity window,
//    the inline rejection of an inverted window, the retirement, and the S12
//    contract — whether the branch price reaches the shared
//    `ProcedureCatalogPicker` and whether the picker can search a code that
//    exists outside its first page (#416).
//
// The branch actor is the fixture account: `ProcedurePriceResolver` reads the
// sede of the authenticated user, and every seeded user has `branch_id` NULL.

import { test, expect } from 'playwright/test'

import {
  classifyFailures,
  collectDiagnostics,
  loginAs,
  primeSession,
  settleSurface
} from '../harness.js'

import { branchCredential, ensureFixtures, fixtureSummary, registerCreated } from './fixtures.js'

import {
  CATALOG_ROUTE,
  DESKTOP,
  LOAD_TIMEOUT,
  QUOTATIONS_ROUTE,
  THRESHOLD,
  UI,
  WIDE_CATALOG_CODE,
  addDaysLocal,
  audit,
  call,
  classifyConsole,
  closeAllDialogs,
  fillControl,
  localDay,
  loginCredential,
  readDetail,
  readPicker,
  readTariff,
  recorder,
  settleCatalogDetail,
  settleQuotations,
  stamp,
  toastText,
  walkTotals,
  watchRequests
} from './helpers.js'

const openTab = async (page, label) => {
  const tab = page.locator('[role="tab"]').filter({ hasText: label }).first()

  if ((await tab.count().catch(() => 0)) === 0) return { ok: false, reason: `no tab ${label}` }

  await tab.click({ timeout: 8000 }).catch(() => {})
  await page.waitForTimeout(350)

  return { ok: true }
}

const tariffRow = (page, text) =>
  page.locator(UI.detail.tariffRows).filter({ hasText: text }).first()

const dialogButton = (page, label) =>
  page.locator('[role="alertdialog"] button').filter({ hasText: label }).last()

test('@U8 · capa 4 — el detalle del procedimiento (Datos, Historial, Versiones, Tarifario)', async ({
  page,
  request
}) => {
  test.setTimeout(240000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const { surface } = fixtures.procedures
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const state = { pageErrors: diagnostics.pageErrors, currentStep: null }
  const evidence = {
    ...stamp(),
    route: `${CATALOG_ROUTE}/:id`,
    role: 'administrador',
    shotDir: 'walk/detail',
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
    await primeSession(page, session)
    await page.goto(`${CATALOG_ROUTE}/${surface.id}`)
    await settleCatalogDetail(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    await step(
      'detail-data',
      'Abrir el detalle del procedimiento de la sesión',
      'La cabecera y el bloque de datos publican el código, la aplicabilidad y las equivalencias',
      async entry => {
        const data = await readDetail(page)
        const shell = await page.evaluate(() => ({
          h1: Array.from(document.querySelectorAll('h1')).map(node => (node.innerText ?? '').trim())
        }))
        entry.obtained = `título «${data.title}» · código ${data.fields.code} · aplicabilidad «${data.fields.siteType}» · equivalencias ${JSON.stringify(data.externalCodes.map(code => code.code))} · h1 ${JSON.stringify(shell.h1)}`
        entry.requestStatus =
          requests.find(row => row.path === `/api/procedure-catalog/${surface.id}`)?.status ?? null
        entry.detail = { fields: data.fields, externalCodes: data.externalCodes, h1: shell.h1 }
        entry.verdict =
          data.title === surface.name &&
          data.fields.code === surface.code &&
          data.fields.siteType === 'Superficie dental' &&
          data.externalCodes.some(row => row.code === '997301')
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'tabs',
      'Leer la tira de pestañas del detalle',
      'El detalle declara Datos, Historial, Versiones y Tarifario',
      async entry => {
        const data = await readDetail(page)
        const labels = data.tabs.map(tab => tab.label)
        entry.obtained = `pestañas ${JSON.stringify(labels)} · activa «${data.activeTab}»`
        entry.detail = { labels, groups: data.groups }
        entry.verdict =
          labels.length === 4 &&
          ['Datos', 'Historial', 'Versiones', 'Tarifario'].every(label => labels.includes(label))
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'tab-audit',
      'Abrir la pestaña «Historial»',
      'El alta del procedimiento dejó un registro de auditoría legible',
      async entry => {
        const opened = await openTab(page, 'Historial')
        await page
          .locator(UI.detail.auditRows)
          .first()
          .waitFor({ state: 'visible', timeout: 5000 })
          .catch(() => {})
        const refreshed = await readDetail(page)
        const auditCall = requests.filter(row => row.path === '/api/audit-logs').at(-1) ?? null
        const sentType = auditCall?.query?.model_type ?? null

        // The same query, with the class name the API actually stored.
        const canonical = await call(
          request,
          session,
          'GET',
          `/api/audit-logs?model_type=${encodeURIComponent('App\\Models\\ProcedureCatalog')}` +
            `&auditable_id=${surface.id}&per_page=50`
        )

        entry.obtained = `pestaña ${opened.ok} · filas ${refreshed.audit.rows} · vacío ${JSON.stringify(refreshed.audit.empty)} · la página pide model_type=${JSON.stringify(sentType)} · la misma consulta con el nombre real devuelve ${(canonical.body?.data ?? []).length} fila(s)`
        entry.requestStatus = auditCall?.status ?? null
        entry.request = {
          method: 'GET',
          path: '/api/audit-logs',
          query: auditCall?.query ?? null
        }
        entry.detail = {
          opened,
          audit: refreshed.audit,
          sentModelType: sentType,
          canonicalTotal: (canonical.body?.data ?? []).length,
          canonicalActions: (canonical.body?.data ?? []).map(row => row.action),
          cause:
            "resources/js/modules/procedure-catalog/ProcedureCatalogDetailPage.vue:loadAudit envía model_type: 'App\\\\Models\\\\ProcedureCatalog' (doble barra): la clase guardada es App\\Models\\ProcedureCatalog y el filtro no coincide"
        }
        entry.verdict =
          refreshed.audit.rows > 0
            ? 'OK'
            : refreshed.audit.errorText
              ? 'DEFECTO'
              : (canonical.body?.data ?? []).length > 0
                ? 'DEFECTO'
                : 'CONFUSO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : `el historial se declara vacío mientras la API tiene ${(canonical.body?.data ?? []).length} registro(s) del procedimiento: el model_type viaja con doble barra`
        return entry
      }
    )

    await step(
      'tab-versions',
      'Abrir la pestaña «Versiones»',
      'El alta dejó un snapshot de versión del procedimiento',
      async entry => {
        const opened = await openTab(page, 'Versiones')
        await page.waitForTimeout(500)
        const data = await readDetail(page)
        entry.obtained = `pestaña abierta ${opened.ok} · filas ${data.versions.rows} · vacío ${JSON.stringify(data.versions.empty)} · error ${data.versions.errorText}`
        entry.requestStatus =
          requests
            .filter(row => row.path === `/api/procedure-catalog/${surface.id}/versions`)
            .at(-1)?.status ?? null
        entry.detail = { opened, versions: data.versions }
        entry.verdict = data.versions.rows > 0 ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'material-recipe',
      'Buscar la receta de insumos del procedimiento en el detalle',
      'La receta que la API crea y publica tiene una superficie donde se lee y se edita',
      async entry => {
        const data = await readDetail(page)
        const body = await page.evaluate(() => document.querySelector('main')?.innerText ?? '')
        const api = await call(request, session, 'GET', `/api/procedure-catalog/${surface.id}`)
        entry.obtained = `la página menciona «receta/insumo» ${/receta|insumo/i.test(body)} · bloques ${JSON.stringify(data.groups)} · la API publica material_recipe ${JSON.stringify(api.body?.data?.material_recipe ?? null)}`
        entry.requestStatus = api.status
        entry.detail = {
          rendered: /receta|insumo/i.test(body),
          groups: data.groups,
          apiField: api.body?.data?.material_recipe ?? null,
          origin: 'INFERIDO',
          cause:
            'App\\Http\\Requests\\StoreProcedureCatalogRequest acepta `material_recipe` y ProcedureCatalogService lo sincroniza, pero ningún consumidor de resources/js lo lee ni lo escribe (grep de `material_recipe` en resources/js: 0 coincidencias)',
          products: fixtures.products ?? []
        }
        entry.verdict = /receta|insumo/i.test(body) ? 'OK' : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : 'material_recipe se escribe por API y no tiene superficie: ni el formulario ni el detalle lo leen'
        return entry
      }
    )

    await step(
      'edit-door',
      'Pulsar «Editar» en la cabecera del detalle',
      'La edición vuelve al listado con el formulario del procedimiento abierto',
      async entry => {
        const urlBefore = page.url()
        const trail = []
        page.on('framenavigated', frame => {
          if (frame === page.mainFrame()) {
            trail.push(frame.url().replace(/^https?:\/\/[^/]+/, ''))
          }
        })

        await page.locator(UI.detail.edit).click({ timeout: 8000 })
        await page
          .locator('[role="dialog"]')
          .waitFor({ state: 'visible', timeout: 3000 })
          .catch(() => {})
        await page.waitForTimeout(600)

        const state = await page.evaluate(() => {
          const dialog = document.querySelector('[role="dialog"]')
          return {
            url: window.location.pathname + window.location.search,
            open: Boolean(dialog),
            code: (dialog?.querySelector('input')?.value ?? null) || null
          }
        })
        const detailCall = requests.filter(
          row => row.path === `/api/procedure-catalog/${surface.id}` && row.method === 'GET'
        ).length

        entry.obtained = `«${urlBefore.replace(/^https?:\/\/[^/]+/, '')}» → ${JSON.stringify(trail)} · diálogo ${state.open} · código precargado ${state.code}`
        entry.requestStatus = null
        entry.detail = {
          trail,
          state,
          detailReads: detailCall,
          cause:
            'resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue:604 openEditFromQuery consume ?edit= y lo retira (línea 616) antes de que la fila esté en la página cargada; la vista se monta dos veces (tres peticiones × 2) y el formulario nunca abre'
        }
        entry.verdict = state.open && state.code === surface.code ? 'OK' : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : `el botón Editar navega al listado y el formulario del procedimiento no abre (URL final «${state.url}»)`
        await closeAllDialogs(page)
        return entry
      }
    )
  } finally {
    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.consoleClassified = classifyConsole(diagnostics.consoleErrors)
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    evidence.totals = walkTotals(evidence)
    audit('walk-detail.json', evidence)

    await closeAllDialogs(page).catch(() => {})
  }

  expect(evidence.steps.length, 'detail walkthrough steps').toBe(6)
  expect(evidence.pageErrors, 'page errors during the detail walkthrough').toEqual([])
  expect(evidence.totals.notProbed, 'steps that could not be probed').toBe(0)
})

test('@U8 · capa 4-5 — el tarifario de la sede y el picker compartido', async ({
  page,
  request
}) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const credential = branchCredential()
  const session = await loginCredential(request, credential.username, credential.password)
  const { surface } = fixtures.procedures
  const day = localDay()

  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const state = { pageErrors: diagnostics.pageErrors, currentStep: null }
  const evidence = {
    ...stamp(),
    route: `${CATALOG_ROUTE}/:id`,
    role: 'administrador (sede)',
    actor: { username: credential.username, branchId: session.user.branch_id ?? null },
    shotDir: 'walk/tariff',
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
    await primeSession(page, session)
    await page.goto(`${CATALOG_ROUTE}/${surface.id}`)
    await settleCatalogDetail(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    await step(
      'tariff-open',
      'Abrir la pestaña «Tarifario» como administrador de sede',
      'La pestaña ofrece la sede del actor y el precio de la sesión con su ventana',
      async entry => {
        const opened = await openTab(page, 'Tarifario')
        await page
          .locator(UI.detail.tariff)
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})
        await page
          .locator(UI.detail.tariffRows)
          .first()
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})
        const data = await readTariff(page)
        const fixtureRow = data.rows.find(row => /375/.test(row.price ?? '')) ?? null
        const listCall = requests
          .filter(row => row.path === `/api/branches/${fixtures.branch.id}/procedure-prices`)
          .at(-1)
        entry.obtained = `pestaña ${opened.ok} · sede «${data.branch}» · ${data.rowCount} fila(s) · precio de la sesión ${JSON.stringify(fixtureRow)}`
        entry.requestStatus = listCall?.status ?? null
        entry.detail = {
          opened,
          branch: data.branch,
          rows: data.rows,
          expected: fixtures.branchPrice,
          fixtureRow
        }
        entry.verdict =
          data.present &&
          data.rows.length > 0 &&
          fixtureRow &&
          fixtureRow.validity ===
            `${fixtures.branchPrice.validFrom.split('-').reverse().join('/')} — ${fixtures.branchPrice.validTo.split('-').reverse().join('/')}`
            ? 'OK'
            : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : `el tarifario no muestra el precio de la sesión con su ventana (${JSON.stringify(fixtureRow?.validity ?? null)})`
        return entry
      }
    )

    const createdPrice = { id: null, price: 320 }

    await step(
      'price-create',
      'Crear un precio de sede con ventana de vigencia',
      'El precio nuevo se guarda con su ventana y aparece en la lista',
      async entry => {
        await page.locator(UI.detail.tariffAdd).click({ timeout: 8000 })
        await page
          .locator('[data-tariff-submit]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})

        // The door upserts by (procedure, sede, payer, valid_from): a row that
        // starts today would overwrite the fixture price the picker needs, so
        // this one starts tomorrow.
        const windowFrom = addDaysLocal(day, 1)
        const windowTo = addDaysLocal(day, 31)
        createdPrice.from = windowFrom
        createdPrice.to = windowTo

        await fillControl(page, '[role="dialog"] [data-field="price"] input', createdPrice.price)
        await fillControl(page, '[data-field="valid_from"] input', windowFrom)
        await fillControl(page, '[data-field="valid_to"] input', windowTo)

        const pending = page
          .waitForResponse(
            response =>
              response.request().method() === 'PUT' &&
              /\/api\/branches\/\d+\/procedure-prices$/.test(new URL(response.url()).pathname),
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await page.locator('[data-tariff-submit]').click({ timeout: 8000 })
        const response = await pending
        const status = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null
        const saved =
          (body?.data ?? []).find(row => Number(row.price) === createdPrice.price) ?? null
        createdPrice.id = saved?.id ?? null
        if (createdPrice.id) {
          registerCreated('branch-price', {
            id: createdPrice.id,
            branchId: fixtures.branch.id,
            step: 'price-create'
          })
        }

        await page.waitForTimeout(600)
        const data = await readTariff(page)
        const row = data.rows.find(entryRow => /320/.test(entryRow.price ?? '')) ?? null
        const toast = await toastText(page)

        entry.obtained = `PUT ${status} · id ${createdPrice.id ?? '—'} · fila ${JSON.stringify(row)} · toast «${toast}»`
        entry.requestStatus = status
        entry.request = { method: 'PUT', path: response ? new URL(response.url()).pathname : null }
        entry.detail = { saved, row, toast, window: { from: windowFrom, to: windowTo } }
        entry.verdict =
          status === 200 &&
          row &&
          row.validity ===
            `${windowFrom.split('-').reverse().join('/')} — ${windowTo.split('-').reverse().join('/')}`
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'price-window-invalid',
      'Guardar una vigencia final anterior al inicio',
      'El formulario rechaza la ventana en línea y no envía la petición',
      async entry => {
        const target = tariffRow(page, '320')
        await target.locator(UI.detail.tariffEdit).first().click({ timeout: 8000 })
        await page
          .locator('[data-tariff-submit]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})

        await fillControl(page, '[data-field="valid_from"] input', day)
        await fillControl(page, '[data-field="valid_to"] input', addDaysLocal(day, -2))

        const before = requests.filter(
          row => row.method === 'PUT' && /procedure-prices$/.test(row.path)
        ).length
        await page.locator('[data-tariff-submit]').click({ timeout: 8000 })
        await page.waitForTimeout(700)
        const after = requests.filter(
          row => row.method === 'PUT' && /procedure-prices$/.test(row.path)
        ).length

        const message = await page.evaluate(() => {
          const dialog = document.querySelector('[role="dialog"]')
          const field = dialog?.querySelector('[data-field="valid_to"]')
          const text = (dialog?.innerText ?? '').trim().replace(/\s+/g, ' ')
          return {
            fieldText: (field?.innerText ?? '').trim(),
            mentionsRule: /vigencia final no puede ser anterior/i.test(text),
            text: text.slice(-260)
          }
        })

        entry.obtained = `mensaje en el campo ${message.mentionsRule} · campo «${message.fieldText}» · PUT antes ${before} → después ${after}`
        entry.requestStatus = after > before ? (requests.at(-1)?.status ?? null) : null
        entry.detail = {
          message,
          before,
          after,
          cause:
            'resources/js/modules/procedure-catalog/BranchTariffPanel.vue:validate() escribe formErrors.valid_to, pero el UiInput de `valid_to` no recibe `:error` (sólo el precio lo hace): la ventana invertida se bloquea sin explicación'
        }
        entry.verdict = after === before && message.mentionsRule ? 'OK' : 'DEFECTO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : after > before
              ? 'la ventana invertida viaja a la API'
              : 'la ventana invertida se bloquea sin mensaje: el error existe en el modelo y no se pinta en el campo'
        await page
          .locator(UI.detail.tariffCancel)
          .click({ timeout: 8000 })
          .catch(() => {})
        await closeAllDialogs(page)
        return entry
      }
    )

    await step(
      'price-retire',
      'Retirar el precio recién creado',
      'La confirmación retira la fila y la lista vuelve a la del precio de la sesión',
      async entry => {
        const target = tariffRow(page, '320')
        await target.locator(UI.detail.tariffRemove).first().click({ timeout: 8000 })
        await page
          .locator('[role="alertdialog"]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})

        const pending = page
          .waitForResponse(
            response =>
              response.request().method() === 'DELETE' &&
              /\/api\/branches\/\d+\/procedure-prices\/\d+$/.test(new URL(response.url()).pathname),
            { timeout: LOAD_TIMEOUT }
          )
          .catch(() => null)

        await dialogButton(page, 'Retirar').click({ timeout: 8000 })
        const response = await pending
        const status = response?.status() ?? null
        await page.waitForTimeout(700)
        const data = await readTariff(page)

        entry.obtained = `DELETE ${status} · filas ${data.rowCount} · quedan ${JSON.stringify(data.rows.map(row => row.price))}`
        entry.requestStatus = status
        entry.request = {
          method: 'DELETE',
          path: response ? new URL(response.url()).pathname : null
        }
        entry.detail = { rows: data.rows }
        entry.verdict =
          status === 200 && !data.rows.some(row => /320/.test(row.price ?? '')) ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    // --- S12: the shared picker ---------------------------------------------
    await step(
      'picker-price',
      'Abrir el picker compartido (presupuestos) como administrador de sede',
      'El picker muestra el precio de la sede que la API resuelve para ese actor',
      async entry => {
        const api = await call(
          request,
          session,
          'GET',
          `/api/procedure-catalog/search?q=${encodeURIComponent(surface.code)}&limit=20`
        )
        const apiRow = (api.body?.data ?? [])[0] ?? null

        await page.goto(QUOTATIONS_ROUTE)
        await settleQuotations(page)
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        await page.locator(UI.quotations.create).click({ timeout: 8000 })
        await page
          .locator(UI.quotations.form)
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})

        // The picker lives behind the per-line toggle (#293 added it inside the
        // line's "Catálogo y sitio" block), so the read surface needs one click.
        await page
          .locator('[data-item-catalog-toggle="0"]')
          .first()
          .click({ timeout: 8000 })
          .catch(() => {})
        await page
          .locator('[data-item-catalog-panel="0"]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})
        // The picker paints a spinner until its own page arrives: wait for a
        // row, not for the panel (the panel also holds the line's controls).
        const pickerReady = () =>
          page
            .waitForFunction(
              () => {
                const dialog = document.querySelector('[role="dialog"]')
                const input = dialog?.querySelector(
                  'input[placeholder="Buscar por nombre o código..."]'
                )
                const scope = input?.closest('div.space-y-3')
                return Boolean(scope && scope.querySelector('button[type="button"]'))
              },
              undefined,
              { timeout: 20000, polling: 150 }
            )
            .then(() => true)
            .catch(() => false)

        const ready = await pickerReady()

        // The picker loads the first page of the active catalogue (12 rows): the
        // row of this session is found by paging, not by typing, because typing
        // is exactly the defect the next step measures.
        let found = null
        const pageReads = []

        for (let attempt = 0; attempt < 8 && !found; attempt += 1) {
          const picker = await readPicker(page)
          pageReads.push(picker?.rowCount ?? null)
          found = (picker?.rows ?? []).find(row => row.code === surface.code) ?? null
          if (found) break

          const next = page.locator('[role="dialog"] .pagination-nav-button--next').first()
          if ((await next.count().catch(() => 0)) === 0) break
          if (await next.isDisabled().catch(() => true)) break
          await next.click({ timeout: 6000 }).catch(() => {})
          await page.waitForTimeout(600)
        }

        entry.obtained = `la API responde ${apiRow?.default_cost ?? '—'} (origen ${apiRow?.price_source ?? '—'}, lista ${apiRow?.list_cost ?? '—'}) · el picker muestra ${found ? JSON.stringify(found.meta ?? found.text) : 'la fila no apareció'} · picker listo ${ready} · páginas leídas ${JSON.stringify(pageReads)}`
        entry.requestStatus = api.status
        entry.request = { method: 'GET', path: '/api/procedure-catalog/search' }
        entry.detail = {
          api: {
            price: apiRow?.default_cost ?? null,
            listCost: apiRow?.list_cost ?? null,
            source: apiRow?.price_source ?? null
          },
          pickerRow: found,
          pickerReady: ready,
          fixturePrice: fixtures.branchPrice,
          pageReads,
          note: 'el picker lee GET /api/procedure-catalog (índice), que publica default_cost de catálogo; /api/procedure-catalog/search sí resuelve la sede'
        }
        const pickerPrice = Number(
          String(found?.meta ?? '')
            .match(/S\/\s*([\d.,]+)/)?.[1]
            ?.replace(/,/g, '') ?? NaN
        )
        entry.verdict =
          Number.isFinite(pickerPrice) && Number.isFinite(Number(apiRow?.default_cost))
            ? pickerPrice === Number(apiRow.default_cost)
              ? 'OK'
              : 'DEFECTO'
            : 'NO PROBADO'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : entry.verdict === 'NO PROBADO'
              ? 'no se pudo leer el precio del picker en la fila de la sesión'
              : `el picker muestra S/ ${pickerPrice} y la API resuelve S/ ${apiRow.default_cost} para la sede del actor`
        return entry
      }
    )

    await step(
      'picker-search',
      `Buscar el código ${WIDE_CATALOG_CODE} dentro del picker`,
      'El picker filtra el catálogo completo por código, como la lista del catálogo',
      async entry => {
        const api = await call(
          request,
          session,
          'GET',
          `/api/procedure-catalog?q=${WIDE_CATALOG_CODE}&per_page=100`
        )
        const apiTotal = api.body?.meta?.total ?? null

        await page
          .waitForFunction(
            () => {
              const dialog = document.querySelector('[role="dialog"]')
              const input = dialog?.querySelector(
                'input[placeholder="Buscar por nombre o código..."]'
              )
              const scope = input?.closest('div.space-y-3')
              return Boolean(scope && scope.querySelector('button[type="button"]'))
            },
            undefined,
            { timeout: 20000, polling: 150 }
          )
          .catch(() => {})
        const before = await readPicker(page)
        const pickerInput = page
          .locator('[role="dialog"] input[placeholder="Buscar por nombre o código..."]')
          .first()
        await pickerInput.fill(WIDE_CATALOG_CODE, { timeout: 8000 }).catch(() => {})
        await page.waitForTimeout(1400)
        const picker = await readPicker(page)
        const codes = (picker?.rows ?? []).map(row => row.code)

        entry.obtained = `la API devuelve ${apiTotal} fila(s) para q=${WIDE_CATALOG_CODE} · el picker tenía ${before?.rowCount ?? '—'} fila(s) y tras teclear pinta ${picker?.rowCount ?? '—'} ${JSON.stringify(codes)} · ${JSON.stringify(picker?.emptyText ?? null)}`
        entry.requestStatus = api.status
        entry.request = {
          method: 'GET',
          path: '/api/procedure-catalog',
          query: { q: WIDE_CATALOG_CODE }
        }
        entry.detail = {
          apiTotal,
          before,
          picker,
          cause:
            'resources/js/components/procedures/ProcedureCatalogPicker.vue: filteredProcedures filtra en cliente las 12 filas cargadas y onSearchInput recarga la página 1 sin término (register #416)'
        }
        entry.verdict = picker && picker.rowCount === 0 && (apiTotal ?? 0) > 0 ? 'DEFECTO' : 'OK'
        entry.reason =
          entry.verdict === 'OK'
            ? null
            : `el picker no encuentra un procedimiento que existe (${WIDE_CATALOG_CODE}): filtra su página de ${picker?.rowCount ?? 0} filas y recarga sin término`
        await closeAllDialogs(page)
        return entry
      }
    )
  } finally {
    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.consoleClassified = classifyConsole(diagnostics.consoleErrors)
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    evidence.totals = walkTotals(evidence)
    audit('walk-tariff.json', evidence)

    await closeAllDialogs(page).catch(() => {})
  }

  expect(evidence.steps.length, 'tariff walkthrough steps').toBe(6)
  expect(evidence.pageErrors, 'page errors during the tariff walkthrough').toEqual([])
  expect(evidence.totals.notProbed, 'steps that could not be probed').toBe(0)
})
