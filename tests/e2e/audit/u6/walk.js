// Plan #13 (issue #369, U6) — layer 4, presupuestos.
//
// One walkthrough as `administrador` (the only role with the whole write set):
// open the create form, submit it empty, fill a catalog line with its site and
// its convenio, read the live summary, save, re-read the detail costs, download
// the PDF and parse its money rows, reject with and without a reason, approve,
// and prove the block on editing and deciding an already-approved document.
//
// Every step is measured against a promise and the walk keeps going after a
// broken one: the verdict vocabulary of S8 (`OK`, `DEFECTO`, `NO-OP`, `CONFUSO`,
// `NO PROBADO`) and the request status are part of the row. Product defects stay
// in the evidence; the only assertion is that the instrument ran (every step has
// a verdict and a capture) and that no page error escaped the declared rows.

import { test, expect } from 'playwright/test'

import { classifyFailures, collectDiagnostics, loginAs, primeSession } from '../harness.js'

import {
  ensureFixtures,
  fixtureSummary,
  placeQuotationStatus,
  quotationByKey,
  registerCreated
} from './fixtures.js'

import {
  DESKTOP,
  LOAD_TIMEOUT,
  QUOTATIONS_ROUTE,
  audit,
  call,
  expectedIgv,
  grossIdentity,
  markExpected,
  moneyIdentity,
  openSelect,
  parseCurrency,
  readDetailCosts,
  readEvidenceFile,
  readFormSummary,
  readQuotationRows,
  settleQuotations,
  stamp,
  waitForCall,
  watchRequests
} from './helpers.js'

import { extractPdfOperands, fetchQuotationPdf, pdfMoneyRows } from './pdf.js'

import {
  NESTED_STEP,
  clearQuotationSearch,
  closeTop,
  openCatalogPanel,
  pickCatalogProcedure,
  pickPatient,
  quotationRowStatus,
  quotationRowTotal,
  recorder,
  rowFor,
  searchQuotation
} from './walk-drivers.js'

// --- the quotation walk -----------------------------------------------------

test('@U6 · presupuestos: dinero, PDF, aprobación y edición bloqueada', async ({
  page,
  request
}) => {
  test.setTimeout(900000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const diagnostics = collectDiagnostics(page)
  const state = { pageErrors: [], currentStep: 'setup' }
  page.on('pageerror', error =>
    state.pageErrors.push({ message: error.message, step: state.currentStep })
  )

  const evidence = {
    route: QUOTATIONS_ROUTE,
    role: 'administrador',
    ...stamp(),
    shotDir: 'walk/quotations',
    scenario:
      'abrir el alta, enviarla vacía, llenarla con procedimiento + sitio + convenio, leer el resumen, ' +
      'guardar, releer el detalle, bajar el PDF, rechazar con y sin motivo, aprobar, y bloquear la ' +
      'edición y la decisión de un presupuesto ya aprobado',
    fixtures: fixtureSummary(fixtures),
    steps: [],
    findings: [],
    requests: [],
    pageErrors: [],
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const step = recorder(page, evidence, state)
  const policyRate = quotationByKey(fixtures, 'manual')?.snapshot?.taxPercentage ?? null
  evidence.policyRate = {
    value: policyRate,
    source: 'el servidor aplica la tasa configurada a un alta sin tasa'
  }

  const requests = watchRequests(page)
  let created = null

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const list = waitForCall(page, 'GET', '/api/quotations', () => true, LOAD_TIMEOUT)
    await page.goto(`${QUOTATIONS_ROUTE}?patient_id=${fixtures.patient.id}`)
    await list
    await settleQuotations(page)

    await step(
      'list',
      'abrir la lista filtrada por el paciente de la sesión',
      'la lista muestra los presupuestos del paciente con su total y su estado, y coinciden con la API',
      async () => {
        const rows = await readQuotationRows(page)
        const api = await call(
          request,
          session,
          'GET',
          `/api/quotations?per_page=100&patient_id=${fixtures.patient.id}`
        )
        const payload = Array.isArray(api.body?.data) ? api.body.data : []
        const mismatches = []

        for (const row of payload) {
          const uiTotal = quotationRowTotal(rows, row.quotation_number)
          const uiStatus = quotationRowStatus(rows, row.quotation_number)
          const apiTotal = Math.round(Number(row.total_amount) * 100)
          const apiStatus =
            {
              draft: 'Borrador',
              sent: 'Enviado',
              viewed: 'Visto',
              approved: 'Aprobado',
              rejected: 'Rechazado',
              expired: 'Expirado'
            }[row.status] ?? row.status

          if (uiTotal !== apiTotal)
            mismatches.push({ number: row.quotation_number, uiTotal, apiTotal })
          if (uiStatus !== apiStatus) {
            mismatches.push({ number: row.quotation_number, uiStatus, apiStatus })
          }
        }

        return {
          requestStatus: api.status,
          obtained: `${rows.rows.length} filas en pantalla, ${payload.length} en la API, ${mismatches.length} discrepancias`,
          verdict:
            mismatches.length === 0 && rows.rows.length === payload.length ? 'OK' : 'DEFECTO',
          arithmetic: { rows: rows.rows.length, apiRows: payload.length },
          detail: mismatches.length ? JSON.stringify(mismatches.slice(0, 4)) : null
        }
      }
    )

    await step(
      'create-open',
      'pulsar «Nuevo presupuesto»',
      'se abre el formulario de alta sobre la lista',
      async () => {
        await page.locator('[data-hero-action="create"]').click({ timeout: 10000 })
        const form = await page
          .locator('[data-quotation-form="create"]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        return {
          obtained: form ? 'el formulario de alta está visible' : 'el formulario no apareció',
          verdict: form ? 'OK' : 'DEFECTO'
        }
      }
    )

    await step(
      'submit-empty',
      'enviar el formulario vacío',
      'los dos errores locales se pintan junto a su campo: paciente y procedimientos',
      async () => {
        await page.locator('[data-form-action="submit"]').click({ timeout: 10000 })
        await page.waitForTimeout(500)

        const patientError = (
          await page
            .locator('[data-field="patient"] p[role="alert"]')
            .first()
            .innerText({ timeout: 4000 })
            .catch(() => '')
        )
          .replace(/\s+/g, ' ')
          .trim()
        const itemsError = (
          await page
            .locator('[data-form-section="items"] [role="alert"]')
            .first()
            .innerText({ timeout: 4000 })
            .catch(() => '')
        )
          .replace(/\s+/g, ' ')
          .trim()

        const ok =
          /Selecciona un paciente/.test(patientError) &&
          /al menos un procedimiento/.test(itemsError)

        return {
          obtained: `paciente: "${patientError}" · procedimientos: "${itemsError}"`,
          verdict: ok ? 'OK' : 'DEFECTO',
          detail: ok
            ? null
            : 'alguno de los dos errores locales no se pintó junto a su campo (paciente: [data-field="patient"], procedimientos: [data-form-section="items"] [role="alert"])'
        }
      }
    )

    await step(
      'fill',
      'elegir paciente, procedimiento del catálogo, sitio y convenio; leer el resumen vivo',
      'el resumen cumple subtotal − descuento = total y su IGV es la descomposición de esa tasa',
      async () => {
        const patient = await pickPatient(page, '[data-quotation-form]', fixtures.patient.document)

        const panel = '[data-item-catalog-panel="0"]'
        const opened = await openCatalogPanel(page, '[data-item-catalog-toggle="0"]', panel)

        // Does the picker's search reach the server? The page-1 list is loaded,
        // so a code that only exists on a later page either appears (server
        // search) or does not (client-side filter over the 12 loaded rows).
        let searchProbe = null

        if (opened.ok) {
          const later = await call(
            request,
            session,
            'GET',
            '/api/procedure-catalog?page=4&per_page=12&is_active=true'
          )
          const laterCode =
            (Array.isArray(later.body?.data) ? later.body.data : [])[0]?.code ?? null

          if (laterCode) {
            const search = page.locator(`${panel} input`).first()
            await search.fill(laterCode).catch(() => {})
            await page.waitForTimeout(900)
            const hits = await page.locator(`${panel} button`, { hasText: laterCode }).count()
            searchProbe = { code: laterCode, hitsOnFirstPage: hits, status: later.status }
            await search.fill('').catch(() => {})
            await page.waitForTimeout(500)
          }
        }

        const catalog = opened.ok
          ? await pickCatalogProcedure(page, panel, fixtures.procedure.code)
          : { ok: false, reason: opened.reason, pagesWalked: 0 }

        const site = await openSelect(
          page,
          '[data-site-field="dental_piece_id"] button',
          `#${fixtures.piece.fdi}`
        )

        const payer = await openSelect(page, '[data-field="payer_id"] button', fixtures.payer.name)

        const summary = await readFormSummary(page)
        const subtotal = parseCurrency(summary?.subtotal)
        const discount = parseCurrency(summary?.discount)
        const igv = parseCurrency(summary?.igv)
        const total = parseCurrency(summary?.total)
        const gross = grossIdentity({ subtotal, discount, total })
        const tax = expectedIgv(total, policyRate)
        const taxOk = igv !== null && Math.abs(Math.round(igv * 100) - Math.round(tax * 100)) <= 1

        const ok = patient.ok && catalog.ok && site.ok && payer.ok && gross.ok && taxOk

        return {
          requestStatus: patient.status,
          obtained:
            `paciente ${patient.ok ? 'elegido' : 'no elegido'} (${patient.status ?? 'sin request'}), ` +
            `catálogo ${catalog.ok ? `elegido en la página ${catalog.pagesWalked}` : 'no elegido'}, ` +
            `sitio ${site.ok ? site.selected : `no (${site.reason})`}, ` +
            `convenio ${payer.ok ? payer.selected : `no (${payer.reason})`}; resumen ` +
            `${summary?.subtotal} − ${summary?.discount} = ${summary?.total}, IGV ${summary?.igv}`,
          verdict: ok ? 'OK' : 'DEFECTO',
          arithmetic: {
            subtotal,
            discount,
            igv,
            total,
            gross,
            tax: { expected: tax, displayed: igv, rate: policyRate, ok: taxOk }
          },
          detail:
            [
              !catalog.ok ? catalog.reason : null,
              !site.ok ? site.reason : null,
              !payer.ok ? payer.reason : null,
              !gross.ok ? 'subtotal − descuento ≠ total' : null,
              !taxOk
                ? `IGV ${igv} no es la descomposición de ${total} al ${policyRate}% (esperado ${tax}): con la tasa vacía el resumen vivo usa 0 y no aplica la configurada`
                : null,
              searchProbe && searchProbe.hitsOnFirstPage === 0
                ? `el buscador del selector de catálogo no consulta al servidor: el código ${searchProbe.code} ` +
                  '(que solo existe en una página posterior) devuelve 0 resultados porque filtra las 12 filas ya cargadas'
                : null
            ]
              .filter(Boolean)
              .join(' · ') || null,
          extra: {
            catalogSearch: { resultsAfterSearch: catalog.resultsAfterSearch, searchProbe },
            preview: summary
          }
        }
      }
    )

    await step(
      'save',
      'guardar el presupuesto',
      'el alta responde 2xx y el total persistido es el que el resumen mostró',
      async () => {
        const posted = waitForCall(page, 'POST', '/api/quotations', () => true, 30000)
        const summaryBefore = await readFormSummary(page)
        const previewTotal = parseCurrency(summaryBefore?.total)
        const previewIgv = parseCurrency(summaryBefore?.igv)

        await page.locator('[data-form-action="submit"]').click({ timeout: 10000 })
        const response = await posted
        const status = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null
        const id = body?.data?.id ?? null

        await settleQuotations(page)
        await page.waitForTimeout(400)

        const detail = id ? await call(request, session, 'GET', `/api/quotations/${id}`) : null
        const persisted = detail?.body?.data ?? null
        created = {
          id,
          number: persisted?.quotation_number ?? null,
          total: Number(persisted?.total_amount ?? 0),
          items: Array.isArray(persisted?.items) ? persisted.items.length : 0,
          priceSource: persisted?.items?.[0]?.price_source ?? null
        }

        if (id) {
          registerCreated('quotation', {
            id,
            number: created.number,
            reason: 'created by the quotations walk through the SPA'
          })
        }

        const persistedTotal = Number(persisted?.total_amount ?? 0)
        const persistedIgv = Number(persisted?.tax_amount ?? 0)
        const totalMatches =
          Math.abs(Math.round(previewTotal * 100) - Math.round(persistedTotal * 100)) <= 1
        const igvMatches =
          Math.abs(Math.round(previewIgv * 100) - Math.round(persistedIgv * 100)) <= 1
        const ok = (status === 201 || status === 200) && id !== null && totalMatches && igvMatches

        return {
          requestStatus: status,
          obtained:
            `HTTP ${status}; persistido ${created.number} con total S/ ${persistedTotal.toFixed(2)} ` +
            `(resumen previo S/ ${previewTotal?.toFixed(2) ?? 'n/d'}), IGV persistido ${persistedIgv.toFixed(2)} ` +
            `(resumen ${previewIgv?.toFixed(2) ?? 'n/d'}), ${created.items} líneas, precio ${created.priceSource}`,
          verdict: ok ? 'OK' : 'DEFECTO',
          arithmetic: {
            previewTotal,
            persistedTotal,
            previewIgv,
            persistedIgv,
            totalMatches,
            igvMatches,
            serversidePriceSource: created.priceSource
          },
          detail: totalMatches
            ? null
            : 'el total cambia entre el resumen del formulario y el documento guardado: el catálogo fija ' +
              `${fixtures.procedure.defaultCost} y el tarifario del convenio ${created.priceSource ?? ''} cobra ` +
              `${persistedTotal} sin avisar en el formulario`,
          extra: { created }
        }
      }
    )

    await step(
      'detail-costs',
      'abrir el detalle del presupuesto creado y leer el bloque de costos',
      'Base imponible + IGV = Total, en céntimos',
      async () => {
        if (!created?.number) return { verdict: 'NO PROBADO', reason: 'no hay presupuesto creado' }

        const status = await searchQuotation(page, created.number)
        const row = rowFor(page, created.number)
        await row.locator('[data-row-action="view"]').click({ timeout: 10000 })
        await page
          .locator('[data-detail-section="costs"]')
          .waitFor({ state: 'visible', timeout: 15000 })
          .catch(() => {})

        const costs = await readDetailCosts(page)
        const identity = moneyIdentity({
          base: costs.values.base,
          igv: costs.values.igv,
          total: costs.values.total
        })

        return {
          requestStatus: status,
          obtained: `lista ${costs.raw.subtotal} · base ${costs.raw.base} · IGV ${costs.raw.igv} · total ${costs.raw.total}`,
          verdict: identity.ok ? 'OK' : identity.reason ? 'NO PROBADO' : 'DEFECTO',
          reason: identity.ok ? null : (identity.reason ?? null),
          arithmetic: identity,
          detail: identity.ok
            ? null
            : identity.reason
              ? `el bloque de costos no publica los tres operandos: ${identity.reason}`
              : `base ${identity.baseCents} + IGV ${identity.igvCents} ≠ total ${identity.totalCents} (${identity.diffCents} céntimos)`
        }
      }
    )

    await step(
      'pdf',
      'descargar el PDF y leer sus filas de dinero',
      'el PDF cumple Base imponible + IGV = Total, en céntimos',
      async () => {
        if (!created?.id) return { verdict: 'NO PROBADO', reason: 'no hay presupuesto creado' }

        await closeTop(page)

        const downloadPromise = page.waitForEvent('download', { timeout: 20000 }).catch(() => null)
        await rowFor(page, created.number)
          .locator('[data-row-action="pdf"]')
          .click({ timeout: 12000 })
          .catch(() => {})
        const download = await downloadPromise

        const fetched = await fetchQuotationPdf(page, session, created.id, {
          relPath: `pdf/quotation-${created.id}.pdf`
        })

        if (!fetched.ok) {
          return {
            requestStatus: fetched.status,
            obtained: `el PDF no se pudo bajar (HTTP ${fetched.status ?? 'sin respuesta'})`,
            reason: fetched.reason ?? 'the PDF door did not answer a PDF',
            verdict: 'NO PROBADO'
          }
        }

        const parsed = extractPdfOperands(readEvidenceFile(fetched.path))
        const rows = pdfMoneyRows(parsed.operands)
        const identity = moneyIdentity({
          base: rows.values?.base ?? null,
          igv: rows.values?.igv ?? null,
          total: rows.values?.total ?? null
        })

        const ok = rows.ok && identity.ok

        return {
          requestStatus: fetched.status,
          obtained: rows.ok
            ? `PDF ${fetched.bytes} bytes, ${parsed.inflated}/${parsed.streams} streams inflados; ` +
              `Subtotal ${rows.rows.subtotal?.raw ?? 'n/d'}, Base ${rows.rows.base?.raw ?? 'n/d'}, ` +
              `IGV ${rows.rows.igv?.raw ?? 'n/d'}, Total ${rows.rows.total?.raw ?? 'n/d'}`
            : `PDF ${fetched.bytes} bytes; ${rows.reason}`,
          verdict: ok ? 'OK' : rows.ok ? 'DEFECTO' : 'NO PROBADO',
          reason: rows.ok ? null : rows.reason,
          arithmetic: {
            ...identity,
            extraction: {
              streams: parsed.streams,
              inflated: parsed.inflated,
              failures: parsed.failures.length
            }
          },
          detail: ok
            ? null
            : rows.ok
              ? `el PDF imprime base ${identity.baseCents} + IGV ${identity.igvCents} ≠ total ${identity.totalCents}`
              : 'las filas de dinero del PDF no se pudieron localizar en el texto extraído',
          extra: {
            download: download ? download.suggestedFilename() : null,
            pdf: fetched.path,
            window: rows.window ?? null
          }
        }
      }
    )

    await step(
      'reject-empty',
      'rechazar el presupuesto enviado sin motivo',
      'el motivo vacío se detiene en el campo, con mensaje',
      async () => {
        const sent = quotationByKey(fixtures, 'sentForReject')?.snapshot
        if (!sent) return { verdict: 'NO PROBADO', reason: 'no hay presupuesto enviado' }

        const status = await searchQuotation(page, sent.number)
        await rowFor(page, sent.number)
          .locator('[data-row-action="reject"]')
          .click({ timeout: 10000 })
        await page.locator('[data-quotation-reject]').waitFor({ state: 'visible', timeout: 10000 })
        await page.locator('[data-modal-action="reject"]').click({ timeout: 8000 })
        await page.waitForTimeout(400)

        const errorText = (
          await page
            .locator('[data-modal-error]')
            .first()
            .innerText({ timeout: 4000 })
            .catch(() => '')
        )
          .replace(/\s+/g, ' ')
          .trim()
        const rejected = await call(request, session, 'GET', `/api/quotations/${sent.id}`)

        return {
          requestStatus: status,
          obtained: `error del campo: "${errorText}"; la API sigue en ${rejected.body?.data?.status}`,
          verdict:
            /Indica el motivo/.test(errorText) && rejected.body?.data?.status === 'sent'
              ? 'OK'
              : 'DEFECTO',
          detail:
            /Indica el motivo/.test(errorText) && rejected.body?.data?.status === 'sent'
              ? null
              : 'el rechazo sin motivo no se detuvo donde debía o escribió estado'
        }
      }
    )

    await step(
      'reject-reason',
      'rechazar el mismo presupuesto con motivo',
      'la decisión responde 2xx y la fila pasa a Rechazado',
      async () => {
        const sent = quotationByKey(fixtures, 'sentForReject')?.snapshot
        if (!sent) return { verdict: 'NO PROBADO', reason: 'no hay presupuesto enviado' }

        await page
          .locator('[data-modal-field="reason"] textarea, textarea[data-modal-field="reason"]')
          .first()
          .fill('AUDIT-U6 motivo del rechazo')
          .catch(async () => {
            await page
              .locator('[data-quotation-reject] textarea')
              .first()
              .fill('AUDIT-U6 motivo del rechazo')
          })

        const posted = waitForCall(
          page,
          'POST',
          new RegExp(`/api/quotations/${sent.id}/reject`),
          () => true,
          30000
        )
        await page.locator('[data-modal-action="reject"]').click({ timeout: 8000 })
        const response = await posted
        const status = response?.status() ?? null

        await settleQuotations(page)
        await page.waitForTimeout(600)
        const rows = await readQuotationRows(page)
        const uiStatus = quotationRowStatus(rows, sent.number)
        const api = await call(request, session, 'GET', `/api/quotations/${sent.id}`)

        return {
          requestStatus: status,
          obtained: `HTTP ${status}; fila "${uiStatus}"; API ${api.body?.data?.status}`,
          verdict:
            (status === 200 || status === 201) &&
            uiStatus === 'Rechazado' &&
            api.body?.data?.status === 'rejected'
              ? 'OK'
              : 'DEFECTO'
        }
      }
    )

    await step(
      'approve',
      'aprobar el presupuesto enviado y abrir su detalle',
      'la decisión responde 2xx, la fila pasa a Aprobado y el detalle publica el bloque de aprobación',
      async () => {
        const sent = quotationByKey(fixtures, 'sentForApprove')?.snapshot
        if (!sent) return { verdict: 'NO PROBADO', reason: 'no hay presupuesto enviado' }

        const status = await searchQuotation(page, sent.number)
        await rowFor(page, sent.number)
          .locator('[data-row-action="approve"]')
          .click({ timeout: 10000 })
        await page
          .locator('[data-quotation-approval]')
          .waitFor({ state: 'visible', timeout: 10000 })

        const posted = waitForCall(
          page,
          'POST',
          new RegExp(`/api/quotations/${sent.id}/approve`),
          () => true,
          30000
        )
        await page.locator('[data-modal-action="approve"]').click({ timeout: 8000 })
        const response = await posted
        const approveStatus = response?.status() ?? null

        await settleQuotations(page)
        await page.waitForTimeout(500)

        await rowFor(page, sent.number)
          .locator('[data-row-action="view"]')
          .click({ timeout: 10000 })
        await page
          .locator('[data-quotation-detail]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})
        // The detail paints the list payload first and replaces it with the
        // read-back of GET /api/quotations/{id}; the block is measured on that
        // second paint.
        const approvalsBlock = await page
          .locator('[data-detail-section="approvals"]')
          .waitFor({ state: 'visible', timeout: 8000 })
          .then(() => true)
          .catch(() => false)
        const api = await call(request, session, 'GET', `/api/quotations/${sent.id}`)

        await closeTop(page)

        return {
          requestStatus: approveStatus,
          obtained:
            `lista ${status}; aprobación ${approveStatus}; API ${api.body?.data?.status}; bloque de aprobación ` +
            `${approvalsBlock ? 'visible' : 'ausente'}`,
          verdict:
            (approveStatus === 200 || approveStatus === 201) &&
            api.body?.data?.status === 'approved' &&
            approvalsBlock
              ? 'OK'
              : 'DEFECTO',
          detail:
            (approveStatus === 200 || approveStatus === 201) &&
            api.body?.data?.status === 'approved' &&
            approvalsBlock
              ? null
              : 'la aprobación no dejó el detalle con su bloque de aprobación, o la API no cambió el estado'
        }
      }
    )

    await step(
      'approved-no-edit',
      'buscar un presupuesto aprobado en la lista y abrir su ficha',
      'ni la fila ni el detalle ofrecen «Editar»',
      async () => {
        const approved = quotationByKey(fixtures, 'approved')?.snapshot
        if (!approved) return { verdict: 'NO PROBADO', reason: 'no hay presupuesto aprobado' }

        const status = await searchQuotation(page, approved.number)
        await page.waitForTimeout(300)
        const rows = await readQuotationRows(page)
        const row = (rows.rows ?? []).find(entry => entry.number === approved.number)
        const rowActions = (row?.actions ?? []).map(action => action.action)

        await rowFor(page, approved.number)
          .locator('[data-row-action="view"]')
          .click({ timeout: 10000 })
        await page
          .locator('[data-quotation-detail]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})
        await page.waitForTimeout(300)
        const detailEdit = await page
          .locator('[data-detail-action="edit"]')
          .isVisible()
          .catch(() => false)
        const statusBadge = await page
          .locator('[data-detail-section="status"]')
          .innerText({ timeout: 4000 })
          .catch(() => '')

        await closeTop(page)

        const ok = !rowActions.includes('edit') && !detailEdit

        return {
          requestStatus: status,
          obtained:
            `acciones de la fila: ${JSON.stringify(rowActions)}; «Editar» en el detalle: ` +
            `${detailEdit}; estado pintado "${statusBadge.replace(/\s+/g, ' ').trim()}"`,
          verdict: ok ? 'OK' : 'DEFECTO',
          detail: ok ? null : 'un presupuesto aprobado conserva la affordance de edición'
        }
      }
    )

    await step(
      'stale-edit',
      'abrir «Editar» en un borrador y aprobarlo antes de guardar',
      'el 422 de la edición bloqueada se pinta en el formulario, con el motivo',
      async () => {
        const manual = quotationByKey(fixtures, 'manual')?.snapshot
        if (!manual) return { verdict: 'NO PROBADO', reason: 'no hay borrador de la sesión' }

        const status = await searchQuotation(page, manual.number)
        await rowFor(page, manual.number)
          .locator('[data-row-action="edit"]')
          .click({ timeout: 10000 })
        await page
          .locator('[data-quotation-form="edit"]')
          .waitFor({ state: 'visible', timeout: 12000 })

        // The SPA has no door from `draft` to `approved` (a decision needs
        // `sent|viewed`), so the state is placed with the guarded fixture while
        // the form stays open. Declared in `statePlacedBy`, never as product
        // behaviour.
        const placed = placeQuotationStatus(manual.id, 'approved')
        const before = await call(request, session, 'GET', `/api/quotations/${manual.id}`)

        const put = waitForCall(
          page,
          'PUT',
          new RegExp(`/api/quotations/${manual.id}`),
          () => true,
          30000
        )
        await page.locator('[data-form-action="submit"]').click({ timeout: 10000 })
        const response = await put
        const putStatus = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null

        await page.waitForTimeout(500)

        const submitError = (
          await page
            .locator('[data-submit-error]')
            .first()
            .innerText({ timeout: 5000 })
            .catch(() => '')
        )
          .replace(/\s+/g, ' ')
          .trim()
        const serverMessage = body?.message ?? null
        // The controller answers a generic `message` and the reason in
        // `errors.status`; the criterion asks for an explicit message, so both
        // are measured and the reason is the one that has to reach the form.
        const reason = Array.isArray(body?.errors?.status) ? body.errors.status[0] : null
        const painted = Boolean(submitError) && Boolean(reason) && submitError.includes(reason)

        await closeTop(page)

        return {
          requestStatus: putStatus,
          obtained:
            `estado colocado por el fixture ${JSON.stringify(placed)} (la API leyó ${before.body?.data?.status}); ` +
            `PUT ${putStatus}; motivo del servidor "${reason}"; mensaje "${serverMessage}"; ` +
            `pintado en [data-submit-error]: "${submitError}"`,
          verdict: putStatus === 422 && painted ? 'OK' : putStatus === 422 ? 'CONFUSO' : 'DEFECTO',
          detail:
            putStatus === 422 && painted
              ? null
              : putStatus === 422
                ? `el 422 llega, pero el formulario solo pinta «${submitError}»: el motivo del servidor ('${reason}') no aparece en pantalla`
                : 'la edición de un aprobado no respondió 422',
          extra: {
            searchStatus: status,
            statePlacedBy: 'guarded tinker (fixture state, not product behaviour)',
            placed
          }
        }
      }
    )

    await step(
      'stale-decision',
      'pulsar «Aprobar» sobre una fila enviada que la API ya aprobó',
      'el 422 de la transición inválida se pinta en el diálogo de decisión',
      async () => {
        const sent = quotationByKey(fixtures, 'sentForStale')?.snapshot
        if (!sent) return { verdict: 'NO PROBADO', reason: 'no hay fila enviada de la sesión' }

        const status = await searchQuotation(page, sent.number)
        const approve = await call(
          request,
          session,
          'POST',
          `/api/quotations/${sent.id}/approve`,
          {}
        )

        await rowFor(page, sent.number)
          .locator('[data-row-action="approve"]')
          .click({ timeout: 10000 })
        const opened = await page
          .locator('[data-quotation-approval]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!opened) {
          return {
            requestStatus: approve.status,
            obtained: 'la fila obsoleta ya no ofrece «Aprobar»: la lista se refrescó sola',
            verdict: 'NO PROBADO',
            reason: 'the stale row was refreshed before the click',
            detail: null
          }
        }

        const posted = waitForCall(
          page,
          'POST',
          new RegExp(`/api/quotations/${sent.id}/approve`),
          () => true,
          30000
        )
        await page.locator('[data-modal-action="approve"]').click({ timeout: 8000 })
        const response = await posted
        const decisionStatus = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null
        await page.waitForTimeout(500)

        const modalError = (
          await page
            .locator('[data-modal-error]')
            .first()
            .innerText({ timeout: 5000 })
            .catch(() => '')
        )
          .replace(/\s+/g, ' ')
          .trim()
        const serverMessage = body?.message ?? null
        const reason = Array.isArray(body?.errors?.status) ? body.errors.status[0] : null
        const painted = Boolean(modalError) && Boolean(reason) && modalError.includes(reason)

        await closeTop(page)
        await clearQuotationSearch(page)

        return {
          requestStatus: decisionStatus,
          obtained:
            `API previa ${approve.status}; decisión ${decisionStatus}; motivo del servidor "${reason}"; ` +
            `mensaje "${serverMessage}"; pintado en [data-modal-error]: "${modalError}"`,
          verdict:
            decisionStatus === 422 && painted
              ? 'OK'
              : decisionStatus === 422
                ? 'CONFUSO'
                : 'DEFECTO',
          detail:
            decisionStatus === 422 && painted
              ? null
              : decisionStatus === 422
                ? `el 422 se pinta en el diálogo, pero con «${modalError}»: el motivo ('${reason}') no llega a la pantalla`
                : 'la decisión obsoleta no respondió 422',
          extra: { searchStatus: status }
        }
      }
    )

    await step(
      'list-vs-payload',
      'releer la lista completa del paciente contra la API',
      'cada fila conserva el total y el estado que la API publica',
      async () => {
        await clearQuotationSearch(page)
        await page.waitForTimeout(400)
        const rows = await readQuotationRows(page)
        const api = await call(
          request,
          session,
          'GET',
          `/api/quotations?per_page=100&patient_id=${fixtures.patient.id}`
        )
        const payload = Array.isArray(api.body?.data) ? api.body.data : []
        const labels = {
          draft: 'Borrador',
          sent: 'Enviado',
          viewed: 'Visto',
          approved: 'Aprobado',
          rejected: 'Rechazado',
          expired: 'Expirado'
        }
        const mismatches = []

        for (const row of payload) {
          const uiTotal = quotationRowTotal(rows, row.quotation_number)
          const uiStatus = quotationRowStatus(rows, row.quotation_number)
          const apiTotal = Math.round(Number(row.total_amount) * 100)
          const apiStatus = labels[row.status] ?? row.status

          if (uiTotal !== apiTotal)
            mismatches.push({ number: row.quotation_number, uiTotal, apiTotal })
          if (uiStatus !== apiStatus)
            mismatches.push({ number: row.quotation_number, uiStatus, apiStatus })
        }

        return {
          requestStatus: api.status,
          obtained: `${rows.rows.length} filas, ${payload.length} en la API, ${mismatches.length} discrepancias`,
          verdict: mismatches.length === 0 ? 'OK' : 'DEFECTO',
          detail: mismatches.length ? JSON.stringify(mismatches.slice(0, 4)) : null,
          extra: {
            statuses: payload.reduce((accumulator, row) => {
              accumulator[row.status] = (accumulator[row.status] ?? 0) + 1
              return accumulator
            }, {})
          }
        }
      }
    )
  } finally {
    await closeTop(page).catch(() => {})
    await page.keyboard.press('Escape').catch(() => {})

    evidence.requests = requests
      .filter(entry => entry.method !== 'GET')
      .slice(-40)
      .map(entry => ({ method: entry.method, path: entry.path, status: entry.status }))
    evidence.pageErrors = state.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
    markExpected(
      evidence.failedRequests,
      failure =>
        [422, 403].includes(failure.status) && /\/api\/quotations\//.test(failure.url ?? ''),
      'probe: the walk submits stale forms and decisions the API must refuse'
    )
    evidence.created = created
    evidence.totals = {
      steps: evidence.steps.length,
      ok: evidence.steps.filter(entry => entry.verdict === 'OK').length,
      findings: evidence.findings.length
    }

    audit('walk-administrador.json', evidence)
  }

  expect(evidence.steps.length, 'steps of the quotation walk').toBeGreaterThanOrEqual(12)
  expect(
    evidence.steps.filter(entry => entry.shot === null),
    'steps without a capture'
  ).toEqual([])
  expect(
    state.pageErrors.filter(error => error.step !== NESTED_STEP),
    'page errors outside the declared nested-dialog probe'
  ).toEqual([])
})
