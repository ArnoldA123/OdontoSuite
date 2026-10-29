// Plan #13 (issue #369, U6) — layer 4, planes de tratamiento.
//
// One walkthrough as `odontologo` (the desk the plan module belongs to): open the
// create form, submit it empty, create a two-line plan (catalog line with site +
// free text), read the detail, open the edit form on top of the detail and prove
// Escape closes only the top, walk draft → proposed → approved, provoke the state
// machine's 422 and measure the surfaces the issue names and the SPA never paints
// (priority, sequence, A/B alternative, new version, item consumption).
//
// Same contract as the quotation walk: every step carries its verdict, its
// request status and its capture, and a defect is evidence, not an exception.
// The nested-dialog step is the only one allowed to raise a page error, and it
// declares it.

import { test, expect } from 'playwright/test'

import { classifyFailures, collectDiagnostics, loginAs, primeSession } from '../harness.js'

import { ensureFixtures, fixtureSummary, registerCreated } from './fixtures.js'

import {
  DESKTOP,
  LOAD_TIMEOUT,
  PLANS_ROUTE,
  TOAST_TIMEOUT,
  audit,
  call,
  fillControl,
  markExpected,
  openSelect,
  parseCurrency,
  readPlanCards,
  readPlanDetail,
  settlePlans,
  stamp,
  textContains,
  waitForCall,
  waitForToast,
  watchRequests
} from './helpers.js'

import { scanSources } from './sources.js'

import {
  MODAL,
  NESTED_STEP,
  changePlanStatus,
  closeTop,
  dismissToasts,
  formLocator,
  openCatalogPanel,
  pickCatalogProcedure,
  pickPatient,
  planBadge,
  planCard,
  recorder
} from './walk-drivers.js'

test('@U6 · planes: alta, pila de modales, estados y superficies ausentes', async ({
  page,
  request
}) => {
  test.setTimeout(900000)

  const session = await loginAs(request, 'odontologo')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const diagnostics = collectDiagnostics(page)
  const state = { pageErrors: [], currentStep: 'setup' }
  page.on('pageerror', error =>
    state.pageErrors.push({ message: error.message, step: state.currentStep })
  )

  const evidence = {
    route: PLANS_ROUTE,
    role: 'odontologo',
    ...stamp(),
    shotDir: 'walk/plans',
    scenario:
      'abrir el alta, enviarla vacía, crear un plan de dos líneas con catálogo y sitio, leer el detalle, ' +
      'abrir la edición encima del detalle y cerrar solo la de arriba, recorrer draft → proposed → approved, ' +
      'provocar una transición inválida y medir las superficies que la issue nombra y el SPA no pinta',
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
  const planTitle = `AUDIT-U6 plan UI ${fixtures.token}`
  let created = null

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const list = waitForCall(page, 'GET', '/api/treatment-plans', () => true, LOAD_TIMEOUT)
    await page.goto(PLANS_ROUTE)
    await list
    await settlePlans(page)

    await step(
      'list',
      'abrir la lista de planes',
      'la tarjeta del plan de la sesión está visible con su costo y su estado',
      async () => {
        const cards = await readPlanCards(page)
        const card = (cards.cards ?? []).find(entry => entry.title === fixtures.plan.title)
        const api = await call(request, session, 'GET', `/api/treatment-plans/${fixtures.plan.id}`)
        const payload = api.body?.data ?? null
        const ok =
          Boolean(card) &&
          card.totalCents === Math.round(Number(payload?.final_cost ?? 0) * 100) &&
          card.status === 'Borrador'

        return {
          requestStatus: api.status,
          obtained: card
            ? `«${card.title}» ${card.status} ${card.total} (${card.items} procedimientos)`
            : 'la tarjeta del plan no aparece',
          verdict: ok ? 'OK' : 'DEFECTO',
          arithmetic: {
            uiTotalCents: card?.totalCents ?? null,
            apiTotalCents: Math.round(Number(payload?.final_cost ?? 0) * 100)
          }
        }
      }
    )

    await step('create-open', 'pulsar «Nuevo Plan»', 'se abre el formulario de alta', async () => {
      await page.locator('[data-create-plan]').click({ timeout: 10000 })
      const open = await page
        .locator('[data-plan-modal]')
        .waitFor({ state: 'visible', timeout: 10000 })
        .then(() => true)
        .catch(() => false)

      return {
        obtained: open ? 'el formulario de alta está visible' : 'el formulario no apareció',
        verdict: open ? 'OK' : 'DEFECTO'
      }
    })

    await step(
      'submit-empty',
      'enviar el formulario vacío',
      'los errores se pintan junto a su campo: paciente y título',
      async () => {
        await page.locator('[data-plan-submit]').click({ timeout: 10000 })
        await page.waitForTimeout(500)

        const patientError = (
          await page
            .locator('[data-plan-error="patient_id"]')
            .first()
            .innerText({ timeout: 4000 })
            .catch(() => '')
        )
          .replace(/\s+/g, ' ')
          .trim()
        const titleText = (
          await page
            .locator('[data-plan-field="title"]')
            .first()
            .innerText({ timeout: 4000 })
            .catch(() => '')
        )
          .replace(/\s+/g, ' ')
          .trim()

        const titlePainted = /obligatorio/i.test(titleText)
        const ok = /Selecciona un paciente/i.test(patientError) && titlePainted

        return {
          obtained: `paciente: "${patientError}" · título: "${titleText.slice(0, 120)}"`,
          verdict: ok ? 'OK' : 'DEFECTO',
          detail: ok
            ? null
            : 'uno de los dos errores locales no llegó a su campo (paciente: [data-plan-error="patient_id"], título: dentro de [data-plan-field="title"])'
        }
      }
    )

    await step(
      'create-two-items',
      'crear un plan de dos líneas: una del catálogo con sitio y una de texto libre',
      'el alta responde 2xx y la tarjeta aparece con el total de sus líneas',
      async () => {
        const patient = await pickPatient(page, '[data-plan-modal]', fixtures.patient.document)

        await fillControl(page, '[data-plan-field="title"] input', planTitle)

        // Line 1: the catalog procedure and its site.
        const panel = '[data-plan-item-catalog-panel="0"]'
        const opened = await openCatalogPanel(page, '[data-plan-item-catalog-toggle="0"]', panel)
        const catalog = opened.ok
          ? await pickCatalogProcedure(page, panel, fixtures.procedure.code)
          : { ok: false, reason: opened.reason, pagesWalked: 0 }
        const site = await openSelect(
          page,
          '[data-site-field="dental_piece_id"] button',
          `#${fixtures.piece.fdi}`
        )

        // Line 2: free text, added through the editor's own button.
        await page
          .locator('[data-plan-modal]')
          .getByRole('button', { name: 'Nuevo procedimiento' })
          .first()
          .click({ timeout: 8000 })
        await page.locator('[data-plan-item]').nth(1).waitFor({ state: 'visible', timeout: 8000 })
        await page
          .locator('[data-plan-item-field="name"] input')
          .nth(1)
          .fill(`AUDIT-U6 línea libre UI ${fixtures.token}`)
        await page.locator('[data-plan-item-field="quantity"] input').nth(1).fill('2')
        await page.locator('[data-plan-item-field="unit_cost"] input').nth(1).fill('30')
        await page.waitForTimeout(300)

        const footerTotal = (
          await page
            .locator('[data-plan-total]')
            .innerText({ timeout: 4000 })
            .catch(() => '')
        ).trim()
        const footerSubtotal = (
          await page
            .locator('[data-plan-subtotal]')
            .innerText({ timeout: 4000 })
            .catch(() => '')
        ).trim()

        const posted = waitForCall(page, 'POST', '/api/treatment-plans', () => true, 30000)
        await page.locator('[data-plan-submit]').click({ timeout: 10000 })
        const response = await posted
        const status = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null
        const id = body?.data?.id ?? null

        await settlePlans(page)
        await page.waitForTimeout(600)

        const cards = await readPlanCards(page)
        const card = (cards.cards ?? []).find(entry => entry.title === planTitle)
        const detail = id ? await call(request, session, 'GET', `/api/treatment-plans/${id}`) : null
        const payload = detail?.body?.data ?? null

        created = {
          id,
          title: planTitle,
          items: Array.isArray(payload?.items) ? payload.items.length : 0,
          finalCost: Number(payload?.final_cost ?? 0)
        }

        if (id) {
          registerCreated('treatment-plan', {
            id,
            title: planTitle,
            reason: 'created by the plans walk through the SPA'
          })
        }

        const footerCents = Math.round(parseCurrency(footerTotal) * 100)
        const apiCents = Math.round(Number(payload?.final_cost ?? 0) * 100)
        const ok =
          (status === 201 || status === 200) &&
          id !== null &&
          card !== undefined &&
          created.items === 2 &&
          footerCents === apiCents

        const validation = body?.errors
          ? Object.entries(body.errors).map(
              ([field, message]) => `${field}: ${[].concat(message)[0]}`
            )
          : []
        const generalError = (
          await page
            .locator('[data-plan-modal] [role="alert"]')
            .first()
            .innerText({ timeout: 3000 })
            .catch(() => '')
        )
          .replace(/\s+/g, ' ')
          .trim()

        return {
          requestStatus: status,
          obtained:
            `paciente ${patient.ok ? 'elegido' : 'no elegido'}; catálogo ${catalog.ok ? `elegido en la página ${catalog.pagesWalked}` : 'no elegido'}; ` +
            `sitio ${site.ok ? site.selected : 'no'}; pie ${footerSubtotal}/${footerTotal}; HTTP ${status}; ` +
            `plan ${id} con ${created.items} líneas y total S/ ${created.finalCost.toFixed(2)}; ` +
            `tarjeta ${card ? 'presente' : 'ausente'}; errores ${JSON.stringify(validation)}; ` +
            `aviso del formulario "${generalError}"`,
          verdict: ok ? 'OK' : 'DEFECTO',
          arithmetic: { footerCents, apiCents, items: created.items },
          detail:
            [
              !catalog.ok ? catalog.reason : null,
              !site.ok ? site.reason : null,
              card === undefined ? 'la tarjeta del plan creado no apareció en la lista' : null,
              footerCents !== apiCents
                ? 'el pie del formulario y la API no coinciden en el total'
                : null,
              validation.length ? `la API rechaza el alta: ${JSON.stringify(validation)}` : null
            ]
              .filter(Boolean)
              .join(' · ') || null,
          extra: {
            created,
            catalogSearch: { resultsAfterSearch: catalog.resultsAfterSearch },
            validation
          }
        }
      }
    )

    await step(
      'create-with-descriptions',
      'rellenar las descripciones que el formulario marca opcionales y reenviar',
      'el alta responde 2xx y la tarjeta aparece con el total de sus líneas',
      async () => {
        if (created?.id) {
          return { obtained: 'el alta anterior ya había creado el plan', verdict: 'OK' }
        }

        await page
          .locator('[data-plan-item-field="description"] input')
          .nth(0)
          .fill(`AUDIT-U6 descripción de la línea de catálogo ${fixtures.token}`, { timeout: 5000 })
          .catch(() => {})
        await page
          .locator('[data-plan-item-field="description"] input')
          .nth(1)
          .fill(`AUDIT-U6 descripción de la línea libre ${fixtures.token}`, { timeout: 5000 })
          .catch(() => {})

        const footerTotal = (
          await page
            .locator('[data-plan-total]')
            .innerText({ timeout: 4000 })
            .catch(() => '')
        ).trim()

        const posted = waitForCall(page, 'POST', '/api/treatment-plans', () => true, 30000)
        await page.locator('[data-plan-submit]').click({ timeout: 10000 })
        const response = await posted
        const status = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null
        const id = body?.data?.id ?? null

        await settlePlans(page)
        await page.waitForTimeout(800)

        const cards = await readPlanCards(page)
        const card = (cards.cards ?? []).find(entry => entry.title === planTitle)
        const detail = id ? await call(request, session, 'GET', `/api/treatment-plans/${id}`) : null
        const payload = detail?.body?.data ?? null

        created = {
          id,
          title: planTitle,
          items: Array.isArray(payload?.items) ? payload.items.length : 0,
          finalCost: Number(payload?.final_cost ?? 0)
        }

        if (id) {
          registerCreated('treatment-plan', {
            id,
            title: planTitle,
            reason: 'created by the plans walk through the SPA'
          })
        }

        const footerCents = Math.round(parseCurrency(footerTotal) * 100)
        const apiCents = Math.round(Number(payload?.final_cost ?? 0) * 100)
        const ok =
          (status === 201 || status === 200) &&
          id !== null &&
          card !== undefined &&
          created.items === 2 &&
          footerCents === apiCents

        return {
          requestStatus: status,
          obtained:
            `HTTP ${status}; plan ${id} con ${created.items} líneas y total S/ ${created.finalCost.toFixed(2)}; ` +
            `pie ${footerTotal}; tarjeta ${card ? 'presente' : 'ausente'}`,
          verdict: ok ? 'OK' : 'DEFECTO',
          arithmetic: { footerCents, apiCents, items: created.items },
          detail: ok
            ? null
            : 'el alta con las descripciones rellenas tampoco produjo la tarjeta con el total del pie',
          extra: { created, declared: 'workaround for the 422 of the previous step' }
        }
      }
    )

    await step(
      'detail',
      'abrir el detalle del plan de la sesión',
      'el detalle lista las dos líneas y su total coincide con la API',
      async () => {
        await closeTop(page)
        const card = planCard(page, fixtures.plan.title)
        await card.locator('[data-action="view"]').click({ timeout: 10000 })
        await page
          .locator('[data-detail-body]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})

        // The table and its empty state are exclusive: wait for whichever the
        // detail paints instead of reading a half-mounted body.
        await page
          .waitForFunction(
            () =>
              Boolean(
                document.querySelector('[data-detail-item]') ||
                document.querySelector('[data-detail-empty="procedures"]')
              ),
            undefined,
            { timeout: 8000, polling: 100 }
          )
          .catch(() => {})

        const detail = await readPlanDetail(page)
        const api = await call(request, session, 'GET', `/api/treatment-plans/${fixtures.plan.id}`)
        const payload = api.body?.data ?? null
        const apiCents = Math.round(Number(payload?.final_cost ?? 0) * 100)
        const apiItems = (payload?.items ?? []).length
        const ok = detail?.itemCount === apiItems && detail?.totalCents === apiCents

        return {
          requestStatus: api.status,
          obtained:
            `«${detail?.title}» ${detail?.itemCount} líneas (API ${apiItems}), total ` +
            `${detail?.total || 'sin total'} (API ${apiCents} céntimos)` +
            `${detail?.emptyText ? ` · el detalle dice "${detail.emptyText}"` : ''}`,
          verdict: ok ? 'OK' : 'DEFECTO',
          arithmetic: {
            uiCents: detail?.totalCents ?? null,
            apiCents,
            uiItems: detail?.itemCount ?? null,
            apiItems
          },
          detail: ok
            ? null
            : 'el detalle abierto desde la tarjeta no lista las líneas del plan: la lista de planes no las ' +
              'carga (TreatmentPlanService::getPlans selecciona patient y createdBy) y el detalle no las pide ' +
              '(TreatmentPlansPage.viewPlan pasa el objeto de la tarjeta, TreatmentPlanDetail no vuelve a consultar)'
        }
      }
    )

    await step(
      'nested-escape',
      'abrir «Editar» encima del detalle y pulsar Escape',
      'Escape cierra solo el formulario de arriba: el detalle sigue visible',
      async () => {
        const formOpen = page.locator('[data-plan-modal]')
        const detailOpen = page.locator('[data-detail-body]')

        const titleBefore = (await readPlanDetail(page))?.title ?? null
        const detailBefore = await detailOpen.isVisible().catch(() => false)
        const errorsBefore = state.pageErrors.length

        await page
          .locator(`[role="dialog"]:visible [data-action="edit"]`)
          .first()
          .click({ timeout: 10000 })
        await formOpen.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})
        await page.waitForTimeout(400)

        const stackBefore = await page.locator(MODAL).count()
        await page.keyboard.press('Escape')
        await page.waitForTimeout(800)

        const formAfter = await formOpen.isVisible().catch(() => false)
        const detailAfter = await detailOpen.isVisible().catch(() => false)
        const stackAfter = await page.locator(MODAL).count()
        const raised = state.pageErrors.slice(errorsBefore).map(error => error.message)
        const titleAfter = (await readPlanDetail(page))?.title ?? null
        const contentKept = Boolean(titleBefore) && titleAfter === titleBefore

        // The page must stay usable after the probe: the surviving dialog is
        // closed so the next step can read the list.
        const cleanup = []
        for (let attempt = 0; attempt < 3; attempt += 1) {
          if (
            (await page
              .locator(MODAL)
              .count()
              .catch(() => 0)) === 0
          )
            break
          await page.keyboard.press('Escape').catch(() => {})
          await page.waitForTimeout(400)
          cleanup.push('escape')
        }

        const dialogsAfterCleanup = await page
          .locator(MODAL)
          .count()
          .catch(() => -1)

        const stackOk = detailBefore && stackBefore === 2 && !formAfter && stackAfter === 1
        const ok = stackOk && contentKept && raised.length === 0

        return {
          obtained:
            `detalle antes ${detailBefore}; pila ${stackBefore}; tras Escape el formulario ` +
            `${formAfter ? 'sigue abierto' : 'se cerró'} y el detalle ${detailAfter ? 'sigue montado' : 'desapareció'} ` +
            `(pila ${stackAfter}); contenido ${contentKept ? 'conservado' : `roto («${titleAfter ?? ''}»)`}; ` +
            `pageerror ${raised.length ? JSON.stringify(raised) : 'ninguno'}; limpieza ${JSON.stringify(cleanup)}`,
          verdict: ok ? 'OK' : 'DEFECTO',
          arithmetic: {
            stackBefore,
            stackAfter,
            detailAfter,
            formAfter,
            contentKept,
            raised: raised.length,
            dialogsAfterCleanup
          },
          detail:
            raised.length || !contentKept
              ? 'cerrar el formulario de arriba deja el detalle sin su plan: TreatmentPlansPage.closeModal limpia ' +
                '`selectedPlan` y el detalle (que sigue montado) vuelve a renderizar con `plan = null`, así que ' +
                "su cabecera y su tabla se pierden y Vue lanza «Cannot read properties of null (reading 'title')»"
              : stackOk
                ? null
                : 'la pila de modales no se comportó como una pila'
        }
      }
    )

    await step(
      'status-proposed',
      'cambiar el estado del plan de borrador a propuesto desde la tarjeta',
      'la tarjeta pasa a Propuesto y la API lo confirma',
      async () => {
        await closeTop(page)
        const changed = await changePlanStatus(page, fixtures.plan.title, 'proposed')
        await page.waitForTimeout(600)
        const badge = await planBadge(page, fixtures.plan.title)
        const api = await call(request, session, 'GET', `/api/treatment-plans/${fixtures.plan.id}`)

        return {
          requestStatus: changed.status,
          obtained: `HTTP ${changed.status}; insignia "${badge}"; API ${api.body?.data?.status}`,
          verdict:
            (changed.status === 200 || changed.status === 201) &&
            badge === 'Propuesto' &&
            api.body?.data?.status === 'proposed'
              ? 'OK'
              : 'DEFECTO'
        }
      }
    )

    await step(
      'status-approved',
      'cambiar el estado de propuesto a aprobado',
      'la tarjeta pasa a Aprobado y la API lo confirma',
      async () => {
        const changed = await changePlanStatus(page, fixtures.plan.title, 'approved')
        await page.waitForTimeout(600)
        const badge = await planBadge(page, fixtures.plan.title)
        const api = await call(request, session, 'GET', `/api/treatment-plans/${fixtures.plan.id}`)

        return {
          requestStatus: changed.status,
          obtained: `HTTP ${changed.status}; insignia "${badge}"; API ${api.body?.data?.status}`,
          verdict:
            (changed.status === 200 || changed.status === 201) &&
            badge === 'Aprobado' &&
            api.body?.data?.status === 'approved'
              ? 'OK'
              : 'DEFECTO'
        }
      }
    )

    await step(
      'status-in-progress',
      'cambiar el estado de aprobado a en progreso desde la tarjeta',
      'la tarjeta pasa a En Progreso y la API lo confirma',
      async () => {
        await dismissToasts(page)
        const changed = await changePlanStatus(page, fixtures.plan.title, 'in_progress')
        await page.waitForTimeout(800)
        const badge = await planBadge(page, fixtures.plan.title)
        const api = await call(request, session, 'GET', `/api/treatment-plans/${fixtures.plan.id}`)

        return {
          requestStatus: changed.status,
          obtained: `HTTP ${changed.status}; insignia "${badge}"; API ${api.body?.data?.status}`,
          verdict:
            (changed.status === 200 || changed.status === 201) &&
            badge === 'En Progreso' &&
            api.body?.data?.status === 'in_progress'
              ? 'OK'
              : 'DEFECTO'
        }
      }
    )

    await step(
      'invalid-transition',
      'pulsar «Completado» en un plan cuyas líneas siguen pendientes',
      'el 422 de la máquina de estados se explica en pantalla',
      async () => {
        await dismissToasts(page)

        const card = planCard(page, fixtures.plan.title)
        await card.locator('[data-status-toggle]').click({ timeout: 10000 })
        const menu = page.locator('[data-status-menu]').first()
        await menu.waitFor({ state: 'visible', timeout: 8000 })
        const options = await menu
          .locator('[data-status-option]')
          .evaluateAll(nodes => nodes.map(node => node.getAttribute('data-status-option')))

        const posted = waitForCall(
          page,
          'POST',
          /\/api\/treatment-plans\/\d+\/change-status/,
          () => true,
          30000
        )
        await menu.locator('[data-status-option="completed"]').click({ timeout: 8000 })
        const response = await posted
        const status = response?.status() ?? null
        const body = response ? await response.json().catch(() => null) : null
        const reason = Array.isArray(body?.errors?.status) ? body.errors.status[0] : null

        const toast = await waitForToast(page, null, TOAST_TIMEOUT)
        const messageVisible = reason ? await textContains(page, reason) : false
        const ok = status === 422 && messageVisible

        return {
          requestStatus: status,
          obtained:
            `opciones de la tarjeta ${JSON.stringify(options)}; transición ${status}; ` +
            `motivo del servidor "${reason}"; aviso visible "${toast}"; ` +
            `motivo del servidor en pantalla: ${messageVisible}`,
          verdict: ok ? 'OK' : status === 422 ? 'DEFECTO' : 'CONFUSO',
          detail: ok
            ? null
            : status === 422
              ? 'la página solo toasta «No se pudo cambiar el estado»: el 422 con su razón nunca se pinta ' +
                '(TreatmentPlansPage.onCardStatusChange captura el error y descarta el mensaje)'
              : 'la transición inválida no llegó a la API como 422'
        }
      }
    )

    await step(
      'edit-draft',
      'abrir «Editar» en el plan borrador creado y pulsar Guardar',
      'el formulario del plan llega con sus líneas y el guardado responde 2xx',
      async () => {
        if (!created?.id)
          return { verdict: 'NO PROBADO', reason: 'no hay plan creado en esta caminata' }

        await closeTop(page)
        await dismissToasts(page)

        const api = await call(request, session, 'GET', `/api/treatment-plans/${created.id}`)
        const apiItems = (api.body?.data?.items ?? []).length

        await planCard(page, created.title)
          .locator('[data-action="edit"]')
          .click({ timeout: 10000 })
        const modalOpen = await formLocator(page)
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        await page.waitForTimeout(300)

        const names = await page
          .locator('[data-plan-item-field="name"] input')
          .evaluateAll(nodes => nodes.map(node => node.value))
        const formItems = names.length

        const renamed = `${created.title} (editado)`
        await fillControl(page, '[data-plan-field="title"] input', renamed)

        const put = waitForCall(
          page,
          'PUT',
          new RegExp(`/api/treatment-plans/${created.id}`),
          () => true,
          8000
        )
        await page
          .locator('[data-plan-submit]')
          .click({ timeout: 10000 })
          .catch(() => {})
        const response = await put
        const status = response?.status() ?? null
        await page.waitForTimeout(600)

        const generalError = (
          await page
            .locator('[data-plan-modal] [role="alert"]')
            .first()
            .innerText({ timeout: 3000 })
            .catch(() => '')
        )
          .replace(/\s+/g, ' ')
          .trim()

        await settlePlans(page)
        await page.waitForTimeout(400)
        const cards = await readPlanCards(page)
        const found = (cards.cards ?? []).some(entry => entry.title === renamed)

        if (found) created.title = renamed

        const carriesLines = formItems === apiItems
        const ok = modalOpen && carriesLines && (status === 200 || status === 201) && found

        return {
          requestStatus: status,
          obtained:
            `formulario ${modalOpen ? 'abierto' : 'no abierto'}; líneas del plan ${apiItems} en la API y ` +
            `${formItems} en el formulario ${JSON.stringify(names)}; HTTP ${status}; ` +
            `aviso del formulario "${generalError}"; tarjeta con el título nuevo: ${found}`,
          verdict: ok ? 'OK' : 'DEFECTO',
          arithmetic: { apiItems, formItems, status },
          detail: ok
            ? null
            : !carriesLines
              ? 'el formulario de edición abre el plan sin sus líneas (la tarjeta no las trae y el modal no las pide) ' +
                'y el envío se detiene en «Agrega al menos un procedimiento con nombre», así que un plan existente ' +
                'no se puede editar sin volver a teclearlo'
              : 'la edición no dejó la tarjeta con el título nuevo',
          extra: { modalOpen, apiItems, formItems, names, generalError, status }
        }
      }
    )

    await step(
      'absent-surfaces',
      'buscar prioridad, secuencia, alternativa A/B, versión nueva y consumo de ítem',
      'la issue las nombra y el SPA no las pinta: la ausencia se mide en el DOM, en el manifiesto y en la API',
      async () => {
        // The previous step may leave the edit modal open (its dirty guard turns
        // Escape into a confirmation); a fresh document is the clean surface this
        // measurement needs.
        const reload = waitForCall(page, 'GET', '/api/treatment-plans', () => true, LOAD_TIMEOUT)
        await page.goto(PLANS_ROUTE)
        await reload
        await settlePlans(page)

        const detail = await call(
          request,
          session,
          'GET',
          `/api/treatment-plans/${fixtures.plan.id}`
        )
        const payload = detail.body?.data ?? null
        const item = payload?.items?.[0] ?? null

        const apiPublishes = {
          priority: item?.priority ?? null,
          sequence: item?.sequence ?? null,
          executed_quantity: item?.executed_quantity ?? null,
          consumptions: Array.isArray(item?.consumptions) ? item.consumptions.length : null,
          current_version: payload?.current_version?.version_number ?? null
        }

        await planCard(page, fixtures.plan.title)
          .locator('[data-action="view"]')
          .click({ timeout: 10000 })
        await page
          .locator('[data-detail-body]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})

        const detailText = (
          await page
            .locator('[data-detail-body]')
            .innerText({ timeout: 5000 })
            .catch(() => '')
        ).replace(/\s+/g, ' ')
        const domTerms = {
          prioridad: /prioridad/i.test(detailText),
          secuencia: /secuencia/i.test(detailText),
          alternativa: /alternativa/i.test(detailText),
          version: /versi[oó]n(es)?\b/i.test(detailText),
          consumo: /consumo|consumid|ejecutad/i.test(detailText)
        }

        // The form is measured on its own (the create dialog), not stacked over
        // the detail: the stack is the previous step's measurement, and closing
        // it under the detail raises the defect that step already declares.
        await closeTop(page)
        await page.locator('[data-create-plan]').click({ timeout: 10000 })
        await formLocator(page)
          .waitFor({ state: 'visible', timeout: 12000 })
          .catch(() => {})
        const modalText = (
          await page
            .locator('[data-plan-modal]')
            .innerText({ timeout: 5000 })
            .catch(() => '')
        ).replace(/\s+/g, ' ')
        const modalTerms = {
          prioridad: /prioridad/i.test(modalText),
          secuencia: /secuencia/i.test(modalText),
          alternativa: /alternativa/i.test(modalText),
          version: /versi[oó]n(es)?\b/i.test(modalText),
          consumo: /consumo|consumid|ejecutad/i.test(modalText)
        }

        const sourceScan = scanSources(
          'priority|sequence|alternatives|executed_quantity|consumptions|current_version',
          ['resources/js/modules/treatment-plans']
        )
        const modalScan = scanSources(
          'priority|sequence|alternatives|executed_quantity|consumptions|current_version',
          ['resources/js/composables/useTreatmentPlans.js']
        )

        await closeTop(page)

        const presentInDom = Object.entries({ ...domTerms, ...modalTerms }).filter(
          ([, value]) => value
        )
        const apiHas = Object.values(apiPublishes).filter(
          value => value !== null && value !== undefined
        )
        const ok = presentInDom.length === 0 && apiHas.length === 5

        return {
          requestStatus: detail.status,
          obtained:
            `API publica ${JSON.stringify(apiPublishes)}; DOM del detalle ${JSON.stringify(domTerms)}; ` +
            `DOM del formulario ${JSON.stringify(modalTerms)}; ` +
            `coincidencias en resources/js/modules/treatment-plans: ${sourceScan.total}; ` +
            `en el composable: ${modalScan.total}`,
          verdict: ok ? 'OK' : 'DEFECTO',
          arithmetic: {
            domMatches: presentInDom.map(([key]) => key).length,
            apiFields: apiHas.length
          },
          detail:
            'las cinco superficies que la issue nombra existen en el payload (priority, sequence, ' +
            'executed_quantity, consumptions, current_version) y no existen en el SPA: ni el detalle ni el ' +
            'formulario las pintan ni hay acción en el manifiesto para versionar, priorizar, secuenciar, ' +
            'elegir alternativa o consumir un ítem',
          extra: {
            api: apiPublishes,
            dom: { detail: domTerms, modal: modalTerms },
            sourceScan: {
              modules: sourceScan.total,
              composable: modalScan.total,
              files: sourceScan.files.map(file => file.file)
            }
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
      failure => [422, 403].includes(failure.status),
      'probe: the walk submits a stale transition, a 422 the surface must paint'
    )
    evidence.created = created
    evidence.totals = {
      steps: evidence.steps.length,
      ok: evidence.steps.filter(entry => entry.verdict === 'OK').length,
      findings: evidence.findings.length
    }

    audit('walk-odontologo.json', evidence)
  }

  expect(evidence.steps.length, 'steps of the plan walk').toBeGreaterThanOrEqual(10)
  expect(
    evidence.steps.filter(entry => entry.shot === null),
    'steps without a capture'
  ).toEqual([])

  // The nested-dialog step is the only one allowed to raise a page error, and it
  // declares it: closing the edit form under an open detail re-renders the detail
  // with `plan = null`. Any other page error fails the walk.
  const undeclared = state.pageErrors.filter(error => error.step !== NESTED_STEP)
  expect(undeclared, 'page errors outside the nested-dialog probe').toEqual([])
})
