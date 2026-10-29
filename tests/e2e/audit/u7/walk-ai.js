// Plan #13 (issue #370, U7b) — layer 4: the AI decision-support module, walked.
//
// One walkthrough as `administrador` (it reaches every analysis): the list and
// its KPIs, the four filters, the creation sheet (patient, image, 20 MB gate,
// notice declaration), the review, the annulment with its reason, the refusal to
// annul a cited analysis, and the trace the surface cannot reach.
//
// The session's own analyses are located by the run token in the patient search
// and by the distinct confidence each promoted row carries: the table exposes no
// id hook and the audit database accumulates rows across runs.

import { test, expect } from 'playwright/test'

import { classifyFailures, collectDiagnostics, loginAs, primeSession } from '../harness.js'

import { analysisByKey, ensureFixtures, fixtureSummary } from './fixtures.js'

import {
  ACTION_TIMEOUT,
  AI_ROUTE,
  DESKTOP,
  DIALOG_TIMEOUT,
  LOAD_TIMEOUT,
  audit,
  call,
  closeAllDialogs,
  dismissToasts,
  fillControl,
  lastWrite,
  localDay,
  nonImageFile,
  openSelect,
  oversizePng,
  pngBuffer,
  recorder,
  settleAi,
  shot,
  stamp,
  uploadPayload,
  waitForCall,
  waitForToast,
  walkTotals,
  watchRequests
} from './helpers.js'

const DAY = localDay()

const readAiPage = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const kpi = key => text(document.querySelector(`[data-kpi-value="${key}"]`))
    const rows = Array.from(document.querySelectorAll('[data-ai-table] tbody tr')).map(row => ({
      cells: Array.from(row.querySelectorAll('td')).map(text),
      text: text(row),
      actions: Array.from(row.querySelectorAll('[data-row-action]')).map(button => ({
        action: button.getAttribute('data-row-action'),
        label: button.getAttribute('aria-label')
      }))
    }))

    return {
      kpis: {
        total: kpi('total_analyses'),
        completed: kpi('completed_analyses'),
        pending: kpi('pending_review'),
        rate: kpi('completion_rate')
      },
      presets: Array.from(document.querySelectorAll('[data-preset]')).map(button => ({
        id: button.getAttribute('data-preset'),
        pressed: button.getAttribute('aria-pressed') === 'true'
      })),
      clearCount: document.querySelectorAll('[data-clear-filters]').length,
      rows,
      empty: text(document.querySelector('[data-ai-empty]')) || null,
      emptyActions: Array.from(document.querySelectorAll('[data-ai-empty] button')).map(text),
      reviewColumn: rows.map(row => row.cells[4] ?? null)
    }
  })

const rowByConfidence = (page, value) =>
  page.locator('[data-ai-table] tbody tr').filter({ hasText: value }).first()

test('@U7 · U7b IA: lista, filtros, sheet, revisión, anulación y traza', async ({
  page,
  request
}) => {
  test.setTimeout(900000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const cited = analysisByKey(fixtures, 'cited')
  const voidable = analysisByKey(fixtures, 'voidable')
  const reviewable = analysisByKey(fixtures, 'reviewable')
  const { token } = fixtures

  const diagnostics = collectDiagnostics(page)
  const state = { pageErrors: [], currentStep: 'setup' }
  page.on('pageerror', error =>
    state.pageErrors.push({ message: error.message, step: state.currentStep })
  )

  const evidence = {
    route: AI_ROUTE,
    role: 'administrador',
    ...stamp(),
    shotDir: 'walk/ai',
    scenario:
      'leer la lista y sus KPIs, aplicar los cuatro filtros, abrir el sheet y validar los tres gates ' +
      'de la imagen y el aviso al paciente, subir un análisis, revisarlo, anular uno con motivo y ' +
      'medir el rechazo de la anulación de un análisis citado',
    fixtures: fixtureSummary(fixtures),
    steps: [],
    findings: [],
    pageErrors: [],
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const step = recorder(page, evidence, state)
  const requests = watchRequests(page)
  const nativeDialogs = []
  page.on('dialog', async dialog => {
    nativeDialogs.push({ type: dialog.type(), message: dialog.message() })
    await dialog.dismiss().catch(() => {})
  })

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const load = waitForCall(page, 'GET', '/api/ai-analysis', () => true, LOAD_TIMEOUT)
    await page.goto(AI_ROUTE)
    await load
    await settleAi(page)
    await page.waitForTimeout(700)

    // --- 01: the list and the KPI strip -----------------------------------
    await step(
      'list-kpis',
      'abrir /ai-analysis',
      'la tabla lista los análisis y la tira de KPIs cuadra con GET /api/ai-analysis/stats',
      async () => {
        const ui = await readAiPage(page)
        const [index, stats] = await Promise.all([
          call(request, session, 'GET', '/api/ai-analysis?per_page=15'),
          call(request, session, 'GET', '/api/ai-analysis/stats')
        ])
        const apiTotal = index.body?.meta?.total ?? null
        const apiStats = stats.body?.data ?? {}

        return {
          requestStatus: index.status,
          obtained: `filas ${ui.rows.length} (API ${apiTotal}); KPIs ${JSON.stringify(ui.kpis)} vs API total ${apiStats.total_analyses}`,
          verdict:
            ui.rows.length === Math.min(apiTotal ?? 0, 15) &&
            Number(ui.kpis.total) === apiStats.total_analyses
              ? 'OK'
              : 'DEFECTO',
          detail: null,
          extra: { ui, apiTotal, apiStats }
        }
      }
    )

    // --- 02: filter by the run's own patient ------------------------------
    await step(
      'filter-search',
      `buscar «${token}» en el filtro de paciente`,
      'la búsqueda se resuelve en el servidor y deja sólo los análisis de ese paciente',
      async () => {
        const searched = waitForCall(
          page,
          'GET',
          '/api/ai-analysis',
          params => (params.get('search') ?? '') === token,
          ACTION_TIMEOUT
        )
        await fillControl(page, '[data-filters-search] input', token)
        const response = await searched
        await page.waitForTimeout(600)

        const ui = await readAiPage(page)
        const api = await call(
          request,
          session,
          'GET',
          `/api/ai-analysis?search=${encodeURIComponent(token)}&per_page=15`
        )

        return {
          requestStatus: response?.status() ?? api.status,
          obtained: `filas ${ui.rows.length}; API ${api.body?.meta?.total}; KPIs ${JSON.stringify(ui.kpis)}`,
          verdict:
            ui.rows.length === (api.body?.meta?.total ?? -1) && ui.rows.length > 0
              ? 'OK'
              : 'DEFECTO',
          detail:
            Number(ui.kpis.total) === (api.body?.meta?.total ?? -1)
              ? null
              : `La tira de KPIs no sigue al filtro: la tabla muestra ${ui.rows.length} fila(s) y el KPI «Total de análisis» sigue en ${ui.kpis.total}.`,
          extra: { ui, apiTotal: api.body?.meta?.total ?? null }
        }
      }
    )

    // --- 02b: the reviewed select's display against the applied filter ----
    await step(
      'filter-reviewed-initial',
      'leer el selector «Revisión» recién pintado y compararlo con la consulta que la página envió',
      'el selector muestra el filtro realmente aplicado («Todas») o ninguno',
      async () => {
        const display = await page
          .locator('[data-ai-filters] button[aria-haspopup="true"]')
          .first()
          .innerText()
          .then(text => text.trim())
          .catch(() => null)
        const initial = await page.evaluate(() =>
          performance
            .getEntriesByType('resource')
            .map(entry => entry.name)
            .filter(name => name.includes('/api/ai-analysis?'))
            .slice(-4)
        )
        const applied = initial.map(url => {
          try {
            const params = new URL(url).searchParams
            return { reviewed: params.get('reviewed'), status: params.get('status'), url }
          } catch {
            return { reviewed: null, status: null, url }
          }
        })
        const anyApplied = applied.some(entry => entry.reviewed !== null)

        return {
          obtained: `el selector muestra «${display}»; consultas ejecutadas: ${JSON.stringify(applied.map(entry => ({ reviewed: entry.reviewed, status: entry.status })))}`,
          verdict: display === 'Todas' || anyApplied ? 'OK' : 'DEFECTO',
          detail:
            display !== 'Todas' && !anyApplied
              ? `El selector «Revisión» muestra «${display}» desde el primer pintado y tras «Limpiar filtros», pero ninguna consulta lleva \`reviewed\`: el valor vacío del modelo (\`''\`) se convierte en \`true\` en el prop de \`UiSelect\` (\`modelValue\` declara \`Boolean\` antes que \`String\`), así que la pantalla anuncia un filtro que la lista no aplica.`
              : null,
          extra: { display, applied }
        }
      }
    )

    // --- 03: the presets --------------------------------------------------
    const presets = [
      ['pending', { status: 'completed', reviewed: 'false' }],
      ['failed', { status: 'failed' }],
      ['completed', { status: 'completed' }],
      ['all', {}]
    ]

    for (const [preset, expected] of presets) {
      await step(
        `preset-${preset}`,
        `pulsar el preset «${preset}»`,
        'la tabla responde con el subconjunto del preset y el preset queda marcado',
        async () => {
          const awaited = waitForCall(
            page,
            'GET',
            '/api/ai-analysis',
            params => {
              const status = params.get('status') ?? ''
              const reviewed = params.get('reviewed') ?? ''
              return status === (expected.status ?? '') && reviewed === (expected.reviewed ?? '')
            },
            ACTION_TIMEOUT
          )
          await page.locator(`[data-preset="${preset}"]`).click({ timeout: 10000 })
          const response = await awaited
          await page.waitForTimeout(500)

          const ui = await readAiPage(page)
          const api = await call(
            request,
            session,
            'GET',
            `/api/ai-analysis?search=${encodeURIComponent(token)}&per_page=15${
              expected.status ? `&status=${expected.status}` : ''
            }${expected.reviewed ? `&reviewed=${expected.reviewed}` : ''}`
          )
          const apiTotal = api.body?.meta?.total ?? null
          const pressed = ui.presets.find(entry => entry.id === preset)?.pressed

          return {
            requestStatus: response?.status() ?? api.status,
            obtained: `filas ${ui.rows.length} (API ${apiTotal}); preset marcado=${pressed}; KPI total ${ui.kpis.total}`,
            verdict: pressed === true && ui.rows.length === apiTotal ? 'OK' : 'DEFECTO',
            detail:
              Number(ui.kpis.total) === apiTotal
                ? null
                : `el preset filtra la tabla (${ui.rows.length} filas) pero el KPI «Total de análisis» sigue en ${ui.kpis.total}: dos números del mismo bloque cuentan poblaciones distintas.`,
            extra: { ui, apiTotal, preset }
          }
        }
      )
    }

    // --- 04: the reviewed filter and the date range -----------------------
    await step(
      'filter-reviewed-and-dates',
      'filtrar por «Sin revisar», acotar el rango de fechas y limpiar los filtros',
      'cada control dispara su consulta y «Limpiar filtros» devuelve la lista completa',
      async () => {
        const reviewed = waitForCall(
          page,
          'GET',
          '/api/ai-analysis',
          params => params.get('reviewed') === 'false',
          ACTION_TIMEOUT
        )
        await openSelect(page, '[data-ai-filters] button[aria-haspopup="true"]', 'Sin revisar')
        const reviewedResponse = await reviewed
        await page.waitForTimeout(400)
        const unreviewed = await readAiPage(page)

        const dated = waitForCall(
          page,
          'GET',
          '/api/ai-analysis',
          params => (params.get('date_from') ?? '') === DAY,
          ACTION_TIMEOUT
        )
        await fillControl(page, '[data-filters-from] input', DAY)
        const datedResponse = await dated
        await page.waitForTimeout(400)
        const ranged = await readAiPage(page)

        const cleared = waitForCall(
          page,
          'GET',
          '/api/ai-analysis',
          params => !params.get('date_from') && !params.get('reviewed'),
          ACTION_TIMEOUT
        )
        await page.locator('[data-clear-filters]').first().click({ timeout: 10000 })
        const clearedResponse = await cleared
        await page.waitForTimeout(400)
        const clean = await readAiPage(page)

        return {
          requestStatus: reviewedResponse?.status() ?? null,
          obtained: `sin revisar → ${unreviewed.rows.length} filas; desde ${DAY} → ${ranged.rows.length}; limpiar → ${clean.rows.length} filas (botones «Limpiar filtros» en pantalla: ${clean.clearCount})`,
          verdict:
            reviewedResponse &&
            datedResponse &&
            clearedResponse &&
            clean.rows.length >= ranged.rows.length
              ? 'OK'
              : 'DEFECTO',
          detail: null,
          extra: {
            unreviewed: unreviewed.rows.length,
            ranged: ranged.rows.length,
            clean: clean.rows.length
          }
        }
      }
    )

    // Re-apply the patient search so the following steps act on this run's rows.
    await fillControl(page, '[data-filters-search] input', token)
    await page.waitForTimeout(900)

    // --- 05: the sheet's three gates --------------------------------------
    await step(
      'sheet-open',
      'pulsar «Nuevo análisis»',
      'el sheet abre a la derecha con sus cuatro pasos y el envío deshabilitado hasta completar paciente, imagen y aviso',
      async () => {
        await page.locator('[data-hero-primary]').click({ timeout: 10000 })
        const opened = await page
          .locator('[data-ai-analysis-sheet]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        await page.waitForTimeout(500)

        const sheet = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
          return {
            steps: Array.from(document.querySelectorAll('[data-sheet-step]')).map(step =>
              step.getAttribute('data-sheet-step')
            ),
            submitDisabled: document.querySelector('[data-sheet-submit]')?.disabled ?? null,
            hint: text(document.querySelector('[data-ai-analysis-sheet] [role="alert"]')) || null,
            text: text(document.querySelector('[data-ai-analysis-sheet]')).slice(0, 400)
          }
        })

        return {
          obtained: `pasos ${JSON.stringify(sheet.steps)}; envío disabled=${sheet.submitDisabled}; pista=${JSON.stringify(sheet.hint)}`,
          verdict:
            opened && sheet.steps.length === 4 && sheet.submitDisabled === true ? 'OK' : 'DEFECTO',
          detail:
            sheet.submitDisabled === true && !sheet.hint
              ? 'El botón «Analizar con IA» arranca deshabilitado y ningún texto dice qué falta (paciente, imagen, aviso): el usuario debe adivinarlo.'
              : null,
          extra: { sheet }
        }
      }
    )

    await step(
      'sheet-non-image',
      'elegir un archivo que no es una imagen en el paso 2',
      'el sheet rechaza el archivo con un mensaje en línea, igual que hace con el tamaño',
      async () => {
        const before = nativeDialogs.length
        await page
          .locator('[data-ai-analysis-sheet] input[type="file"]')
          .setInputFiles(nonImageFile())
        await page.waitForTimeout(700)

        const sheet = await page.evaluate(() => ({
          errors: Array.from(
            document.querySelectorAll('[data-step-error="image"], [data-sheet-error]')
          ).map(node => (node.innerText ?? '').trim()),
          submitDisabled: document.querySelector('[data-sheet-submit]')?.disabled ?? null
        }))
        const raised = nativeDialogs.slice(before)

        return {
          obtained: `errores en línea ${JSON.stringify(sheet.errors)}; diálogos nativos ${JSON.stringify(raised.map(dialog => dialog.message))}`,
          verdict:
            raised.length === 0 && sheet.errors.length > 0 && sheet.submitDisabled === true
              ? 'OK'
              : 'DEFECTO',
          detail:
            raised.length > 0
              ? `El rechazo del archivo que no es imagen ocurre en un alert() nativo del primitivo FileUpload («${raised[0]?.message ?? ''}»), no en el mensaje en línea del paso: el sheet no se enteró del rechazo (errores en línea: ${JSON.stringify(sheet.errors)}) y la app rompe su propio vocabulario de feedback.`
              : sheet.errors.length === 0
                ? 'El archivo que no es imagen se rechazó sin mensaje en línea: el paso no dice por qué no se aceptó.'
                : null,
          extra: { sheet, nativeDialogs: raised }
        }
      }
    )

    await step(
      'sheet-oversize',
      'elegir una imagen de más de 20 MB',
      'el sheet muestra el límite en línea y no deja el archivo previsualizado como si estuviera listo',
      async () => {
        await page
          .locator('[data-ai-analysis-sheet] input[type="file"]')
          .setInputFiles(uploadPayload('audit-u7-oversize.png', oversizePng()))
        // The primitive emits the file before its FileReader finishes: the
        // preview (or its absence) is measured once the reader settled.
        await page
          .waitForFunction(
            () =>
              Boolean(
                document.querySelector(
                  '[data-ai-analysis-sheet] .preview-container, [data-ai-analysis-sheet] .preview-image, [data-ai-analysis-sheet] .file-info'
                )
              ),
            undefined,
            { timeout: 5000, polling: 100 }
          )
          .catch(() => {})
        await page.waitForTimeout(600)

        const readSheetState = () =>
          page.evaluate(() => ({
            errors: Array.from(
              document.querySelectorAll('[data-step-error="image"], [data-sheet-error]')
            ).map(node => (node.innerText ?? '').trim()),
            preview: Boolean(
              document.querySelector(
                '[data-ai-analysis-sheet] .preview-container, [data-ai-analysis-sheet] .preview-image, [data-ai-analysis-sheet] .file-info'
              )
            ),
            picker: document.querySelectorAll('[data-ai-analysis-sheet] input[type="file"]').length,
            clearButton: document.querySelectorAll('[data-ai-analysis-sheet] .clear-btn').length,
            submitDisabled: document.querySelector('[data-sheet-submit]')?.disabled ?? null
          }))

        const rejected = await readSheetState()

        // #426: a rejected file must not be presented as ready, so the recovery
        // is measured against the contract: the inline error is there, the
        // preview is gone and the picker is back without pressing anything.
        const recovery = rejected.preview
          ? await page
              .locator('[data-ai-analysis-sheet] .preview-container .clear-btn')
              .first()
              .click({ timeout: 8000 })
              .then(() => true)
              .catch(() => false)
          : null
        await page.waitForTimeout(600)
        const afterClear = await readSheetState()

        return {
          obtained: `errores ${JSON.stringify(rejected.errors)}; vista previa retenida=${rejected.preview}; selector de archivo disponible tras el rechazo=${rejected.picker}; X de la vista previa=${rejected.clearButton} (clic=${recovery}); selector tras limpiar=${afterClear.picker}`,
          verdict:
            rejected.errors.length > 0 && !rejected.preview && rejected.picker > 0
              ? 'OK'
              : 'DEFECTO',
          detail:
            rejected.errors.length === 0
              ? 'no apareció el mensaje del límite de 20 MB'
              : rejected.preview
                ? `El límite de 20 MB se avisa en línea, pero el primitivo conserva la vista previa del archivo rechazado y con ella desaparece el input de archivo (${rejected.picker} en el DOM): para volver a elegir hay que pulsar la X de la vista previa. La pantalla dice «supera los 20 MB» y a la vez muestra la imagen como si estuviera cargada.`
                : rejected.picker === 0
                  ? 'El rechazo avisa en línea pero deja el paso sin selector de archivo: no hay forma de volver a elegir.'
                  : null,
          extra: { rejected, afterClear, recovery }
        }
      }
    )

    await step(
      'sheet-submit',
      'elegir paciente e imagen válida, declarar el aviso y enviar',
      'el envío se habilita sólo con el aviso marcado, responde 201 y el resultado se abre en el detalle',
      async () => {
        const sheetInput = page.locator('[data-ai-analysis-sheet] input[type="file"]')
        await sheetInput.setInputFiles(
          uploadPayload(`audit-u7-sheet-${token}.png`, pngBuffer(72, 72, [90, 140, 200]))
        )
        await page.waitForTimeout(600)

        const picked = await page.evaluate(() => {
          const input = document.querySelector('[data-ai-analysis-sheet] .patient-selector input')
          return Boolean(input)
        })

        if (picked) {
          const selector = page.locator('[data-ai-analysis-sheet] .patient-selector input').first()
          await selector.click({ timeout: 8000 })
          await selector.fill(fixtures.patients.clinical.document)
          await page.waitForTimeout(900)
          const option = page
            .locator('[data-ai-analysis-sheet] .patient-selector div.cursor-pointer')
            .filter({ hasText: fixtures.patients.clinical.document })
            .first()
          await option.click({ timeout: 8000 })
          await page.waitForTimeout(400)
        }

        const beforeNotice = await page.evaluate(
          () => document.querySelector('[data-sheet-submit]')?.disabled ?? null
        )

        await page.locator('[data-sheet-notice]').check({ timeout: 8000 })
        await page.waitForTimeout(200)
        const afterNotice = await page.evaluate(
          () => document.querySelector('[data-sheet-submit]')?.disabled ?? null
        )

        const submitted = waitForCall(
          page,
          'POST',
          '/api/ai-analysis/upload-and-analyze',
          () => true,
          ACTION_TIMEOUT
        )
        await page.locator('[data-sheet-submit]').click({ timeout: 10000 })
        const response = await submitted
        const status = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null
        await page.waitForTimeout(1500)

        const detail = await page.evaluate(() => {
          const root = document.querySelector('[data-ai-detail]')
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
          // #427: a failed row must not open the detail as if it had a result.
          // The reason stays on the sheet the professional can retry from, so
          // the walk reads both inline channels and the progress surface.
          const failure =
            document.querySelector('[data-sheet-error]') ??
            document.querySelector('[data-progress-error]')

          return {
            sheetOpen: Boolean(document.querySelector('[data-ai-analysis-sheet]')),
            progressOpen: Boolean(document.querySelector('[data-progress-error]')),
            failure: text(failure),
            detailOpened: Boolean(root),
            status: root
              ? text(root.closest('[role="dialog"]')?.querySelector('[data-ai-detail-status]'))
              : null,
            notice: root ? text(root.querySelector('[data-ai-assistive-notice]')) : null,
            findings: root ? text(root.querySelector('[data-ai-section="findings"] p')) : null,
            failureBlock: root ? text(root.querySelector('[data-ai-failure]')) : null
          }
        })

        const analysisStatus = body?.data?.status ?? null
        const completedPath =
          analysisStatus === 'completed' && detail.detailOpened && detail.status === 'Completado'
        const failedPath =
          analysisStatus === 'failed' &&
          !detail.detailOpened &&
          detail.sheetOpen &&
          !detail.progressOpen &&
          detail.failure.length > 0

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `envío disabled antes/después del aviso: ${beforeNotice}/${afterNotice}; POST upload-and-analyze → ${status} (estado ${analysisStatus}); sheet abierto=${detail.sheetOpen}; aviso del fallo=${JSON.stringify(detail.failure)}; detalle abierto con estado «${detail.status ?? 'sin detalle'}»`,
          verdict:
            beforeNotice === true &&
            afterNotice === false &&
            status === 201 &&
            (completedPath || failedPath)
              ? 'OK'
              : 'DEFECTO',
          detail:
            status !== 201
              ? `el envío no respondió 201 (${status})`
              : analysisStatus === 'failed' && detail.detailOpened
                ? 'Sin OPENAI_API_KEY el análisis nace en `failed`, pero la interfaz abre el detalle como si hubiera resultado.'
                : analysisStatus === 'failed' && detail.progressOpen
                  ? 'El análisis falló y la interfaz deja abierto el modal de progreso, que no se cierra con Escape: la página queda atrapada detrás.'
                  : analysisStatus === 'failed' && detail.failure.length === 0
                    ? 'El análisis falló y la pantalla no publica ningún motivo: el usuario lee «Fallido» sin causa.'
                    : null,
          extra: { beforeNotice, afterNotice, detail, status: body?.data?.status ?? null }
        }
      }
    )

    // --- 06: review a completed analysis ---------------------------------
    await step(
      'review',
      'abrir el análisis completado sin revisar y guardar una revisión',
      'el formulario de revisión aparece, la decisión viaja como POST …/review (200) y la fila pasa a «Revisado»',
      async () => {
        // The submission opened its own detail modal on top of the table.
        await closeAllDialogs(page)
        await page.waitForTimeout(500)

        const row = rowByConfidence(page, '81.00%')

        if ((await row.count().catch(() => 0)) === 0) {
          return {
            obtained: 'la fila del análisis por revisar no está en la tabla filtrada',
            verdict: 'NO PROBADO'
          }
        }

        await row.locator('[data-row-action="view"]').click({ timeout: 10000 })
        await page
          .locator('[data-ai-detail]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})
        await page.waitForTimeout(600)

        const formVisible = await page.locator('[data-ai-review-form]').count()
        const submitBefore = await page
          .locator('[data-ai-review-submit]')
          .isDisabled()
          .catch(() => null)

        await openSelect(page, '[data-ai-review-decision] button[aria-haspopup="true"]', 'Aceptado')
        await fillControl(page, '[data-ai-review-notes]', 'AUDIT-U7 revisión de la sesión')

        const submitted = waitForCall(
          page,
          'POST',
          new RegExp(`/api/ai-analysis/${reviewable?.id}/review$`),
          () => true,
          ACTION_TIMEOUT
        )
        await page.locator('[data-ai-review-submit]').click({ timeout: 10000 })
        const response = await submitted
        const status = response?.status() ?? null
        await waitForToast(page, null, 8000)
        await page.waitForTimeout(1200)

        const reviewedRows = await page
          .locator('[data-ai-table] tbody tr')
          .filter({ hasText: 'Revisado' })
          .count()

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `formulario=${formVisible > 0}; envío deshabilitado sin decisión=${submitBefore}; POST review → ${status}; filas con «Revisado»=${reviewedRows}`,
          verdict: formVisible > 0 && status === 200 && reviewedRows > 0 ? 'OK' : 'DEFECTO',
          detail: status === 200 ? null : 'la revisión no se guardó',
          extra: { formVisible, submitBefore, reviewedRows }
        }
      }
    )

    // --- 07: annul with a reason -----------------------------------------
    await step(
      'void-reason',
      'anular un análisis completado: primero sin motivo, después con motivo',
      'sin motivo el botón está deshabilitado; con motivo la confirmación abre y el POST …/void responde 200',
      async () => {
        await closeAllDialogs(page)
        await page.waitForTimeout(400)

        const row = rowByConfidence(page, '66.00%')

        if ((await row.count().catch(() => 0)) === 0) {
          return {
            obtained: 'la fila del análisis anulable no está en la tabla filtrada',
            verdict: 'NO PROBADO'
          }
        }

        await row.locator('[data-row-action="void"]').click({ timeout: 10000 })
        await page
          .locator('[data-ai-detail]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})
        await page.waitForTimeout(600)

        const emptyDisabled = await page
          .locator('[data-ai-void]')
          .isDisabled()
          .catch(() => null)

        await fillControl(page, '[data-ai-void-reason]', 'AUDIT-U7 anulación de la sesión')
        const filledDisabled = await page
          .locator('[data-ai-void]')
          .isDisabled()
          .catch(() => null)

        await page.locator('[data-ai-void]').click({ timeout: 10000 })
        await page.waitForTimeout(1200)
        const afterVoidClick = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
          return {
            dialogs: Array.from(
              document.querySelectorAll('[role="dialog"], [role="alertdialog"]')
            ).map(node => ({
              role: node.getAttribute('role'),
              visible: node.getBoundingClientRect().width > 0,
              heading: text(node.querySelector('h1, h2')).slice(0, 60)
            })),
            alertButtons: Array.from(document.querySelectorAll('[role="alertdialog"] button')).map(
              button => text(button)
            )
          }
        })
        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmOpen = await confirm
          .waitFor({ state: 'visible', timeout: DIALOG_TIMEOUT })
          .then(() => true)
          .catch(() => false)

        // The confirm's primary button is behind the detail modal: the hit test
        // is the measurement, and the walk does not click a control the user
        // cannot reach (a 10 s click timeout is not evidence, it is a stall).
        const hitTest = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim()
          const dialog = document.querySelector('[role="alertdialog"]')
          const button = dialog
            ? [...dialog.querySelectorAll('button')].find(node => text(node) === 'Anular')
            : null
          const box = button?.getBoundingClientRect() ?? null
          const at = box
            ? document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2)
            : null

          return {
            buttonFound: Boolean(button),
            hit: at
              ? {
                  tag: at.tagName,
                  text: (at.innerText ?? '').trim().slice(0, 60),
                  insideConfirm: dialog ? dialog.contains(at) : null
                }
              : null
          }
        })

        const blocked = hitTest.hit ? hitTest.hit.insideConfirm !== true : true
        const response = blocked ? null : await clickedConfirmed()

        async function clickedConfirmed() {
          const awaited = waitForCall(
            page,
            'POST',
            new RegExp(`/api/ai-analysis/${voidable?.id}/void$`),
            () => true,
            ACTION_TIMEOUT
          )
          await confirm
            .locator('button')
            .filter({ hasText: 'Anular' })
            .last()
            .click({ timeout: 8000 })
          return awaited
        }
        const status = response?.status() ?? null
        await page.waitForTimeout(600)

        const stillListed = await page
          .locator('[data-ai-table] tbody tr')
          .filter({ hasText: '66.00%' })
          .count()

        await closeAllDialogs(page)

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `sin motivo disabled=${emptyDisabled}; con motivo disabled=${filledDisabled}; la confirmación abrió=${confirmOpen}; el clic en «Anular» aterriza en ${JSON.stringify(hitTest.hit)}; POST void → ${status ?? 'no enviado'}; fila todavía listada=${stillListed}`,
          verdict:
            emptyDisabled === true &&
            filledDisabled === false &&
            confirmOpen &&
            !blocked &&
            status === 200 &&
            stillListed === 0
              ? 'OK'
              : 'DEFECTO',
          detail: blocked
            ? 'La confirmación se abre *debajo* del detalle del análisis: ambos overlays son `z-50` y el modal del detalle se teletransporta después, así que el botón «Anular» queda tapado y el clic del usuario no llega. Desde su punto de vista, «Anular análisis» no hace nada: el análisis nunca se anula.'
            : 'el flujo de anulación no completó',
          extra: {
            emptyDisabled,
            filledDisabled,
            confirmOpen,
            stillListed,
            hitTest,
            afterVoidClick
          }
        }
      }
    )

    // --- 08: annul a cited analysis --------------------------------------
    await step(
      'void-cited',
      'intentar anular el análisis citado por una evolución',
      'la interfaz muestra el motivo real del rechazo (el análisis está citado), no un mensaje genérico',
      async () => {
        const row = rowByConfidence(page, '88.00%')

        if ((await row.count().catch(() => 0)) === 0) {
          return {
            obtained: 'la fila del análisis citado no está en la tabla filtrada',
            verdict: 'NO PROBADO'
          }
        }

        await row.locator('[data-row-action="void"]').click({ timeout: 10000 })
        await page
          .locator('[data-ai-detail]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})
        await page.waitForTimeout(600)

        await fillControl(
          page,
          '[data-ai-void-reason]',
          'AUDIT-U7 intento de anular un análisis citado'
        )
        await page.locator('[data-ai-void]').click({ timeout: 10000 })
        await page.waitForTimeout(1200)

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmOpen = await confirm
          .waitFor({ state: 'visible', timeout: DIALOG_TIMEOUT })
          .then(() => true)
          .catch(() => false)

        const hitTest = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim()
          const dialog = document.querySelector('[role="alertdialog"]')
          const button = dialog
            ? [...dialog.querySelectorAll('button')].find(node => text(node) === 'Anular')
            : null
          const box = button?.getBoundingClientRect() ?? null
          const at = box
            ? document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2)
            : null

          return {
            buttonFound: Boolean(button),
            hit: at
              ? {
                  tag: at.tagName,
                  text: (at.innerText ?? '').trim().slice(0, 60),
                  insideConfirm: dialog ? dialog.contains(at) : null
                }
              : null
          }
        })
        // The confirmation sits on top of the detail (#423), so the click lands
        // and the door answers: the step measures the refusal it used to skip.
        let status = null
        let responseBody = null

        if (!hitTest.hit || hitTest.hit.insideConfirm === true) {
          const pending = page
            .waitForResponse(
              response =>
                response.request().method() === 'POST' &&
                /\/api\/ai-analysis\/\d+\/void$/.test(new URL(response.url()).pathname),
              { timeout: LOAD_TIMEOUT }
            )
            .catch(() => null)

          await confirm
            .locator('button')
            .filter({ hasText: 'Anular' })
            .last()
            .click({ timeout: 8000 })
            .catch(() => {})

          const settled = await pending
          status = settled?.status() ?? null
          responseBody = settled ? await settled.json().catch(() => null) : null
        }

        await page.waitForTimeout(600)

        const visible = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
          return {
            toasts: Array.from(
              document.querySelectorAll('[aria-live="assertive"], [aria-live="polite"]')
            )
              .map(text)
              .filter(Boolean),
            detailOpen: Boolean(document.querySelector('[data-ai-detail]')),
            inline: text(document.querySelector('[data-ai-void-section] [role="alert"]')) || null
          }
        })
        const shown = `${visible.toasts.join(' | ')} ${visible.inline ?? ''}`.trim()
        const apiReason =
          responseBody?.errors?.void?.[0] ??
          responseBody?.errors?.reason?.[0] ??
          responseBody?.message ??
          null
        const speaks = /citad|evoluci|plan/i.test(shown)
        const ok = status === 422 && speaks

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained:
            `la confirmación abrió=${confirmOpen}; el clic aterriza en ${JSON.stringify(hitTest.hit)}; ` +
            `POST void → ${status ?? 'sin respuesta'}; el aviso visible dice «${shown.slice(0, 160)}»`,
          verdict: ok ? 'OK' : status === null ? 'NO PROBADO' : 'DEFECTO',
          reason: status === null ? 'el clic no produjo la petición de anulación' : null,
          detail: ok
            ? null
            : status !== 422
              ? `la anulación de un análisis citado respondió ${status}: la interfaz debía recibir el 422 con su motivo`
              : `el 422 llegó y el aviso visible no nombra la causa (${apiReason ?? 'sin motivo en el cuerpo'}): «${shown.slice(0, 160)}»`,
          extra: {
            hitTest,
            detailOpen: visible.detailOpen,
            confirmOpen,
            citedAnalysisId: cited?.id ?? null,
            apiReason,
            shown
          }
        }
      }
    )

    // --- 09: the trace of the annulled row -------------------------------
    await step(
      'voided-trace',
      'buscar en la interfaz los análisis anulados',
      'la superficie ofrece ver los análisis anulados, como el contrato de la API (`?voided=1`)',
      async () => {
        const ui = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
          return {
            filters: text(document.querySelector('[data-ai-filters]')),
            presets: Array.from(document.querySelectorAll('[data-preset]')).map(button =>
              text(button)
            ),
            controlCount: document.querySelectorAll(
              '[data-ai-filters] button, [data-ai-filters] [role="button"]'
            ).length
          }
        })
        const api = await call(request, session, 'GET', '/api/ai-analysis?voided=1&per_page=50')
        const voidedRows = Array.isArray(api.body?.data) ? api.body.data.length : null
        const hasControl = /anulad/i.test(ui.filters)

        return {
          requestStatus: api.status,
          obtained: `API ?voided=1 → ${api.status} (${voidedRows} fila(s)); controles del bloque de filtros: ${ui.controlCount} ${JSON.stringify(ui.presets)}`,
          verdict: hasControl ? 'OK' : api.status === 200 ? 'DEFECTO' : 'NO PROBADO',
          detail: hasControl
            ? null
            : `La API publica \`?voided=1\` (${api.status}) y la pantalla no tiene ningún control para ver los análisis anulados: el rastro que #296 conserva no es consultable desde la interfaz.`,
          extra: { ui, voidedRows }
        }
      }
    )

    // --- 10: the detail of the cited analysis ----------------------------
    await step(
      'detail',
      'abrir el detalle del análisis citado',
      'el detalle publica el aviso asistencial, la confianza, el modelo, el costo y la traza del aviso al paciente',
      async () => {
        await closeAllDialogs(page)
        await page.waitForTimeout(400)

        const row = rowByConfidence(page, '88.00%')
        if ((await row.count().catch(() => 0)) === 0) {
          return {
            obtained: 'la fila del análisis citado no está en la tabla',
            verdict: 'NO PROBADO'
          }
        }

        await row.locator('[data-row-action="view"]').click({ timeout: 10000 })
        await page
          .locator('[data-ai-detail]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})
        await page.waitForTimeout(700)

        const detail = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
          const root = document.querySelector('[data-ai-detail]')
          if (!root) return null
          return {
            status: text(root.closest('[role="dialog"]')?.querySelector('[data-ai-detail-status]')),
            notice: text(root.querySelector('[data-ai-assistive-notice]')),
            confidence: text(root.querySelector('[data-ai-confidence]')),
            findings: Array.from(root.querySelectorAll('[data-ai-section="findings"] li')).length,
            recommendations: Array.from(
              root.querySelectorAll('[data-ai-section="recommendations"] li')
            ).length,
            metadata: Array.from(
              root.querySelectorAll('[data-ai-section="metadata"] dl > div')
            ).map(row => `${text(row.querySelector('dt'))}: ${text(row.querySelector('dd'))}`),
            reviewOutcome: text(root.querySelector('[data-ai-review-outcome]')),
            voidSection: Boolean(root.querySelector('[data-ai-void-section]'))
          }
        })

        const actionLabels = await page.evaluate(() =>
          Array.from(document.querySelectorAll('[data-ai-table] tbody tr [data-row-action]')).map(
            button => ({
              action: button.getAttribute('data-row-action'),
              label: button.getAttribute('aria-label')
            })
          )
        )
        const distinctActions = new Set(actionLabels.map(entry => entry.action))

        return {
          obtained: `estado «${detail?.status}»; confianza «${detail?.confidence}»; ${detail?.findings} hallazgo(s), ${detail?.recommendations} recomendación(es); acciones por fila ${JSON.stringify([...distinctActions])}`,
          verdict:
            detail && detail.notice.length > 0 && detail.findings > 0 && detail.metadata.length >= 6
              ? 'OK'
              : 'DEFECTO',
          detail:
            detail && /\.00%/.test(detail.confidence ?? '')
              ? `La confianza se publica con dos decimales («${detail.confidence}») mientras el resto de porcentajes de la sesión no los usa.`
              : null,
          extra: { detail, actionLabels: actionLabels.slice(0, 3) }
        }
      }
    )
  } finally {
    await closeAllDialogs(page).catch(() => {})
    await dismissToasts(page)

    evidence.requests = requests
      .filter(entry => entry.method !== 'GET')
      .slice(-40)
      .map(entry => ({ method: entry.method, path: entry.path, status: entry.status }))
    evidence.pageErrors = state.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    // The walk submits one refusal (422) and one native alert on purpose.
    evidence.nativeDialogs = nativeDialogs
    evidence.totals = walkTotals(evidence)
    audit('walk-ai.json', evidence)
  }

  expect(evidence.steps.length, 'steps of the AI walkthrough').toBeGreaterThanOrEqual(14)
  expect(
    evidence.steps.filter(entry => entry.shot === null),
    'steps without a capture'
  ).toEqual([])
  expect(state.pageErrors, 'page errors during the AI walkthrough').toEqual([])
})

// --- the reach of a specialist who does not follow the patient -------------

test('@U7 · U7b IA: el implantólogo sólo ve los análisis que alcanza', async ({
  page,
  request
}) => {
  test.setTimeout(300000)

  const session = await loginAs(request, 'implantologo')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const diagnostics = collectDiagnostics(page)
  const evidence = {
    route: AI_ROUTE,
    role: 'implantologo',
    ...stamp(),
    href: AI_ROUTE,
    shots: [],
    ui: null,
    api: null,
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await page.goto(AI_ROUTE)
    await settleAi(page)
    await page.waitForTimeout(800)

    const ui = await readAiPage(page)
    const api = await call(request, session, 'GET', '/api/ai-analysis?per_page=15')

    evidence.ui = {
      kpis: ui.kpis,
      rows: ui.rows.length,
      empty: ui.empty,
      emptyActions: ui.emptyActions
    }
    evidence.api = { status: api.status, total: api.body?.meta?.total ?? null }
    evidence.reach = {
      role: 'implantologo',
      visibleRows: ui.rows.length,
      apiTotal: api.body?.meta?.total ?? null,
      adminRows: fixtures.reach?.ai?.rows ?? null
    }
    evidence.shots.push(await shot(page, 'walk/ai-implantologo.png'))

    if (ui.rows.length === 0 && evidence.reach.adminRows > 0) {
      evidence.finding = `El implantólogo ve ${ui.rows.length} análisis y la tira de KPIs en ${ui.kpis.total}, mientras el administrador ve ${evidence.reach.adminRows} de los mismos pacientes: la política de alcance (#214) filtra por relación clínica y el estado vacío afirma «Sin análisis registrados» como si no existiera ninguno.`
    }
  } finally {
    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    audit('walk-ai-implantologo.json', evidence)
  }

  expect(evidence.shots, 'capture of the specialist AI surface').toHaveLength(1)
  expect(evidence.pageErrors, 'page errors on the specialist AI surface').toEqual([])
})
