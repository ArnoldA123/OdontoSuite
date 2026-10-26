// Plan #13 (issue #370, U7b) — layer 4: the five specialty records, walked.
//
// One walkthrough as `administrador` (the only role whose form offers all five
// specialties): select the patient, open each tab, create one record per
// specialty through the interface, edit one, open its detail and delete it with
// the global confirm. A second row measures the role matrix — which tabs each
// clinical role is offered against the 403 the API answers for the same
// specialty (`StoreSpecialtyRecordRequest::authorize`).

import { test, expect } from 'playwright/test'

import { classifyFailures, collectDiagnostics, loginAs, primeSession } from '../harness.js'

import { ensureFixtures, fixtureSummary, registerCreated } from './fixtures.js'

import {
  ACTION_TIMEOUT,
  DESKTOP,
  DIALOG_TIMEOUT,
  LOAD_TIMEOUT,
  SPECIALTIES_ROUTE,
  audit,
  call,
  closeAllDialogs,
  dismissToasts,
  fillControl,
  lastWrite,
  localDay,
  openSelect,
  pickPatient,
  recorder,
  settleSpecialties,
  stamp,
  waitForCall,
  waitForToast,
  walkTotals,
  watchRequests
} from './helpers.js'

const DAY = localDay()

const tabButton = (page, label) => page.locator('[role="tab"]').filter({ hasText: label }).first()

const waitPanel = (page, timeout = LOAD_TIMEOUT) =>
  page
    .waitForFunction(
      () =>
        !document.querySelector('[data-specialty-records-loading]') &&
        Boolean(
          document.querySelector('[data-specialty-record-card]') ||
          document.querySelector('[data-specialty-records-empty]') ||
          document.querySelector('[data-specialty-records-error]')
        ),
      undefined,
      { timeout, polling: 120 }
    )
    .catch(() => {})

const readPanel = page =>
  page.evaluate(() => {
    const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
    const cards = Array.from(document.querySelectorAll('[data-specialty-record-card]'))
    const kpi = key => text(document.querySelector(`[data-kpi-value="${key}"]`))
    const tabs = Array.from(document.querySelectorAll('[role="tab"]')).map(tab => ({
      label: text(tab.querySelector('span.truncate')),
      badge: (tab.innerText ?? '')
        .trim()
        .replace(text(tab.querySelector('span.truncate')), '')
        .trim(),
      selected: tab.getAttribute('aria-selected') === 'true'
    }))

    return {
      active: tabs.find(tab => tab.selected)?.label ?? null,
      tabs,
      cards: cards.map(card => ({
        specialty: card.getAttribute('data-specialty'),
        title: text(card.querySelector('[data-record-title]')),
        status: text(card.querySelector('[data-record-status], [class*="inline-flex"]')),
        actions: Array.from(card.querySelectorAll('[data-record-action]')).map(button =>
          button.getAttribute('data-record-action')
        )
      })),
      empty: text(document.querySelector('[data-specialty-records-empty]')) || null,
      emptyActions: Array.from(
        document.querySelectorAll('[data-specialty-records-empty] button')
      ).map(text),
      kpis: {
        total: kpi('total'),
        inProgress: kpi('in_progress'),
        completed: kpi('completed'),
        last: kpi('last')
      }
    }
  })

const SPEC_CREATIONS = [
  {
    key: 'implantologia',
    label: 'Implantología',
    fills: [
      ['[data-field="implant_brand"] input', 'Straumann'],
      ['[data-field="implant_model"] input', 'BLX'],
      ['[data-field="implant_diameter"] input', '4.1'],
      ['[data-field="implant_length"] input', '10'],
      ['[data-field="batch_number"] input', 'LOTE-UI'],
      ['[data-field="placement_date"] input', DAY],
      ['[data-field="surgical_notes"] textarea', 'AUDIT-U7 implante colocado por la interfaz']
    ],
    selects: [['[data-dental-piece-select] button[aria-haspopup="true"]', '11']]
  },
  {
    key: 'ortodoncia',
    label: 'Ortodoncia',
    titleMarker: 'AUDIT-U7 ortodoncia de la interfaz',
    fills: [
      ['[data-field="treatment_type"] input', 'AUDIT-U7 ortodoncia de la interfaz'],
      ['[data-field="treatment_start_date"] input', DAY],
      ['[data-field="treatment_objectives"] textarea', 'AUDIT-U7 alinear la arcada superior']
    ],
    selects: []
  },
  {
    key: 'endodoncia',
    label: 'Endodoncia',
    fills: [
      ['[data-field="tooth_number"] input', '11'],
      ['[data-field="canal_count"] input', '2'],
      ['[data-field="pulp_diagnosis"] input', 'AUDIT-U7 pulpitis irreversible'],
      ['[data-field="canal_preparation_notes"] textarea', 'AUDIT-U7 conductos preparados']
    ],
    selects: [['[data-dental-piece-select] button[aria-haspopup="true"]', '11']]
  },
  {
    key: 'rehabilitacion',
    label: 'Rehabilitación',
    fills: [
      ['[data-field="material_type"] input', 'AUDIT-U7 zirconio'],
      ['[data-field="laboratory_name"] input', 'AUDIT-U7 laboratorio de la sesión']
    ],
    selects: [
      ['[data-dental-piece-select] button[aria-haspopup="true"]', '11'],
      ['[data-field="prosthesis_type"] button[aria-haspopup="true"]', 'Corona']
    ]
  },
  {
    key: 'cirugia_oral',
    label: 'Cirugía Oral',
    fills: [
      ['[data-field="procedure_type"] input', 'AUDIT-U7 extracción'],
      ['[data-field="surgery_site"] input', 'AUDIT-U7 cuadrante superior derecho'],
      ['[data-field="surgical_notes"] textarea', 'AUDIT-U7 exodoncia sin complicaciones']
    ],
    selects: []
  }
]

test('@U7 · U7b especialidades: cinco tabs, alta por especialidad, edición, detalle y borrado', async ({
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
    route: SPECIALTIES_ROUTE,
    role: 'administrador',
    ...stamp(),
    shotDir: 'walk/specialties',
    scenario:
      'elegir el paciente de la sesión, abrir las cinco tabs, crear un registro por especialidad, ' +
      'editar el de ortodoncia, abrir su detalle y eliminarlo con la confirmación global',
    fixtures: fixtureSummary(fixtures),
    steps: [],
    findings: [],
    pageErrors: [],
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }

  const step = recorder(page, evidence, state)
  const requests = watchRequests(page)
  const created = {}

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    await page.goto(SPECIALTIES_ROUTE)
    await settleSpecialties(page)

    await step(
      'no-patient',
      'abrir la página sin paciente seleccionado',
      'la página pide un paciente y no ofrece crear un registro',
      async () => {
        const ui = await readPanel(page)
        const noPatient = await page.locator('[data-specialty-records-no-patient]').count()
        const disabled = await page
          .locator('[data-new-record]')
          .isDisabled()
          .catch(() => null)

        return {
          obtained: `estado «selecciona un paciente»=${noPatient > 0}; tabs=${ui.tabs.length}; «Nuevo registro» disabled=${disabled}`,
          verdict: noPatient > 0 && disabled === true ? 'OK' : 'DEFECTO',
          detail:
            disabled === true
              ? 'El botón primario está deshabilitado sin un texto que diga qué falta; el estado de la lista sí lo dice, pero el botón no.'
              : null,
          extra: { noPatient, disabled }
        }
      }
    )

    // The empty patient has no record in any of the five specialties: each tab
    // is measured on its own empty state, which is the #370 criterion.
    await step(
      'empty-patient-tabs',
      'elegir el paciente sin registros y recorrer las cinco tabs',
      'cada tab vacía explica qué falta y ofrece la acción que la resuelve',
      async () => {
        await pickPatient(page, '[data-patient-card]', fixtures.patients.empty.document)
        await waitPanel(page)
        await page.waitForTimeout(400)

        const perTab = []

        for (const entry of SPEC_CREATIONS) {
          await tabButton(page, entry.label).click({ timeout: 10000 })
          await page.waitForTimeout(300)
          const ui = await readPanel(page)
          perTab.push({
            label: entry.label,
            cards: ui.cards.length,
            empty: ui.empty,
            actions: ui.emptyActions
          })
        }

        const withoutAction = perTab.filter(tab => tab.actions.length === 0)

        return {
          obtained: `tabs vacías ${perTab.length}; con CTA ${perTab.filter(tab => tab.actions.length > 0).length}; sin CTA ${withoutAction.length}`,
          verdict:
            perTab.length === 5 &&
            perTab.every(tab => tab.cards === 0) &&
            withoutAction.length === 0
              ? 'OK'
              : 'DEFECTO',
          detail: withoutAction.length
            ? `sin acción: ${JSON.stringify(withoutAction.map(tab => tab.label))}`
            : null,
          extra: { perTab }
        }
      }
    )

    // A fresh visit so the in-page selector offers the search field again.
    await page.reload()
    await settleSpecialties(page)

    await step(
      'select-patient',
      'buscar al paciente de la sesión en el selector y elegirlo',
      'las cinco tabs aparecen con su badge y la especialidad activa pinta su registro',
      async () => {
        const picked = await pickPatient(
          page,
          '[data-patient-card]',
          fixtures.patients.clinical.document
        )
        await waitPanel(page)
        await page.waitForTimeout(400)
        const ui = await readPanel(page)

        return {
          obtained: `paciente=${picked.ok}; tabs=${JSON.stringify(ui.tabs.map(tab => tab.label))}; tarjetas=${ui.cards.length}; KPIs=${JSON.stringify(ui.kpis)}`,
          verdict: picked.ok && ui.tabs.length === 5 ? 'OK' : 'DEFECTO',
          detail: picked.ok ? null : picked.reason,
          extra: { picked, ui }
        }
      }
    )

    // --- the five tabs, each with the fixture record of its specialty ------
    for (const entry of SPEC_CREATIONS) {
      await step(
        `tab-${entry.key}`,
        `abrir la tab «${entry.label}»`,
        'la tab pinta el registro de la sesión, sus KPIs y las acciones del registro',
        async () => {
          await tabButton(page, entry.label).click({ timeout: 10000 })
          await page.waitForTimeout(350)
          const ui = await readPanel(page)
          const api = await call(
            request,
            session,
            'GET',
            `/api/specialty-records/patient/${fixtures.patients.clinical.id}/${entry.key}`
          )
          const apiRows = Array.isArray(api.body?.data) ? api.body.data.length : null

          return {
            requestStatus: api.status,
            obtained: `activa «${ui.active}»; tarjetas ${ui.cards.length} (API ${apiRows}); acciones ${JSON.stringify(ui.cards[0]?.actions ?? [])}`,
            verdict: ui.active === entry.label && ui.cards.length === apiRows ? 'OK' : 'DEFECTO',
            detail: null,
            extra: { ui, apiRows }
          }
        }
      )
    }

    // --- one creation per specialty, through the form ----------------------
    for (const entry of SPEC_CREATIONS) {
      await step(
        `create-${entry.key}`,
        `crear un registro de ${entry.label} desde el formulario`,
        'el alta responde 201, la tarjeta nueva aparece y el badge del tab suma uno',
        async () => {
          await tabButton(page, entry.label).click({ timeout: 10000 })
          await page.waitForTimeout(300)
          const before = await readPanel(page)

          await page.locator('[data-new-record]').click({ timeout: 10000 })
          const formOpen = await page
            .locator('[data-specialty-record-form]')
            .waitFor({ state: 'visible', timeout: 10000 })
            .then(() => true)
            .catch(() => false)

          if (!formOpen) {
            return {
              obtained: 'el formulario de alta no abrió',
              verdict: 'DEFECTO'
            }
          }

          for (const [selector, value] of entry.fills) {
            await fillControl(page, selector, value)
          }

          for (const [selector, option] of entry.selects) {
            await openSelect(page, selector, option)
          }

          const submitted = waitForCall(
            page,
            'POST',
            '/api/specialty-records',
            () => true,
            ACTION_TIMEOUT
          )
          await page.locator('[data-modal-submit]').click({ timeout: 10000 })
          const response = await submitted
          const status = response?.status() ?? null
          const body = response ? await response.json().catch(() => null) : null
          const id = body?.data?.id ?? null

          if (id) {
            created[entry.key] = { id, specialty: entry.key }
            registerCreated('specialty-record', { id, specialty: entry.key })
          }

          await waitForToast(page, 'Registro creado', 12000)
          await page.waitForTimeout(900)
          const after = await readPanel(page)
          const errors = body?.errors ? Object.keys(body.errors) : null

          return {
            requestStatus: status,
            request: lastWrite(requests),
            obtained: `POST /api/specialty-records → ${status}${errors ? ` (campos: ${errors.join(', ')})` : ''}; tarjetas ${before.cards.length} → ${after.cards.length}`,
            verdict: status === 201 && after.cards.length > before.cards.length ? 'OK' : 'DEFECTO',
            detail:
              status === 201
                ? null
                : `el alta no se completó: ${JSON.stringify(body?.errors ?? body?.message ?? null)}`,
            extra: { before: before.cards.length, after: after.cards.length, id }
          }
        }
      )
    }

    // --- edit the orthodontics record ------------------------------------
    await step(
      'edit-ortodoncia',
      'editar el registro de ortodoncia creado por la sesión',
      'el formulario abre con la especialidad fijada, guarda (200) y la tarjeta muestra el valor nuevo',
      async () => {
        const target = created.ortodoncia
        await tabButton(page, 'Ortodoncia').click({ timeout: 10000 })
        await waitPanel(page)
        await page.waitForTimeout(350)
        const card = page
          .locator('[data-specialty-record-card]')
          .filter({ hasText: SPEC_CREATIONS[1].titleMarker })
          .first()

        await card.locator('[data-record-action="edit"]').click({ timeout: 10000 })
        const formOpen = await page
          .locator('[data-specialty-record-form]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!formOpen) {
          return { obtained: 'el formulario de edición no abrió', verdict: 'DEFECTO' }
        }

        const specialtyLocked = await page
          .locator('[data-specialty-select] button')
          .isDisabled()
          .catch(() => null)

        await fillControl(
          page,
          '[data-field="treatment_type"] input',
          'AUDIT-U7 ortodoncia editada'
        )

        const submitted = waitForCall(
          page,
          'PUT',
          `/api/specialty-records/${target.id}`,
          () => true,
          ACTION_TIMEOUT
        )
        await page.locator('[data-modal-submit]').click({ timeout: 10000 })
        const response = await submitted
        const status = response?.status() ?? null
        await waitForToast(page, 'Registro actualizado', 12000)
        await page.waitForTimeout(900)

        const edited = await page
          .locator('[data-specialty-record-card]')
          .filter({ hasText: 'AUDIT-U7 ortodoncia editada' })
          .count()

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `PUT → ${status}; especialidad bloqueada=${specialtyLocked}; tarjeta con el valor nuevo=${edited}`,
          verdict: status === 200 && edited > 0 ? 'OK' : 'DEFECTO',
          detail: status === 200 ? null : 'la edición no persiste en la tarjeta',
          extra: { specialtyLocked, edited, id: target.id }
        }
      }
    )

    // --- the detail -------------------------------------------------------
    await step(
      'detail',
      'abrir el detalle del registro editado',
      'el detalle pinta las secciones y los campos del registro, y ofrece editar',
      async () => {
        await tabButton(page, 'Ortodoncia').click({ timeout: 10000 })
        await waitPanel(page)
        await page.waitForTimeout(350)
        await page
          .locator('[data-specialty-record-card]')
          .filter({ hasText: 'AUDIT-U7 ortodoncia editada' })
          .first()
          .locator('[data-record-action="view"]')
          .click({ timeout: 10000 })
        await page
          .locator('[data-specialty-record-detail]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .catch(() => {})
        await page.waitForTimeout(400)

        const detail = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
          const dialog = document
            .querySelector('[data-specialty-record-detail]')
            ?.closest('[role="dialog"]')
          return {
            heading: text(dialog?.querySelector('h2')),
            patient: text(document.querySelector('[data-detail-patient]')),
            sections: Array.from(document.querySelectorAll('[data-detail-section]')).map(node =>
              node.getAttribute('data-detail-section')
            ),
            fields: Array.from(document.querySelectorAll('[data-detail-field]')).length,
            actions: Array.from(dialog?.querySelectorAll('button') ?? []).map(
              button => (button.innerText ?? '').trim() || button.getAttribute('aria-label')
            )
          }
        })

        await closeAllDialogs(page)

        return {
          obtained: `detalle «${detail.heading}»; secciones ${JSON.stringify(detail.sections)}; ${detail.fields} campos; acciones ${JSON.stringify(detail.actions)}`,
          verdict: detail.sections.length > 0 && detail.fields > 0 ? 'OK' : 'DEFECTO',
          detail: null,
          extra: { detail }
        }
      }
    )

    // --- delete it with the global confirm -------------------------------
    await step(
      'delete',
      'eliminar el registro de ortodoncia con la confirmación global',
      'la confirmación pide confirmar, el DELETE responde 200 y la tarjeta desaparece',
      async () => {
        const target = created.ortodoncia
        await tabButton(page, 'Ortodoncia').click({ timeout: 10000 })
        await waitPanel(page)
        await page.waitForTimeout(350)
        await page
          .locator('[data-specialty-record-card]')
          .filter({ hasText: 'AUDIT-U7 ortodoncia editada' })
          .first()
          .locator('[data-record-action="delete"]')
          .click({ timeout: 10000 })

        const confirm = page.locator('[role="alertdialog"]').last()
        const opened = await confirm
          .waitFor({ state: 'visible', timeout: DIALOG_TIMEOUT })
          .then(() => true)
          .catch(() => false)
        const message = await confirm.innerText({ timeout: 4000 }).catch(() => '')

        const deleted = waitForCall(
          page,
          'DELETE',
          `/api/specialty-records/${target.id}`,
          () => true,
          ACTION_TIMEOUT
        )
        await confirm
          .locator('button')
          .filter({ hasText: 'Eliminar' })
          .last()
          .click({ timeout: 10000 })
        const response = await deleted
        const status = response?.status() ?? null
        await page.waitForTimeout(900)
        await waitPanel(page)

        const remaining = await page
          .locator('[data-specialty-record-card]')
          .filter({ hasText: 'AUDIT-U7 ortodoncia editada' })
          .count()

        if (status === 200) {
          const row = (fixtures.createdByWalk ?? []).find(entry => entry.id === target.id)
          if (row) row.deleted = true
        }

        return {
          requestStatus: status,
          request: lastWrite(requests),
          obtained: `confirmación=${opened}; DELETE → ${status}; tarjetas restantes con ese título=${remaining}`,
          verdict: opened && status === 200 && remaining === 0 ? 'OK' : 'DEFECTO',
          detail: opened
            ? null
            : `la confirmación no abrió: ${JSON.stringify(message.slice(0, 120))}`,
          extra: { opened, message: message.slice(0, 200), remaining }
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
    evidence.created = created
    evidence.totals = walkTotals(evidence)
    audit('walk-specialties.json', evidence)
  }

  expect(evidence.steps.length, 'steps of the specialty walkthrough').toBeGreaterThanOrEqual(16)
  expect(
    evidence.steps.filter(entry => entry.shot === null),
    'steps without a capture'
  ).toEqual([])
  expect(state.pageErrors, 'page errors during the specialty walkthrough').toEqual([])
})

// --- the role × specialty write matrix -------------------------------------

const MATRIX_ROLES = Object.freeze([
  {
    role: 'odontologo',
    tabs: ['Ortodoncia', 'Endodoncia', 'Rehabilitación', 'Cirugía Oral'],
    hidden: 'Implantología',
    allowed: 'ortodoncia',
    refused: 'implantologia'
  },
  {
    role: 'implantologo',
    tabs: ['Implantología'],
    hidden: 'Ortodoncia',
    allowed: 'implantologia',
    refused: 'ortodoncia'
  }
])

for (const entry of MATRIX_ROLES) {
  test(`@U7 · U7b matriz rol × especialidad: ${entry.role}`, async ({ page, request }) => {
    test.setTimeout(300000)

    const session = await loginAs(request, entry.role)
    const fixtures = await ensureFixtures(request)
    if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

    const diagnostics = collectDiagnostics(page)
    const state = { pageErrors: [], currentStep: 'setup' }
    page.on('pageerror', error =>
      state.pageErrors.push({ message: error.message, step: state.currentStep })
    )

    const evidence = {
      route: SPECIALTIES_ROUTE,
      role: entry.role,
      ...stamp(),
      shotDir: `walk/specialties-${entry.role}`,
      expectedTabs: entry.tabs,
      expectedHidden: entry.hidden,
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
      await page.goto(SPECIALTIES_ROUTE)
      await settleSpecialties(page)
      await pickPatient(page, '[data-patient-card]', fixtures.patients.clinical.document)
      await waitPanel(page)
      await page.waitForTimeout(400)

      await step(
        'tabs-offered',
        `abrir la página como ${entry.role} y elegir el paciente`,
        `la interfaz ofrece ${JSON.stringify(entry.tabs)} y no ofrece ${entry.hidden}`,
        async () => {
          const ui = await readPanel(page)
          const labels = ui.tabs.map(tab => tab.label)
          const offersHidden = labels.includes(entry.hidden)
          const missing = entry.tabs.filter(label => !labels.includes(label))

          return {
            obtained: `tabs ofrecidas ${JSON.stringify(labels)}; falta ${JSON.stringify(missing)}; ofrece la excluida=${offersHidden}`,
            verdict: missing.length === 0 && !offersHidden ? 'OK' : 'DEFECTO',
            detail: offersHidden
              ? `la interfaz ofrece «${entry.hidden}», que la API rechaza para este rol`
              : missing.length
                ? `la interfaz no ofrece ${JSON.stringify(missing)}`
                : null,
            extra: { labels, ui }
          }
        }
      )

      await step(
        'api-allowed',
        `enviar por API un registro de ${entry.allowed}, la especialidad de este rol`,
        'la autorización pasa y el cuerpo incompleto responde 422 (no 403)',
        async () => {
          const response = await call(request, session, 'POST', '/api/specialty-records', {
            specialty: entry.allowed,
            patient_id: fixtures.patients.clinical.id
          })

          return {
            requestStatus: response.status,
            obtained: `POST /api/specialty-records (${entry.allowed}, cuerpo incompleto) → ${response.status}`,
            verdict: response.status === 422 ? 'OK' : 'DEFECTO',
            detail:
              response.status === 422
                ? 'La autorización del FormRequest deja pasar al rol y el 422 es la validación del cuerpo: la matriz rol × especialidad se sostiene.'
                : `se esperaba 422 y llegó ${response.status}: ${response.message ?? ''}`,
            extra: { errors: response.errors ? Object.keys(response.errors) : null }
          }
        }
      )

      await step(
        'api-refused',
        `enviar por API un registro de ${entry.refused}, la especialidad de otro rol`,
        'la API responde 403 antes de validar el cuerpo',
        async () => {
          const response = await call(request, session, 'POST', '/api/specialty-records', {
            specialty: entry.refused,
            patient_id: fixtures.patients.clinical.id
          })

          return {
            requestStatus: response.status,
            obtained: `POST /api/specialty-records (${entry.refused}) → ${response.status}`,
            verdict: response.status === 403 ? 'OK' : 'DEFECTO',
            detail: response.status === 403 ? null : `se esperaba 403 y llegó ${response.status}`,
            extra: { message: response.message }
          }
        }
      )
    } finally {
      await closeAllDialogs(page).catch(() => {})
      await dismissToasts(page)

      evidence.pageErrors = state.pageErrors
      evidence.consoleErrors = diagnostics.consoleErrors
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'owner')
      evidence.totals = walkTotals(evidence)
      audit(`walk-specialties-${entry.role}.json`, evidence)
    }

    expect(evidence.steps.length, `steps of the ${entry.role} matrix`).toBe(3)
    expect(state.pageErrors, `page errors during the ${entry.role} matrix`).toEqual([])
  })
}

// The assistant holds none of the specialty permissions: the surface must not
// offer her a tab and the API must answer 403 for the aggregate read.
test('@U7 · U7b matriz rol × especialidad: asistente', async ({ page, request }) => {
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
    route: SPECIALTIES_ROUTE,
    role: 'asistente',
    ...stamp(),
    shotDir: 'walk/specialties-asistente',
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
    await page.goto(SPECIALTIES_ROUTE)
    await settleSpecialties(page)

    await step(
      'no-specialties',
      'abrir la página como asistente',
      'la página declara que el rol no tiene especialidades y no inventa tabs vacías',
      async () => {
        const ui = await page.evaluate(() => {
          const text = node => (node?.innerText ?? '').trim().replace(/\s+/g, ' ')
          return {
            tabs: document.querySelectorAll('[role="tab"]').length,
            noSpecialties: text(document.querySelector('[data-specialty-records-no-specialties]')),
            newRecordDisabled: document.querySelector('[data-new-record]')?.disabled ?? null,
            sidebar: Array.from(document.querySelectorAll('#primary-sidebar a[href]')).map(link =>
              link.getAttribute('href')
            )
          }
        })

        return {
          obtained: `tabs=${ui.tabs}; mensaje «${ui.noSpecialties.slice(0, 80)}»; entrada en el sidebar=${ui.sidebar.includes(SPECIALTIES_ROUTE)}`,
          verdict: ui.tabs === 0 && !ui.sidebar.includes(SPECIALTIES_ROUTE) ? 'OK' : 'DEFECTO',
          detail: null,
          extra: { ui }
        }
      }
    )

    await step(
      'api-refused',
      'pedir por API los registros del paciente',
      'la API responde 403 y la página no puede mostrar datos que no le corresponden',
      async () => {
        const response = await call(
          request,
          session,
          'GET',
          `/api/specialty-records/patient/${fixtures.patients.clinical.id}/all`
        )

        return {
          requestStatus: response.status,
          obtained: `GET /api/specialty-records/patient/{id}/all → ${response.status}`,
          verdict: response.status === 403 ? 'OK' : 'DEFECTO',
          detail: response.status === 403 ? null : `se esperaba 403 y llegó ${response.status}`,
          extra: { message: response.message }
        }
      }
    )
  } finally {
    await closeAllDialogs(page).catch(() => {})
    evidence.pageErrors = state.pageErrors
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'member')
    evidence.totals = walkTotals(evidence)
    audit('walk-specialties-asistente.json', evidence)
  }

  expect(evidence.steps.length, 'steps of the asistente matrix').toBe(2)
  expect(state.pageErrors, 'page errors during the asistente matrix').toEqual([])
})
