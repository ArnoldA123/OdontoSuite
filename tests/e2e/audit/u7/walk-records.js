// Plan #13 (issue #370, U7a) — layer 4: the clinical history, walked.
//
// One walkthrough as `administrador`, the role that holds the whole write set of
// the module, plus one short row as `asistente` (she holds `manage.medical_record`
// and none of the specialty or AI permissions). Every step carries its expected
// value, the observed one, the request it caused, a capture and a verdict.
//
// The session's *empty* patient is walked first, so the four empty tab states of
// #370 are observed before the walkthrough creates their first history.

import { test, expect } from 'playwright/test'

import { classifyFailures, collectDiagnostics, loginAs, primeSession } from '../harness.js'

import { ensureFixtures, fixtureSummary, recordFixture, registerCreated } from './fixtures.js'

import {
  ACTION_TIMEOUT,
  DESKTOP,
  LOAD_TIMEOUT,
  RECORDS_ROUTE,
  addDaysLocal,
  audit,
  call,
  closeAllDialogs,
  dismissToasts,
  fillControl,
  lastWrite,
  localDay,
  messageOf,
  openSelect,
  pngBuffer,
  recorder,
  settleRecordsLanding,
  settleRecordsWorkspace,
  stamp,
  uploadPayload,
  waitForCall,
  waitForToast,
  walkTotals,
  watchRequests
} from './helpers.js'

const DAY = localDay()

const readTabState = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const empties = Array.from(
      document.querySelectorAll('[data-state^="empty"], [data-state^="no-record"]')
    ).map(node => ({
      hook: node.getAttribute('data-state'),
      text: text(node),
      actions: Array.from(node.querySelectorAll('button')).map(button => text(button))
    }))
    const tabs = Array.from(document.querySelectorAll('[role="tab"]')).map(tab => ({
      id: tab.getAttribute('id'),
      label: text(tab.querySelector('span.truncate')),
      selected: tab.getAttribute('aria-selected') === 'true'
    }))

    return {
      active: tabs.find(tab => tab.selected)?.label ?? null,
      empties,
      emptyCount: empties.length,
      actionCount: empties.reduce((total, entry) => total + entry.actions.length, 0)
    }
  })

const tabButton = (page, label) => page.locator('[role="tab"]').filter({ hasText: label }).first()

test('@U7 · U7a historia clínica: tabs, alta, evolución, adjunto, addendum y stats', async ({
  page,
  request
}) => {
  test.setTimeout(900000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const record = recordFixture(fixtures)
  const emptyPatient = fixtures.patients.empty

  const diagnostics = collectDiagnostics(page)
  const state = { pageErrors: [], currentStep: 'setup' }
  page.on('pageerror', error =>
    state.pageErrors.push({ message: error.message, step: state.currentStep })
  )

  const evidence = {
    route: RECORDS_ROUTE,
    role: 'administrador',
    ...stamp(),
    shotDir: 'walk/records',
    scenario:
      'abrir el índice, recorrer los cuatro tabs del paciente vacío, crear la historia desde el CTA, ' +
      'registrar una evolución vacía y una completa con mediciones, validar y subir un adjunto, ' +
      'corregir con addendum, leer las estadísticas y comprobar que no existe edición ni borrado',
    fixtures: fixtureSummary(fixtures),
    steps: [],
    findings: [],
    requests: [],
    pageErrors: [],
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const step = recorder(page, evidence, state)
  const requests = watchRequests(page)
  const created = { recordId: null, evolutionIds: [], attachmentIds: [] }
  const emptyTabs = []

  // Dialogs are modal overlays: a native alert() blocks the page and Playwright
  // would hang on the next action. The walk records the text of any dialog the
  // app raises instead of dismissing it silently.
  const dialogs = []
  page.on('dialog', async dialog => {
    dialogs.push({ type: dialog.type(), message: dialog.message() })
    await dialog.dismiss().catch(() => {})
  })

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    // --- 01: the recent-histories index -----------------------------------
    const landingLoad = waitForCall(page, 'GET', '/api/medical-records', () => true, LOAD_TIMEOUT)
    await page.goto(RECORDS_ROUTE)
    await landingLoad
    await settleRecordsLanding(page)

    await step(
      'index',
      'abrir /medical-records sin paciente seleccionado',
      'la lista de historias recientes coincide con GET /api/medical-records y el resumen no finge datos',
      async () => {
        const ui = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
          const kpi = key => text(document.querySelector(`[data-stat="${key}"]`))

          return {
            rows: document.querySelectorAll('[data-recent-list] [data-recent-row]').length,
            kpis: {
              records: kpi('records'),
              evolutions: kpi('evolutions'),
              attachments: kpi('attachments'),
              followUps: kpi('follow-ups')
            },
            summaryCaption: text(document.querySelector('[data-medical-records-block="summary"]')),
            newRecordDisabled: document.querySelector('[data-new-record-button]')?.disabled ?? null
          }
        })
        const api = await call(request, session, 'GET', '/api/medical-records?per_page=10')
        const apiRows = Array.isArray(api.body?.data) ? api.body.data.length : null
        const dashes = Object.values(ui.kpis).filter(value => value === '—').length

        return {
          requestStatus: api.status,
          obtained: `${ui.rows} filas visibles, ${apiRows} en la API; KPI con «—»: ${dashes}/4; «Nueva historia» disabled=${ui.newRecordDisabled}`,
          verdict: ui.rows === apiRows ? 'OK' : 'DEFECTO',
          detail:
            dashes === 4
              ? `El bloque «Resumen clínico» pinta cuatro tarjetas con «—» (${JSON.stringify(ui.kpis)}) mientras no hay paciente seleccionado: ocupa la primera pantalla sin dato y sin explicación.`
              : null,
          extra: { ui, apiRows }
        }
      }
    )

    // --- 02: the empty patient and its four tabs ---------------------------
    const emptyLoad = waitForCall(
      page,
      'GET',
      '/api/medical-records',
      params => params.get('patient_id') === String(emptyPatient.id),
      LOAD_TIMEOUT
    )
    await page.goto(`${RECORDS_ROUTE}?patient_id=${emptyPatient.id}`)
    await emptyLoad
    await settleRecordsWorkspace(page)

    for (const label of ['Historia general', 'Evoluciones', 'Adjuntos', 'Estadísticas']) {
      await step(
        `empty-tab-${label.toLowerCase().replace(/\s+/g, '-')}`,
        `abrir la tab «${label}» del paciente sin historia`,
        'el estado vacío explica qué falta y ofrece la acción que lo resuelve',
        async () => {
          const clicked = await tabButton(page, label)
            .click({ timeout: 10000 })
            .then(() => true)
            .catch(() => false)
          await page.waitForTimeout(250)
          const tabState = await readTabState(page)
          emptyTabs.push({ label, ...tabState })

          return {
            obtained: `${tabState.emptyCount} estado(s) vacío(s), ${tabState.actionCount} acción(es): ${JSON.stringify(tabState.empties.map(entry => entry.actions))}`,
            verdict: !clicked
              ? 'DEFECTO'
              : tabState.emptyCount === 0
                ? 'DEFECTO'
                : tabState.actionCount > 0
                  ? 'OK'
                  : 'DEFECTO',
            detail:
              tabState.actionCount === 0
                ? `El estado vacío de «${label}» dice qué hacer pero no ofrece ningún control: ${JSON.stringify(tabState.empties.map(entry => entry.text))}`
                : null,
            extra: { tabState }
          }
        }
      )
    }

    // --- 03: create the history from the empty tab CTA ---------------------
    await step(
      'create-record',
      'pulsar «Crear historia» en la tab general vacía y guardar el formulario sin llenar',
      'la historia se crea con el paciente fijado y aparece como primera fila del tab general',
      async () => {
        await tabButton(page, 'Historia general').click({ timeout: 10000 })
        const cta = page.locator('[data-state="empty-records-panel"] button').first()
        const ctaVisible = await cta
          .waitFor({ state: 'visible', timeout: 8000 })
          .then(() => true)
          .catch(() => false)

        if (!ctaVisible) {
          return {
            obtained: 'el estado vacío de la tab general no ofrece CTA',
            verdict: 'DEFECTO'
          }
        }

        await cta.click({ timeout: 10000 })
        await page
          .locator('[data-medical-record-form]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})

        const submitted = waitForCall(
          page,
          'POST',
          '/api/medical-records',
          () => true,
          ACTION_TIMEOUT
        )
        const toast = waitForToast(page, 'Historia clínica creada', 12000)
        await page.locator('[data-modal-submit]').click({ timeout: 10000 })
        const response = await submitted
        await toast
        await settleRecordsWorkspace(page)

        const status = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null
        created.recordId = body?.data?.id ?? null
        if (created.recordId) registerCreated('medical-record', { id: created.recordId })

        const cards = await page.locator('[data-record-list] > *').count()

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `POST /api/medical-records → ${status}; ${cards} historia(s) en la tab general`,
          verdict: status === 201 && cards >= 1 ? 'OK' : 'DEFECTO',
          detail:
            status === 201 && cards >= 1
              ? 'El formulario se envía con el paciente fijado y sin ningún campo clínico: es el alta mínima del contrato (sólo el paciente es obligatorio).'
              : null,
          extra: { recordId: created.recordId }
        }
      }
    )

    // --- 04: the evolution form, submitted empty --------------------------
    await step(
      'evolution-empty-submit',
      'abrir «Nueva evolución» y enviar el formulario vacío',
      'el formulario exige al menos un bloque SOAP antes de crear una evolución sin contenido',
      async () => {
        await tabButton(page, 'Evoluciones').click({ timeout: 10000 })
        await page.waitForTimeout(300)
        const open = page.locator('[data-add-evolution-empty], [data-add-evolution]').first()
        await open.click({ timeout: 10000 })
        await page
          .locator('[data-evolution-form]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})

        // 3 s: the form refuses the empty act locally, so a long wait only
        // stalls the walk when no request leaves the SPA.
        const submitted = waitForCall(
          page,
          'POST',
          /\/api\/medical-records\/\d+\/evolutions/,
          () => true,
          3000
        )
        await page.locator('[data-evolution-submit]').click({ timeout: 10000 })
        const response = await submitted
        const status = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null

        // #425: the empty act is refused, so the door that matters is the
        // inline message the form paints — and the fact that no request left
        // the SPA at all.
        const inline = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')

          return {
            error: text(document.querySelector('[data-evolution-error]')),
            formOpen: Boolean(document.querySelector('[data-evolution-form]')),
            cards: document.querySelectorAll('[data-evolution-card]').length
          }
        })

        if (body?.data?.id) created.evolutionIds.push(body.data.id)
        await closeAllDialogs(page)
        await page.waitForTimeout(600)

        const cards = await page.locator('[data-evolution-card]').count()
        const emptyCards = await page
          .evaluate(
            () =>
              Array.from(document.querySelectorAll('[data-evolution-card]')).filter(
                card => card.querySelectorAll('[data-soap-block]').length === 0
              ).length
          )
          .catch(() => null)

        const refused = status === null || status === 422
        const explained = inline.error.length > 0
        const stayedOpen = inline.formOpen === true
        const nothingWritten =
          (body?.data?.id ?? null) === null && (emptyCards === null || emptyCards === 0)

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `POST …/evolutions → ${status ?? 'sin petición'}; mensaje en línea=${JSON.stringify(inline.error)}; modal abierto=${inline.formOpen}; ${cards} tarjeta(s) en el timeline, ${emptyCards} sin ningún bloque SOAP`,
          verdict: refused && explained && stayedOpen && nothingWritten ? 'OK' : 'DEFECTO',
          detail:
            status === 201
              ? 'Ningún campo SOAP es obligatorio: el envío vacío crea una evolución sin subjetivo, objetivo, evaluación ni plan, y el timeline la pinta como una tarjeta con sólo fecha y autor. El registro clínico gana ruido que ninguna puerta vuelve a corregir (la historia es inmutable).'
              : !explained
                ? 'El envío vacío se rechaza sin decir por qué: el formulario no pinta el motivo junto al bloque SOAP.'
                : !stayedOpen
                  ? 'El formulario se cerró con el envío rechazado: el usuario pierde lo que había escrito y el motivo no tiene dónde leerse.'
                  : null,
          extra: { evolutionId: body?.data?.id ?? null, cards, emptyCards, inline }
        }
      }
    )

    // --- 05: a complete evolution with measurements -----------------------
    await step(
      'evolution-create',
      'registrar una evolución con SOAP, signos vitales, mediciones clínicas y seguimiento',
      'la evolución se guarda (201) y el timeline muestra los cuatro bloques SOAP y las cinco mediciones',
      async () => {
        await page.locator('[data-add-evolution]').first().click({ timeout: 10000 })
        await page
          .locator('[data-evolution-form]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})

        await fillControl(
          page,
          '[data-evolution-field="subjective"] textarea',
          'AUDIT-U7 refiere sensibilidad al frío en el 46'
        )
        await fillControl(
          page,
          '[data-evolution-field="objective"] textarea',
          'AUDIT-U7 caries oclusal profunda sin exposición pulpar'
        )
        await fillControl(
          page,
          '[data-evolution-field="assessment"] textarea',
          'AUDIT-U7 pulpitis reversible'
        )
        await fillControl(
          page,
          '[data-evolution-field="plan"] textarea',
          'AUDIT-U7 restauración en la próxima sesión'
        )
        await fillControl(page, '[data-evolution-field="pocket_depth_mm"] input', '3.5')
        await fillControl(page, '[data-evolution-field="gingival_recession_mm"] input', '1.2')
        await fillControl(page, '[data-evolution-field="plaque_index_percent"] input', '35')
        await fillControl(page, '[data-evolution-field="tooth_mobility"] input', '1')
        await openSelect(
          page,
          '[data-evolution-field="bleeding_on_probing"] button[aria-haspopup="true"]',
          'Sí'
        )

        await page
          .locator('[data-evolution-follow-up-toggle]')
          .check({ timeout: 8000 })
          .catch(() => {})
        await fillControl(
          page,
          '[data-evolution-field="follow_up_date"] input',
          addDaysLocal(DAY, 1)
        )
        await fillControl(page, '[data-evolution-field="blood_pressure"] input', '120/80')
        await fillControl(page, '[data-evolution-field="heart_rate"] input', '72')

        const submitted = waitForCall(
          page,
          'POST',
          /\/api\/medical-records\/\d+\/evolutions/,
          () => true,
          ACTION_TIMEOUT
        )
        await page.locator('[data-evolution-submit]').click({ timeout: 10000 })
        const response = await submitted
        const status = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null

        if (body?.data?.id) created.evolutionIds.push(body.data.id)
        await page.waitForTimeout(900)

        const measured = await page.evaluate(() => {
          const cards = Array.from(document.querySelectorAll('[data-evolution-card]'))
          const card = cards.find(node => node.querySelectorAll('[data-soap-block]').length >= 4)
          if (!card) return null

          return {
            soap: card.querySelectorAll('[data-soap-block]').length,
            measurements: Array.from(card.querySelectorAll('[data-evolution-measurement]')).map(
              node => (node.innerText ?? '').trim()
            ),
            vitals: Boolean(card.querySelector('[data-evolution-vitals]')),
            followUp: Boolean(card.querySelector('[data-evolution-follow-up]')),
            id: Number(card.getAttribute('data-evolution-id'))
          }
        })

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `POST …/evolutions → ${status}; tarjeta con ${measured?.soap ?? 0} bloques SOAP, ${measured?.measurements.length ?? 0} mediciones, signos=${measured?.vitals}, seguimiento=${measured?.followUp}`,
          verdict:
            status === 201 &&
            (measured?.soap ?? 0) === 4 &&
            (measured?.measurements.length ?? 0) === 5
              ? 'OK'
              : 'DEFECTO',
          detail:
            status === 201 && (measured?.measurements.length ?? 0) === 5
              ? null
              : 'la evolución guardada no se lee completa en el timeline',
          extra: { measured, evolutionId: body?.data?.id ?? null }
        }
      }
    )

    // --- 06: the attachment gate -----------------------------------------
    await step(
      'attachment-validation',
      'abrir «Subir adjunto» y enviarlo vacío; después elegir un archivo de más de 10 MB',
      'el formulario rechaza el envío vacío y el archivo que supera el límite, con el mensaje junto al campo',
      async () => {
        await tabButton(page, 'Adjuntos').click({ timeout: 10000 })
        await page.waitForTimeout(300)
        const upload = page
          .locator('[data-upload-attachment], [data-state="empty-attachments"] button')
          .first()
        await upload.click({ timeout: 10000 })
        await page
          .locator('[data-upload-attachment-form]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})

        await page.locator('[data-upload-submit]').click({ timeout: 10000 })
        await page.waitForTimeout(400)

        const emptyErrors = await page.evaluate(() =>
          Array.from(document.querySelectorAll('[data-upload-error]')).map(node => ({
            field: node.getAttribute('data-upload-error'),
            text: (node.innerText ?? '').trim()
          }))
        )

        const oversize = Buffer.alloc(10 * 1024 * 1024 + 2048, 1)
        await page.locator('[data-upload-file-input]').setInputFiles({
          name: 'audit-u7-oversize.png',
          mimeType: 'image/png',
          buffer: oversize
        })
        await page.waitForTimeout(500)

        const sizeError = await page
          .locator('[data-upload-error="file"]')
          .innerText({ timeout: 4000 })
          .catch(() => '')

        return {
          obtained: `errores del envío vacío: ${JSON.stringify(emptyErrors.map(entry => entry.field))}; error del archivo grande: ${JSON.stringify(sizeError)}`,
          verdict: emptyErrors.length >= 2 && /10 MB/.test(sizeError) ? 'OK' : 'DEFECTO',
          detail:
            emptyErrors.length >= 2 && /10 MB/.test(sizeError)
              ? null
              : 'la validación local del adjunto no cubre uno de los dos casos',
          extra: { emptyErrors, sizeError }
        }
      }
    )

    // --- 07: upload a real attachment ------------------------------------
    await step(
      'attachment-upload',
      'elegir un PNG, la categoría «Radiografía» y subirlo',
      'el adjunto se sube (201), la galería lo pinta con su categoría y el contador sube',
      async () => {
        const category = await openSelect(
          page,
          '[data-upload-category] button[aria-haspopup="true"]',
          'Radiografía'
        )

        await page
          .locator('[data-upload-file-input]')
          .setInputFiles(
            uploadPayload(`audit-u7-walk-${fixtures.token}.png`, pngBuffer(64, 64, [120, 200, 160]))
          )
        await page.waitForTimeout(400)

        const submitted = waitForCall(
          page,
          'POST',
          '/api/medical-records/attachments',
          () => true,
          ACTION_TIMEOUT
        )
        await page.locator('[data-upload-submit]').click({ timeout: 10000 })
        const response = await submitted
        const status = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null

        if (body?.data?.id) created.attachmentIds.push(body.data.id)
        await page.waitForTimeout(800)

        const gallery = await page.evaluate(() => ({
          cards: document.querySelectorAll('[data-attachment-id]').length,
          count: (document.querySelector('[data-attachment-count]')?.innerText ?? '').trim(),
          categories: Array.from(document.querySelectorAll('[data-attachment-id]')).map(card =>
            card.getAttribute('data-attachment-category')
          )
        }))

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `POST /api/medical-records/attachments → ${status}; ${gallery.cards} tarjeta(s), contador «${gallery.count}»`,
          verdict: status === 201 && gallery.cards > 0 && category.ok ? 'OK' : 'DEFECTO',
          detail:
            status === 201 && gallery.cards > 0
              ? null
              : `la galería no refleja el adjunto subido (categoría elegida: ${JSON.stringify(category)})`,
          extra: { gallery, category, attachmentId: body?.data?.id ?? null }
        }
      }
    )

    // --- 08: preview + category filter -----------------------------------
    await step(
      'attachment-preview-and-filter',
      'abrir la vista previa de la imagen y filtrar la galería por categoría',
      'la imagen abre en un modal y el filtro reduce el contador al subconjunto de la categoría',
      async () => {
        await page.locator('[data-attachment-preview-trigger]').first().click({ timeout: 10000 })
        const previewOpen = await page
          .locator('[data-attachment-preview]')
          .waitFor({ state: 'visible', timeout: 8000 })
          .then(() => true)
          .catch(() => false)
        await page.keyboard.press('Escape').catch(() => {})
        await page.waitForTimeout(300)

        const filtered = await openSelect(
          page,
          '[data-category-filter] button[aria-haspopup="true"]',
          'Foto clínica'
        )
        await page.waitForTimeout(400)

        const after = await page.evaluate(() => ({
          cards: document.querySelectorAll('[data-attachment-id]').length,
          count: (document.querySelector('[data-attachment-count]')?.innerText ?? '').trim(),
          emptyFiltered: Boolean(document.querySelector('[data-state="empty-attachments-filter"]'))
        }))

        await openSelect(page, '[data-category-filter] button[aria-haspopup="true"]', 'Todas')
        await page.waitForTimeout(300)

        return {
          obtained: `vista previa=${previewOpen}; filtro «Foto clínica» → ${after.cards} tarjeta(s) (contador «${after.count}»), vacío=${after.emptyFiltered}`,
          verdict: previewOpen && filtered.ok ? 'OK' : 'DEFECTO',
          detail:
            previewOpen && filtered.ok
              ? after.emptyFiltered
                ? 'La categoría filtrada no tiene adjuntos de la sesión: el estado «Sin adjuntos en esta categoría» aparece con su acción «Ver todas».'
                : null
              : 'la vista previa o el filtro de categoría no respondieron',
          extra: { previewOpen, filtered, after }
        }
      }
    )

    // --- 09: the addendum on the record ----------------------------------
    await step(
      'addendum-record',
      'abrir el detalle de la historia y registrar un addendum con motivo y contenido',
      'el original no se edita: el addendum se agrega firmado y el detalle lo muestra junto a la historia',
      async () => {
        await tabButton(page, 'Historia general').click({ timeout: 10000 })
        await page.waitForTimeout(300)
        await page.locator('[data-record-action="view"]').first().click({ timeout: 10000 })
        await page
          .locator('[data-medical-record-detail]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})

        await page.locator('[data-detail-action="addendum"]').first().click({ timeout: 10000 })
        await page
          .locator('[data-addendum-form]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})

        await page.locator('[data-addendum-submit]').click({ timeout: 10000 })
        await page.waitForTimeout(400)
        const emptyErrors = await page.evaluate(() =>
          Array.from(document.querySelectorAll('[data-addendum-form] p'))
            .map(node => (node.innerText ?? '').trim())
            .filter(text => text.length > 0)
        )

        await fillControl(
          page,
          '[data-addendum-field="reason"] input',
          'AUDIT-U7 el diagnóstico omitía la pieza 47'
        )
        await fillControl(
          page,
          '[data-addendum-field="content"] textarea',
          'AUDIT-U7 se agrega que el 47 presenta una lesión incipiente en la cara distal.'
        )

        const submitted = waitForCall(
          page,
          'POST',
          /\/api\/medical-records\/\d+\/addenda/,
          () => true,
          ACTION_TIMEOUT
        )
        await page.locator('[data-addendum-submit]').click({ timeout: 10000 })
        const response = await submitted
        const status = response?.status() ?? null
        await page.waitForTimeout(1200)

        await page.locator('[data-record-action="view"]').first().click({ timeout: 10000 })
        await page
          .locator('[data-medical-record-detail]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})
        // The detail paints the list row first and refreshes it with
        // GET /api/medical-records/{id}, which is where the addenda travel.
        await page
          .waitForFunction(
            () =>
              Boolean(document.querySelector('[data-detail-section="addenda"] [data-addendum-id]')),
            undefined,
            { timeout: 8000, polling: 120 }
          )
          .catch(() => {})
        const addenda = await page
          .evaluate(() => {
            const blocks = Array.from(
              document.querySelectorAll('[data-detail-section="addenda"] [data-addendum-id]')
            )
            return blocks.map(block => ({
              reason: (block.querySelector('[data-addendum-reason]')?.innerText ?? '').trim(),
              meta: (block.querySelector('[data-addendum-meta]')?.innerText ?? '').trim()
            }))
          })
          .catch(() => [])

        await closeAllDialogs(page)

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `envío vacío → ${emptyErrors.length} error(es) local(es); POST …/addenda → ${status}; ${addenda.length} addendum(s) en el detalle`,
          verdict:
            status === 201 && addenda.length > 0 && emptyErrors.length >= 2 ? 'OK' : 'DEFECTO',
          detail:
            status === 201 && addenda.length > 0
              ? null
              : 'el addendum no quedó visible junto a la historia',
          extra: { emptyErrors, addenda }
        }
      }
    )

    // --- 10: the addendum on an evolution --------------------------------
    await step(
      'addendum-evolution',
      'registrar un addendum sobre una evolución concreta desde el timeline',
      'la corrección viaja con la evolución corregida y la tarjeta la muestra',
      async () => {
        await tabButton(page, 'Evoluciones').click({ timeout: 10000 })
        await page.waitForTimeout(400)

        const target = page
          .locator('[data-evolution-card]')
          .filter({ has: page.locator('[data-soap-block="assessment"]') })
          .first()
        await target.locator('[data-evolution-addendum]').click({ timeout: 10000 })
        await page
          .locator('[data-addendum-form]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})

        const context = await page
          .locator('[data-addendum-context]')
          .innerText({ timeout: 4000 })
          .catch(() => '')

        await fillControl(
          page,
          '[data-addendum-field="reason"] input',
          'AUDIT-U7 error de digitación en la evaluación'
        )
        await fillControl(
          page,
          '[data-addendum-field="content"] textarea',
          'AUDIT-U7 la evaluación correcta es pulpitis irreversible.'
        )

        const submitted = waitForCall(
          page,
          'POST',
          /\/api\/medical-records\/evolutions\/\d+\/addenda/,
          () => true,
          ACTION_TIMEOUT
        )
        await page.locator('[data-addendum-submit]').click({ timeout: 10000 })
        const response = await submitted
        const status = response?.status() ?? null
        await page.waitForTimeout(1000)

        const addenda = await page
          .evaluate(() =>
            Array.from(document.querySelectorAll('[data-evolution-addenda] [data-addendum]')).map(
              block => (block.querySelector('[data-addendum-reason]')?.innerText ?? '').trim()
            )
          )
          .catch(() => [])

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `contexto «${context.replace(/\s+/g, ' ').slice(0, 60)}»; POST …/evolutions/{id}/addenda → ${status}; ${addenda.length} addendum(s) en el timeline`,
          verdict: status === 201 && addenda.length > 0 ? 'OK' : 'DEFECTO',
          detail:
            status === 201 && addenda.length > 0
              ? null
              : 'la corrección de la evolución no aparece en su tarjeta',
          extra: { addenda, context }
        }
      }
    )

    // --- 11: the statistics tab -----------------------------------------
    await step(
      'stats',
      'abrir la tab «Estadísticas»',
      'las cifras del tab coinciden con GET /api/medical-records/patient/{id}/stats',
      async () => {
        await tabButton(page, 'Estadísticas').click({ timeout: 10000 })
        await page.waitForTimeout(900)

        const ui = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
          const card = key => text(document.querySelector(`[data-stat="${key}"]`))
          const bars = Array.from(
            document.querySelectorAll('[data-attachment-bars] [data-attachment-category]')
          ).map(node => ({
            category: node.getAttribute('data-attachment-category'),
            text: text(node)
          }))

          return {
            records: card('records'),
            evolutions: card('evolutions'),
            attachments: card('attachments'),
            bars,
            lastEvolution: text(document.querySelector('[data-last-evolution-date]')),
            specialties: Array.from(document.querySelectorAll('[data-specialty-chip]')).map(text),
            scopeNote: text(document.querySelector('[data-stats-scope]'))
          }
        })
        const api = await call(
          request,
          session,
          'GET',
          `/api/medical-records/patient/${emptyPatient.id}/stats`
        )
        const stats = api.body?.data ?? {}

        return {
          requestStatus: api.status,
          obtained: `UI: ${ui.records} historias / ${ui.evolutions} evoluciones / ${ui.attachments} adjuntos; API: ${stats.total_records} / ${stats.total_evolutions} / ${stats.total_attachments}`,
          verdict:
            Number(ui.records) === stats.total_records &&
            Number(ui.evolutions) === stats.total_evolutions &&
            Number(ui.attachments) === stats.total_attachments
              ? 'OK'
              : 'DEFECTO',
          detail:
            Number(ui.attachments) === stats.total_attachments
              ? null
              : 'las tarjetas del resumen no coinciden con el payload de estadísticas',
          extra: { ui, api: stats }
        }
      }
    )

    // --- 12: no edit and no delete for clinical content ------------------
    await step(
      'no-edit-delete',
      'buscar controles de edición o borrado en la historia, su detalle y sus evoluciones, y probar las rutas',
      'la historia clínica no se edita ni se borra: ningún control lo ofrece y la API no publica la ruta',
      async () => {
        await tabButton(page, 'Historia general').click({ timeout: 10000 })
        await page.waitForTimeout(300)

        const cardButtons = await page.evaluate(() =>
          Array.from(document.querySelectorAll('[data-record-list] button')).map(
            node => (node.innerText ?? '').trim() || node.getAttribute('aria-label')
          )
        )
        await page.locator('[data-record-action="view"]').first().click({ timeout: 10000 })
        await page.waitForTimeout(500)
        const detailButtons = await page.evaluate(() =>
          Array.from(document.querySelectorAll('[role="dialog"] button'))
            .map(node => (node.innerText ?? '').trim() || node.getAttribute('aria-label'))
            .filter(Boolean)
        )
        await closeAllDialogs(page)

        await tabButton(page, 'Evoluciones').click({ timeout: 10000 })
        await page.waitForTimeout(400)
        const evolutionButtons = await page.evaluate(() =>
          Array.from(document.querySelectorAll('[data-evolution-card] button')).map(
            node => (node.innerText ?? '').trim() || node.getAttribute('aria-label')
          )
        )

        const summary = [cardButtons, detailButtons, evolutionButtons]
          .flat()
          .filter(Boolean)
          .filter(label => /editar|eliminar|borrar|delete/i.test(label))

        const methodStatuses = {}

        for (const method of ['PUT', 'PATCH', 'DELETE']) {
          const response = await call(
            request,
            session,
            method,
            `/api/medical-records/${created.recordId ?? record.id}`,
            {}
          )
          methodStatuses[method] = {
            status: response.status,
            message: messageOf(response.body)
          }
        }

        const all405 = Object.values(methodStatuses).every(entry => entry.status === 405)

        return {
          requestStatus: methodStatuses.DELETE.status,
          obtained: `${summary.length} control(es) de edición/borrado visibles; PUT/PATCH/DELETE → ${Object.values(
            methodStatuses
          )
            .map(entry => entry.status)
            .join('/')}`,
          verdict: summary.length === 0 && all405 ? 'OK' : 'DEFECTO',
          detail:
            summary.length === 0 && all405
              ? 'La superficie no ofrece edición ni borrado y las tres rutas responden 405: el contrato de la historia inmutable se sostiene de punta a punta.'
              : `controles: ${JSON.stringify(summary)}; rutas: ${JSON.stringify(methodStatuses)}`,
          extra: { summary, methodStatuses }
        }
      }
    )

    // --- 13: back to the index ------------------------------------------
    await step(
      'back-to-index',
      'pulsar «Quitar selección»',
      'la página vuelve al índice de historias recientes y limpia el paciente del query',
      async () => {
        await page.locator('[data-clear-selection]').click({ timeout: 10000 })
        await settleRecordsLanding(page)
        await page.waitForTimeout(400)

        const url = page.url()
        const back = await page.locator('[data-recent-list]').count()

        return {
          obtained: `url ${url}; lista reciente presente=${back > 0}`,
          verdict: back > 0 && !url.includes('patient_id=') ? 'OK' : 'DEFECTO',
          detail:
            back > 0 && !url.includes('patient_id=')
              ? null
              : 'la selección no vuelve al índice o deja el paciente en la URL',
          extra: { url }
        }
      }
    )

    // --- 14: the patient that carries the fixtures -----------------------
    await step(
      'clinical-patient-tabs',
      'abrir la historia del paciente con datos y recorrer sus cuatro tabs',
      'cada tab pinta el contenido del expediente y las cifras coinciden con la API',
      async () => {
        await page.goto(`${RECORDS_ROUTE}/${record.id}`)
        await settleRecordsWorkspace(page)
        await page.waitForTimeout(700)

        const surfaces = {}

        for (const label of ['Historia general', 'Evoluciones', 'Adjuntos', 'Estadísticas']) {
          await tabButton(page, label).click({ timeout: 10000 })
          await page.waitForTimeout(500)
          surfaces[label] = await page.evaluate(() => ({
            records: document.querySelectorAll('[data-record-list] > *').length,
            evolutions: document.querySelectorAll('[data-evolution-card]').length,
            measurements: document.querySelectorAll('[data-evolution-measurement]').length,
            attachments: document.querySelectorAll('[data-attachment-id]').length,
            statsBlocks: document.querySelectorAll('[data-stats-block]').length,
            empty: Array.from(
              document.querySelectorAll('[data-state^="empty"], [data-state^="no-record"]')
            ).map(node => node.getAttribute('data-state'))
          }))
        }

        const api = await call(
          request,
          session,
          'GET',
          `/api/medical-records/${record.id}/evolutions`
        )
        const evolutions = Array.isArray(api.body?.data) ? api.body.data.length : null
        const timeline = surfaces['Evoluciones'].evolutions

        return {
          requestStatus: api.status,
          obtained: `general ${surfaces['Historia general'].records} / evoluciones ${timeline} (API ${evolutions}) / adjuntos ${surfaces['Adjuntos'].attachments} / bloques de stats ${surfaces['Estadísticas'].statsBlocks}`,
          verdict:
            surfaces['Historia general'].records > 0 &&
            timeline === evolutions &&
            surfaces['Adjuntos'].attachments > 0 &&
            surfaces['Estadísticas'].statsBlocks > 0
              ? 'OK'
              : 'DEFECTO',
          detail: null,
          extra: { surfaces, apiEvolutions: evolutions }
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
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    evidence.nativeDialogs = dialogs
    evidence.emptyTabs = emptyTabs
    evidence.created = created
    evidence.totals = walkTotals(evidence)
    evidence.shots = evidence.steps.map(entry => entry.shot).filter(Boolean)

    audit('walk-records.json', evidence)
  }

  expect(evidence.steps.length, 'steps of the history walkthrough').toBeGreaterThanOrEqual(16)
  expect(
    evidence.steps.filter(entry => entry.shot === null),
    'steps without a capture'
  ).toEqual([])
  expect(state.pageErrors, 'page errors during the history walkthrough').toEqual([])
})

// --- the assistant's write set ---------------------------------------------

test('@U7 · U7a historia: el asistente puede crear la historia y no ve la especialidad ni la IA', async ({
  page,
  request
}) => {
  test.setTimeout(300000)

  const session = await loginAs(request, 'asistente')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const diagnostics = collectDiagnostics(page)
  const state = { pageErrors: [], currentStep: 'setup' }
  page.on('pageerror', error =>
    state.pageErrors.push({ message: error.message, step: state.currentStep })
  )

  const evidence = {
    route: RECORDS_ROUTE,
    role: 'asistente',
    ...stamp(),
    shotDir: 'walk/records-asistente',
    scenario:
      'la asistente abre la historia del paciente de la sesión, ve la creación de historia y el alta de evolución, ' +
      'y no ve las entradas de especialidades ni de IA',
    fixtures: fixtureSummary(fixtures),
    steps: [],
    findings: [],
    pageErrors: [],
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const step = recorder(page, evidence, state)

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    await step(
      'assistant-workspace',
      'abrir la historia del paciente de la sesión',
      'la asistente lee el expediente y conserva el alta de historia y de evolución',
      async () => {
        await page.goto(`${RECORDS_ROUTE}/${fixtures.record.id}`)
        await settleRecordsWorkspace(page)
        await page.waitForTimeout(600)

        const ui = await page.evaluate(() => {
          return {
            newRecord: Boolean(document.querySelector('[data-new-record-button]')),
            newRecordDisabled: document.querySelector('[data-new-record-button]')?.disabled ?? null,
            tabs: Array.from(document.querySelectorAll('[role="tab"]')).length,
            sidebar: Array.from(document.querySelectorAll('#primary-sidebar a[href]')).map(link =>
              link.getAttribute('href')
            )
          }
        })

        const clinicalEntries = ['/specialty-records', '/ai-analysis'].filter(route =>
          ui.sidebar.includes(route)
        )

        return {
          obtained: `tabs=${ui.tabs}; «Nueva historia»=${ui.newRecord} (disabled=${ui.newRecordDisabled}); entradas clínicas fuera de su escritorio: ${JSON.stringify(clinicalEntries)}`,
          verdict: ui.tabs === 4 && clinicalEntries.length === 0 ? 'OK' : 'DEFECTO',
          detail:
            clinicalEntries.length === 0
              ? null
              : `la navegación ofrece a la asistente ${clinicalEntries.join(', ')}`,
          extra: { ui, clinicalEntries }
        }
      }
    )

    await step(
      'assistant-evolution',
      'abrir el formulario de evolución del paciente',
      'la asistente puede documentar una evolución (manage.evolution) y el formulario abre',
      async () => {
        await tabButton(page, 'Evoluciones').click({ timeout: 10000 })
        await page.waitForTimeout(500)
        const trigger = page.locator('[data-add-evolution], [data-add-evolution-empty]').first()
        const visible = await trigger
          .waitFor({ state: 'visible', timeout: 6000 })
          .then(() => true)
          .catch(() => false)

        if (visible) await trigger.click({ timeout: 8000 })
        const opened = await page
          .locator('[data-evolution-form]')
          .waitFor({ state: 'visible', timeout: 8000 })
          .then(() => true)
          .catch(() => false)
        await closeAllDialogs(page)

        return {
          obtained: `botón visible=${visible}; formulario abierto=${opened}`,
          verdict: opened ? 'OK' : 'DEFECTO',
          detail: opened ? null : 'la asistente no alcanza el formulario de evolución',
          extra: { visible, opened }
        }
      }
    )
  } finally {
    await closeAllDialogs(page).catch(() => {})
    await dismissToasts(page)

    evidence.pageErrors = state.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'member')
    evidence.totals = walkTotals(evidence)
    audit('walk-records-asistente.json', evidence)
  }

  expect(evidence.steps.length, 'steps of the assistant record walkthrough').toBe(2)
  expect(state.pageErrors, 'page errors during the assistant walkthrough').toEqual([])
})
