// Plan #13 (issue #372, U9) — layer 4: the appointment-types ABM, action by action.
//
// `/appointment-types` filters in the browser (one `status=all` load, search and
// status applied to the copy), writes through the shared edit modal, and deep
// links the detail's «Editar» back into that same modal (`?edit=<id>`). The
// walkthrough measures all three, plus the omission the issue names: the tab that
// deactivates a type lives in the form, not in a row action, and the delete door
// of the list refuses nothing on its own — the API is the one that answers 409
// when the type still has appointments.
//
// The type this walk creates is deactivated through the form and deleted through
// the row, so the run leaves nothing behind. `requires_materials` is not a
// control: the API derives it from the default procedure's recipe (#295, #444),
// so the edit modal declares it and the detail names where it comes from.
// `DEFECTO` / `NO-OP` / `CONFUSO` are evidence, never a thrown failure: the only
// hard assertions are that no `pageerror` was raised and that every declared
// step was recorded.

import { test, expect } from 'playwright/test'

import { classifyFailures, collectDiagnostics, primeSession, settleLayout } from '../harness.js'

import { ensureFixtures, fixtureSummary } from './fixtures.js'

import {
  DESKTOP,
  SESSION,
  api,
  audit,
  classifyConsole,
  closeDialog,
  confirmDialog,
  dismissToasts,
  fieldErrors,
  fillField,
  gotoSurface,
  lastCall,
  login,
  markExpected,
  pickOption,
  requestOf,
  respondTo,
  rowCount,
  settle,
  shot,
  tableHeaders,
  tablePrimitive,
  toastText,
  visibleTexts,
  walkCounts,
  walkStep,
  watchRequests
} from './helpers.js'

const SURFACE = '/appointment-types'
const SLUG = 'appointment-types'
const MARKER = 'AUDIT-U9'
const ROWS = 'main [data-type-row]'
const SEARCH = '[data-type-search] input'
const DIALOG = '[role="dialog"]:visible'
const CREATE_SUBMIT = 'button[type="submit"][form="form-new-type"]'
const EDIT_SUBMIT = 'button[type="submit"][form="form-edit-type"]'

const DECLARED_CONSOLE = [
  {
    pattern: /Failed to load resource: .*500/,
    reason: 'el paso `states` provoca un 500 para medir el estado de error de la lista'
  }
]

const rowNamed = (page, name) => page.locator(ROWS).filter({ hasText: name }).first()

const buttonLabels = async locator =>
  (
    await locator
      .locator('button')
      .allInnerTexts()
      .catch(() => [])
  )
    .map(label => label.trim().replace(/\s+/g, ' '))
    .filter(Boolean)

const listText = page =>
  page.evaluate(() => (document.querySelector('main')?.innerText ?? '').replace(/\s+/g, ' '))

const search = async (page, value) => {
  await fillField(page, 'Buscar tipos de cita por nombre', value)
  await settleLayout(page)
  await page.waitForTimeout(150)
}

const filterCount = async page =>
  (await visibleTexts(page, '[data-filter-count]'))[0]?.trim() ?? null

test('@U9 walks tipos de cita', async ({ page }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const session = await login('administrador')
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const created = { appointmentType: null, deleted: false }

  const walkName = `${MARKER}-${fixtures.token} tipo de la caminata`
  const walkColor = '#2563EB'

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks tipos de cita',
    role: 'administrador',
    steps: [],
    counts: null,
    fixtures: { marker: fixtures.marker, ok: fixtures.ok, token: fixtures.token },
    created: { name: walkName, color: walkColor },
    pageErrors: [],
    consoleErrors: [],
    consoleClassified: null,
    failedRequests: null,
    cleanup: []
  }

  const step = walkStep(page, evidence)

  page.setDefaultTimeout(8000)

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)
    await gotoSurface(page, SURFACE)

    await step(
      'list',
      'Abrir /appointment-types como administración',
      'La lista pinta sus filas, sus cabeceras, el estado de cada tipo y los KPIs del catálogo',
      async entry => {
        const painted = await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        const rows = await rowCount(page, ROWS)
        const headers = await tableHeaders(page)
        const statuses = await visibleTexts(page, `${ROWS} td:nth-child(5)`)
        const kpis = await visibleTexts(page, '[data-kpi-value]')
        const count = await filterCount(page)
        const primitive = await tablePrimitive(page)
        const call = lastCall(requests, 'GET', '/api/appointment-types')
        const badged =
          statuses.length === rows && statuses.every(label => /^(Activo|Inactivo)$/.test(label))
        const headersNamed = headers.includes('NOMBRE') && headers.includes('ESTADO')

        entry.obtained = `${rows} filas · cabeceras ${headers.length} ${JSON.stringify(headers)} · estados ${JSON.stringify([...new Set(statuses)])} · «${count ?? '—'}» · KPIs ${JSON.stringify(kpis)} · lista: ${primitive.primitive} (data-table-scroll ${primitive.dataTables}, affordance de scroll ${primitive.scrollAffordance})`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = { painted, rows, headers, statuses, kpis, count, primitive, call }
        entry.verdict = rows > 0 && headersNamed && badged ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la tabla, sus cabeceras o el badge de estado de las filas no coincide con lo pintado'

        return entry
      }
    )

    await step(
      'filter',
      'Filtrar por estado activo, inactivo y todos, y buscar un término sin coincidencias',
      'Cada estado responde su población y un término sin coincidencias explica el vacío',
      async entry => {
        const total = await rowCount(page, ROWS)
        const countAll = await filterCount(page)
        const callsBefore = requests.filter(call => call.path === '/api/appointment-types').length

        await pickOption(page, page.locator('[data-status-filter] button'), 'Inactivos')
        await page.waitForTimeout(250)

        const inactive = await rowCount(page, ROWS)
        const inactiveBody = await listText(page)
        const inactiveEmpty = /No hay tipos de cita inactivos/.test(inactiveBody)

        await pickOption(page, page.locator('[data-status-filter] button'), 'Activos')
        await page.waitForTimeout(250)

        const active = await rowCount(page, ROWS)
        const activeStatuses = await visibleTexts(page, `${ROWS} td:nth-child(5)`)
        const allActive = activeStatuses.every(label => label === 'Activo')

        await pickOption(page, page.locator('[data-status-filter] button'), 'Todos')
        await page.waitForTimeout(250)

        const all = await rowCount(page, ROWS)

        await search(page, 'ZZZ-AUDIT-U9-SIN-COINCIDENCIAS')
        await page.waitForTimeout(250)

        const emptyBody = await listText(page)
        const emptyTitle = /Sin resultados/.test(emptyBody)
        const emptyNamesTerm = /ZZZ-AUDIT-U9-SIN-COINCIDENCIAS/.test(emptyBody)

        await search(page, '')
        await page.waitForTimeout(250)

        const restored = await rowCount(page, ROWS)
        const callsAfter = requests.filter(call => call.path === '/api/appointment-types').length

        entry.obtained = `inactivos: ${inactive} fila(s)${inactiveEmpty ? ' con «No hay tipos de cita inactivos»' : ''} · activos: ${active} filas, todas activas ${allActive} · todos: ${all} filas («${countAll ?? '—'}») · búsqueda sin coincidencias: ${emptyTitle ? '«Sin resultados»' : 'sin estado vacío'}${emptyNamesTerm ? ' nombrando el término' : ' sin nombrar el término'} · limpiar → ${restored} filas · peticiones de la lista ${callsBefore} → ${callsAfter}`
        entry.detail = {
          total,
          countAll,
          inactive,
          inactiveEmpty,
          active,
          activeStatuses: activeStatuses.slice(0, 6),
          allActive,
          all,
          emptyTitle,
          emptyNamesTerm,
          restored,
          callsBefore,
          callsAfter
        }
        entry.verdict =
          inactiveEmpty &&
          active > 0 &&
          allActive &&
          all === total &&
          emptyTitle &&
          emptyNamesTerm &&
          restored === total &&
          callsAfter === callsBefore
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el filtro de estado, el vacío de una búsqueda o su limpieza no responden la población declarada'

        return entry
      }
    )

    await step(
      'create-empty',
      'Abrir «Nuevo tipo» y enviar el formulario sin nombre y sin duración',
      'El formulario marca el nombre y la duración en su campo y no dispara ningún POST',
      async entry => {
        await page.locator('[data-hero-action]').click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        const painted = await dialog
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        const title = painted
          ? (
              (await dialog
                .locator('h2')
                .first()
                .innerText()
                .catch(() => '')) || ''
            ).trim()
          : null
        const defaults = {
          duration: await dialog
            .getByLabel('Duración (minutos)', { exact: false })
            .inputValue()
            .catch(() => null),
          color: await dialog
            .getByLabel('Código hex', { exact: false })
            .inputValue()
            .catch(() => null)
        }
        const noValidate = await dialog
          .locator('form#form-new-type')
          .evaluate(form => form.hasAttribute('novalidate'))
          .catch(() => null)

        const postsBefore = requests.filter(
          call => call.method === 'POST' && call.path === '/api/appointment-types'
        ).length

        const emptyAttempt = await respondTo(
          page,
          { method: 'POST', path: '/api/appointment-types' },
          () => dialog.locator(CREATE_SUBMIT).click({ timeout: 8000 }),
          2000
        )
        await page.waitForTimeout(250)

        const emptyErrors = await fieldErrors(page)

        // The create form starts with a valid duration (60), so the second
        // submit clears it to read the range rule the page owns.
        await fillField(page, 'Duración (minutos)', '')

        const invalidAttempt = await respondTo(
          page,
          { method: 'POST', path: '/api/appointment-types' },
          () => dialog.locator(CREATE_SUBMIT).click({ timeout: 8000 }),
          2000
        )
        await page.waitForTimeout(400)

        const errors = await fieldErrors(page)
        const postsAfter = requests.filter(
          call => call.method === 'POST' && call.path === '/api/appointment-types'
        ).length
        const namesName = errors.messages.some(message =>
          /El nombre es obligatorio\./.test(message)
        )
        const namesDuration = errors.messages.some(message =>
          /La duración debe estar entre 15 y 480 minutos\./.test(message)
        )

        entry.obtained = `diálogo «${title}» · valores por defecto duración «${defaults.duration ?? '—'}» y color «${defaults.color ?? '—'}» · formulario novalidate ${noValidate} · envío intacto: ${JSON.stringify(emptyErrors.messages)} · envío sin duración: ${JSON.stringify(errors.messages)} · campos inválidos ${JSON.stringify(errors.nativeInvalid)} · POST ${postsBefore} → ${postsAfter}${emptyAttempt.status || invalidAttempt.status ? ` (${emptyAttempt.status ?? invalidAttempt.status})` : ''}`
        entry.request = requestOf(invalidAttempt)
        entry.detail = {
          title,
          defaults,
          noValidate,
          emptyErrors,
          errors,
          emptyAttempt: { status: emptyAttempt.status, acted: emptyAttempt.acted },
          invalidAttempt: { status: invalidAttempt.status, acted: invalidAttempt.acted },
          postsBefore,
          postsAfter,
          dialogOpen: await dialog.isVisible().catch(() => false)
        }
        entry.verdict =
          painted && noValidate === true && postsAfter === postsBefore && namesName && namesDuration
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el envío sin nombre o sin duración no deja el mensaje en su campo, o dispara una escritura: la página debe validar en línea con `novalidate`'

        await closeDialog(page)

        return entry
      }
    )

    await step(
      'create',
      'Crear un tipo de cita con nombre, duración, color y una bandera',
      'El POST responde 201 con los valores del formulario, sin la bandera derivada, y la fila aparece en la lista',
      async entry => {
        await page.locator('[data-hero-action]').click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        const flagControls = await dialog
          .locator('[data-flag-select]')
          .evaluateAll(nodes => nodes.map(node => node.getAttribute('data-flag-select')))
        const materialsControls = flagControls.includes('requires_materials') ? 1 : 0

        await fillField(page, 'Nombre del Tipo', walkName)
        await fillField(page, 'Duración (minutos)', '45')
        await fillField(page, 'Código hex', walkColor)

        const flag = await pickOption(
          page,
          page.locator(`${DIALOG} [data-flag-select="requires_confirmation"] button`),
          'No'
        )

        const pendingCreate = page
          .waitForRequest(
            request => {
              if (request.method() !== 'POST') return false
              try {
                return new URL(request.url()).pathname === '/api/appointment-types'
              } catch {
                return false
              }
            },
            { timeout: 15000 }
          )
          .catch(() => null)

        const sent = await respondTo(page, { method: 'POST', path: '/api/appointment-types' }, () =>
          dialog.locator(CREATE_SUBMIT).click({ timeout: 8000 })
        )

        const createRequest = await pendingCreate
        let payload = null

        try {
          payload = createRequest ? createRequest.postDataJSON() : null
        } catch {
          payload = null
        }

        const payloadDeclaresMaterials = payload
          ? Object.prototype.hasOwnProperty.call(payload, 'requires_materials')
          : null

        await page.waitForTimeout(400)

        const toast = await toastText(page)
        const id = sent.body?.data?.id ?? null

        if (id) created.appointmentType = { id, name: walkName }

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})
        await search(page, walkName)
        await page.waitForTimeout(300)

        const rows = await rowCount(page, ROWS)
        const rowText = rows > 0 ? (await visibleTexts(page, ROWS))[0] : null
        const listed = Boolean(rowText?.includes(walkName))
        const data = sent.body?.data ?? null

        entry.obtained = `POST ${sent.status ?? 'sin petición'} · id ${id ?? '—'} · duración ${data?.default_duration_minutes ?? '—'} min · color ${data?.color ?? '—'} · requiere confirmación ${data?.requires_confirmation ?? '—'} (bandera «${flag.selected ?? `no elegida (${flag.reason})`}») · banderas del formulario ${JSON.stringify(flagControls)} · control derivado requires_materials ${materialsControls} · payload sin requires_materials ${payloadDeclaresMaterials === false} · toast «${toast}» · ${rows} fila ${listed ? 'con el nombre' : 'sin el nombre'}`
        entry.request = requestOf(sent)
        entry.detail = {
          id,
          toast,
          rows,
          rowText,
          flag,
          flagControls,
          materialsControls,
          payloadDeclaresMaterials,
          payloadKeys: payload ? Object.keys(payload) : null,
          data
        }
        entry.verdict =
          sent.status === 201 &&
          id !== null &&
          data?.default_duration_minutes === 45 &&
          data?.color === walkColor &&
          data?.requires_confirmation === false &&
          materialsControls === 0 &&
          payloadDeclaresMaterials === false &&
          listed
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el alta no guarda los valores del formulario, ofrece o envía la bandera derivada, o la fila no queda visible'

        return entry
      }
    )

    await step(
      'edit',
      'Editar el tipo recién creado y cambiar su duración',
      'El PUT responde 200 con la duración nueva y la fila la refleja',
      async entry => {
        const row = rowNamed(page, walkName)
        const visible = await row
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!visible) {
          entry.obtained = `la fila «${walkName}» no está visible para editarla`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'no se pudo abrir la fila que la caminata creó'

          return entry
        }

        await row.locator('[data-row-action="edit"]').first().click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        const prefilled = await dialog
          .getByLabel('Duración (minutos)', { exact: false })
          .inputValue()
          .catch(() => null)

        await fillField(page, 'Duración (minutos)', '75')

        const sent = await respondTo(
          page,
          { method: 'PUT', path: /^\/api\/appointment-types\/\d+$/ },
          () => dialog.locator(EDIT_SUBMIT).click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})
        await search(page, walkName)
        await page.waitForTimeout(300)

        const rowText = (await visibleTexts(page, ROWS))[0] ?? null
        const showsNew = Boolean(rowText?.includes('75 min'))

        entry.obtained = `duración precargada «${prefilled ?? '—'}» min · PUT ${sent.status ?? 'sin petición'} · duración devuelta ${sent.body?.data?.default_duration_minutes ?? '—'} min · fila «${rowText?.slice(0, 80) ?? '—'}» · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = { prefilled, toast, rowText, showsNew }
        entry.verdict =
          prefilled === '45' &&
          sent.status === 200 &&
          sent.body?.data?.default_duration_minutes === 75 &&
          showsNew
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la edición no precarga la duración guardada, no la persiste o la fila no la muestra'

        return entry
      }
    )

    await step(
      'deactivate',
      'Desactivar el tipo de cita desde el formulario de edición',
      'El estado «Inactivo» se guarda y la fila pinta su badge; la lista no ofrece la baja como acción de fila',
      async entry => {
        const row = rowNamed(page, walkName)
        const actions = await buttonLabels(row)

        await row.locator('[data-row-action="edit"]').first().click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        const status = await pickOption(
          page,
          page.locator(`${DIALOG} .select-container`).filter({ hasText: 'Activo' }).last(),
          'Inactivo'
        )

        const sent = await respondTo(
          page,
          { method: 'PUT', path: /^\/api\/appointment-types\/\d+$/ },
          () => dialog.locator(EDIT_SUBMIT).click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})
        await search(page, walkName)
        await page.waitForTimeout(300)

        const badge = (await visibleTexts(page, `${ROWS} td:nth-child(5)`))[0] ?? null
        const inactive = badge === 'Inactivo'

        entry.obtained = `acciones de la fila ${JSON.stringify(actions)} (sin acción de baja) · estado elegido «${status.selected ?? `no elegido (${status.reason})`}» · PUT ${sent.status ?? 'sin petición'} · is_active ${sent.body?.data?.is_active ?? '—'} · badge «${badge ?? '—'}» · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          actions,
          status,
          badge,
          inactive,
          isActive: sent.body?.data?.is_active ?? null,
          rowDeactivateAction: actions.some(label => /desactivar|inactivar/i.test(label))
        }
        entry.verdict =
          sent.status === 200 && inactive && !entry.detail.rowDeactivateAction ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la baja del tipo de cita no se completa desde el formulario, o la lista ofrece una acción de fila que la API no respalda'

        return entry
      }
    )

    await step(
      'detail',
      'Abrir «Ver Detalle» del tipo de la fixture, recorrer «Datos»/«Historial» y usar «Editar»',
      'La ficha muestra el tipo, las dos pestañas cargan y «Editar» abre el formulario de edición',
      async entry => {
        const fixture = fixtures.appointmentTypes.detail
        const auditPath = `/api/audit-logs/appointment-type/${fixture.id}`

        await search(page, fixture.name)

        await rowNamed(page, fixture.name)
          .locator('[data-row-action="detail"]')
          .first()
          .click({ timeout: 8000 })
          .catch(() => {})

        await page
          .waitForURL(url => url.pathname.endsWith(`/appointment-types/${fixture.id}`), {
            timeout: 12000
          })
          .catch(() => {})

        const hero = await page
          .locator('[data-detail-hero]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        await settle(page)

        const detailPath = new URL(page.url()).pathname
        const h1 = (await visibleTexts(page, '[data-detail-title]'))[0] ?? null
        const tabs = await visibleTexts(page, '[role="tab"]')
        const duration = (await visibleTexts(page, '[data-detail-field="duration"]'))[0] ?? null
        const status = (await visibleTexts(page, '[data-detail-status]'))[0] ?? null

        const audit = await respondTo(page, { method: 'GET', path: auditPath }, () =>
          page.getByRole('tab', { name: 'Historial' }).first().click({ timeout: 8000 })
        )

        await page.waitForTimeout(800)

        const auditRows = await rowCount(page, '[data-audit-row]')
        const empty = /No hay historial de auditoría/.test(await listText(page))
        const fromApi = await api(session, 'GET', auditPath)
        const entries = Array.isArray(fromApi.body?.data) ? fromApi.body.data.length : null
        const declared = entries === 0 ? empty : auditRows > 0

        await page.locator('[data-detail-action="edit"]').click({ timeout: 8000 })

        const editForm = page.locator('#form-edit-type')
        const editOpen = await editForm
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        const editPath = page.url()
        const prefilledName = editOpen
          ? await editForm
              .locator('input')
              .first()
              .inputValue()
              .catch(() => null)
          : null

        entry.obtained = `URL ${detailPath} · h1 «${h1 ?? '—'}» · pestañas ${JSON.stringify(tabs)} · duración ${duration ?? '—'} · estado ${status ?? '—'} · «Historial» → GET ${auditPath} ${audit.status ?? 'sin petición'} · ${auditRows} entradas pintadas (${entries ?? '—'} en la API) · «Editar» → ${new URL(editPath).pathname}${new URL(editPath).search} con el formulario ${editOpen ? 'abierto' : 'cerrado'} y el nombre «${prefilledName ?? '—'}»`
        entry.request = requestOf(audit)
        entry.detail = {
          detailPath,
          hero,
          h1,
          tabs,
          duration,
          status,
          auditRows,
          entries,
          empty,
          declared,
          editPath,
          editOpen,
          prefilledName
        }
        entry.verdict =
          detailPath === `/appointment-types/${fixture.id}` &&
          Boolean(h1?.includes(fixture.name)) &&
          tabs.join('|') === 'Datos|Historial' &&
          audit.status === 200 &&
          declared &&
          editOpen &&
          prefilledName === fixture.name
            ? 'OK'
            : 'DEFECTO'
        entry.detail.cause =
          'AppointmentTypesPage.openEditFromQuery abre el modal y a continuación hace router.replace({edit: undefined}); el <router-view> de app.js está keyed por $route.fullPath, así que el cambio de query remonta la página y descarta el modal recién abierto'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la ficha no muestra el tipo, sus dos pestañas o el historial; «Editar» navega a /appointment-types?edit=<id> y el formulario de edición no queda abierto (la query se limpia y el router-view keyed por fullPath remonta la página)'

        await closeDialog(page)

        return entry
      }
    )

    await step(
      'delete',
      'Eliminar el tipo de cita que esta caminata creó',
      'El DELETE responde 200 y la fila desaparece de la lista',
      async entry => {
        await search(page, walkName)
        await page.waitForTimeout(200)

        const row = rowNamed(page, walkName)
        const visible = await row
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!visible) {
          entry.obtained = `la fila «${walkName}» no está visible para eliminarla`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'no se pudo abrir la fila que la caminata creó'

          return entry
        }

        await row.locator('[data-row-action="delete"]').first().click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const sent = await respondTo(
          page,
          { method: 'DELETE', path: /^\/api\/appointment-types\/\d+$/ },
          () => confirmDialog(page, 'Eliminar')
        )

        await page.waitForTimeout(500)

        const toast = await toastText(page)

        if (sent.status === 200) created.deleted = true

        await page
          .waitForFunction(selector => document.querySelectorAll(selector).length === 0, ROWS, {
            timeout: 8000,
            polling: 120
          })
          .catch(() => {})

        const rows = await rowCount(page, ROWS)
        const emptyShown = /Sin resultados/.test(await listText(page))

        entry.obtained = `confirmación «${confirmText}» · DELETE ${sent.status ?? 'sin petición'} · ${rows} fila(s) · estado vacío del filtro ${emptyShown} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = { confirmText, toast, rows, emptyShown }
        entry.verdict = sent.status === 200 && rows === 0 ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el borrado de un tipo sin citas no responde 200 o la fila permanece en la lista'

        return entry
      }
    )

    await step(
      'back',
      'Abrir el detalle del tipo de la fixture y volver con el botón del navegador',
      'La lista reaparece en /appointment-types con la búsqueda limpia',
      async entry => {
        const fixture = fixtures.appointmentTypes.detail

        await search(page, fixture.name)
        await page.waitForTimeout(200)

        await rowNamed(page, fixture.name)
          .locator('[data-row-action="detail"]')
          .first()
          .click({ timeout: 8000 })
          .catch(() => {})

        await page
          .waitForURL(url => url.pathname.endsWith(`/appointment-types/${fixture.id}`), {
            timeout: 12000
          })
          .catch(() => {})
        await settle(page)

        const detailPath = new URL(page.url()).pathname

        await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => {})
        await settle(page)
        await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 15000 })
          .catch(() => {})

        const path = new URL(page.url()).pathname
        const searchValue = await page
          .locator(SEARCH)
          .inputValue()
          .catch(() => null)
        const rows = await rowCount(page, ROWS)
        const count = await filterCount(page)

        entry.obtained = `detalle ${detailPath} · volver → URL ${path} · búsqueda «${searchValue ?? ''}» · ${rows} filas · «${count ?? '—'}»`
        entry.detail = { detailPath, path, searchValue, rows, count }
        entry.verdict = path === SURFACE && searchValue === '' && rows > 0 ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el retorno del navegador no deja la lista con su búsqueda vacía'

        return entry
      }
    )

    await step(
      'states',
      'Medir el estado de carga, el vacío de un filtro y el error con reintento',
      'La lista muestra su placeholder de carga, un vacío que explica el filtro y un error con reintento que recarga',
      async entry => {
        const detail = { loading: null, empty: null, error: null }

        await page.route('**/api/appointment-types*', async route => {
          await new Promise(resolve => setTimeout(resolve, 1500))
          await route.continue().catch(() => {})
        })

        await page.goto(SURFACE, { waitUntil: 'domcontentloaded' })

        const loading = await page
          .waitForFunction(
            () => /Cargando tipos de cita/i.test(document.querySelector('main')?.innerText ?? ''),
            undefined,
            { timeout: 6000, polling: 100 }
          )
          .then(() => true)
          .catch(() => false)

        detail.loading = {
          visible: loading,
          shot: loading ? await shot(page, `shots/walk/${SLUG}/${entry.id}-loading.png`) : null
        }

        await page.unroute('**/api/appointment-types*')
        await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 15000 })
          .catch(() => {})

        await search(page, 'ZZZ-AUDIT-U9-SIN-RESULTADOS')
        await page.waitForTimeout(300)

        const emptyBody = await listText(page)

        detail.empty = {
          rows: await rowCount(page, ROWS),
          title: /Sin resultados/.test(emptyBody),
          description: /Ningún tipo de cita coincide/.test(emptyBody),
          namesTerm: /ZZZ-AUDIT-U9-SIN-RESULTADOS/.test(emptyBody)
        }

        await search(page, '')
        await page.waitForTimeout(200)

        await page.route('**/api/appointment-types*', route =>
          route
            .fulfill({
              status: 500,
              contentType: 'application/json',
              body: JSON.stringify({ message: 'AUDIT-U9 error provocado' })
            })
            .catch(() => {})
        )

        await page.reload({ waitUntil: 'domcontentloaded' })

        const errorPainted = await page
          .locator('[data-types-error]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const errorText = (await visibleTexts(page, '[data-types-error]')).join(' | ')
        const kpisInError = await visibleTexts(page, '[data-kpi-value]')
        const retry = page.locator('[data-retry-types]').first()
        const retryOffered = (await retry.count()) > 0

        detail.error = {
          painted: errorPainted,
          text: errorText,
          kpis: kpisInError,
          retryOffered,
          shot: await shot(page, `shots/walk/${SLUG}/${entry.id}-error.png`)
        }

        await page.unroute('**/api/appointment-types*')

        if (retryOffered) {
          await respondTo(
            page,
            { method: 'GET', path: '/api/appointment-types', query: { status: 'all' } },
            () => retry.click({ timeout: 8000 })
          )
          await page.waitForTimeout(500)
        }

        const rowsAfterRetry = await rowCount(page, ROWS)

        detail.error.rowsAfterRetry = rowsAfterRetry

        entry.obtained = `carga: ${detail.loading.visible ? 'muestra «Cargando tipos de cita...»' : 'sin placeholder observable'} · vacío: ${detail.empty.title && detail.empty.description ? '«Sin resultados» nombrando el término' : 'sin texto de vacío'} · error 500: ${detail.error.painted ? `bloque «${detail.error.text.slice(0, 80)}»` : 'sin bloque de error'} con KPIs ${JSON.stringify(detail.error.kpis)} · «Reintentar» ${detail.error.retryOffered ? 'ofrecido' : 'ausente'} · tras reintentar ${rowsAfterRetry} fila(s)`
        entry.detail = detail
        entry.verdict =
          detail.loading.visible &&
          detail.empty.title &&
          detail.empty.description &&
          detail.empty.namesTerm &&
          detail.error.painted &&
          detail.error.retryOffered &&
          rowsAfterRetry > 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la lista no cubre carga, vacío y error con reintento, o el reintento no recarga las filas'

        return entry
      }
    )
    await step(
      'flags',
      'Leer la declaración derivada de «Requiere materiales» y guardar el tipo de la fixture',
      'La edición no ofrece el control, declara que el valor se deriva de la receta, el PUT no envía la bandera y la API y la ficha publican el valor derivado',
      async entry => {
        const fixture = fixtures.appointmentTypes.detail

        await search(page, fixture.name)
        await page.waitForTimeout(200)

        const row = rowNamed(page, fixture.name)
        const visible = await row
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!visible) {
          entry.obtained = `la fila «${fixture.name}» no está visible para editarla`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'no se pudo abrir el tipo de la fixture'

          return entry
        }

        await row.locator('[data-row-action="edit"]').first().click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        const derivedValue =
          (await visibleTexts(page, `${DIALOG} [data-derived-field="requires_materials"]`))[0] ??
          null
        const dialogText = (await dialog.innerText().catch(() => '')).trim().replace(/\s+/g, ' ')
        const derivedCaption = /Derivado de la receta del procedimiento por defecto\./.test(
          dialogText
        )
        const flagControls = await dialog
          .locator('[data-flag-select]')
          .evaluateAll(nodes => nodes.map(node => node.getAttribute('data-flag-select')))
        const materialsControls = flagControls.includes('requires_materials') ? 1 : 0

        const pendingWrite = page
          .waitForRequest(
            request => {
              if (request.method() !== 'PUT') return false
              try {
                return /^\/api\/appointment-types\/\d+$/.test(new URL(request.url()).pathname)
              } catch {
                return false
              }
            },
            { timeout: 15000 }
          )
          .catch(() => null)

        const sent = await respondTo(
          page,
          { method: 'PUT', path: /^\/api\/appointment-types\/\d+$/ },
          () => dialog.locator(EDIT_SUBMIT).click({ timeout: 8000 })
        )
        const writeRequest = await pendingWrite
        let payload = null

        try {
          payload = writeRequest ? writeRequest.postDataJSON() : null
        } catch {
          payload = null
        }

        const payloadDeclaresMaterials = payload
          ? Object.prototype.hasOwnProperty.call(payload, 'requires_materials')
          : null

        await page.waitForTimeout(400)

        const toast = await toastText(page)
        const published = sent.body?.data?.requires_materials ?? null

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})

        const opened = await row
          .locator('[data-row-action="detail"]')
          .first()
          .click({ timeout: 8000 })
          .then(() => true)
          .catch(() => false)

        await page
          .waitForURL(url => url.pathname.endsWith(`/appointment-types/${fixture.id}`), {
            timeout: 12000
          })
          .catch(() => {})
        await settle(page)

        const detailValue =
          (await visibleTexts(page, '[data-detail-field="requires-materials"]'))[0] ?? null
        const detailDeclared =
          (await visibleTexts(page, '[data-detail-derived="requires-materials"]'))[0] ?? null
        const derivedHonoured =
          Boolean(derivedValue?.includes('Requiere materiales')) &&
          derivedCaption &&
          materialsControls === 0 &&
          sent.status === 200 &&
          payloadDeclaresMaterials === false &&
          published === false &&
          detailValue === 'No' &&
          /Derivado de la receta del procedimiento por defecto\./.test(detailDeclared ?? '')

        entry.obtained = `control «Requiere materiales» en la edición ${materialsControls} (banderas del formulario ${JSON.stringify(flagControls)}) · declaración «${derivedValue ?? '—'}» con la nota derivada ${derivedCaption} · PUT ${sent.status ?? 'sin petición'} · payload sin requires_materials ${payloadDeclaresMaterials === false} (${JSON.stringify(Object.keys(payload ?? {}))}) · requiere materiales publicado ${published ?? '—'} · la ficha pinta «${detailValue ?? '—'}» y declara «${detailDeclared ?? '—'}» · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          derivedValue,
          derivedCaption,
          materialsControls,
          flagControls,
          payloadDeclaresMaterials,
          payloadKeys: payload ? Object.keys(payload) : null,
          opened,
          published,
          detailValue,
          detailDeclared,
          derivedHonoured,
          source:
            'AppointmentType::toResponseArray publica requires_materials derivado del recetario del procedimiento (#295, #444)'
        }
        entry.verdict = derivedHonoured ? 'OK' : 'DEFECTO'
        entry.notes = derivedHonoured
          ? ''
          : 'la edición ofrece el control derivado, el PUT envía la bandera, o la API y la ficha no publican el valor derivado del recetario del procedimiento'

        await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => {})
        await settle(page)

        return entry
      }
    )
  } finally {
    if (created.appointmentType && !created.deleted) {
      const removed = await api(
        session,
        'DELETE',
        `/api/appointment-types/${created.appointmentType.id}`
      )

      evidence.cleanup.push({
        type: 'appointment-type',
        id: created.appointmentType.id,
        status: removed.status,
        message: removed.body?.message ?? null
      })
    }

    await dismissToasts(page).catch(() => {})
    await closeDialog(page).catch(() => {})

    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.consoleClassified = classifyConsole(
      diagnostics.consoleErrors,
      'admin',
      DECLARED_CONSOLE
    )
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')

    markExpected(
      evidence.failedRequests,
      failure => failure.status === 500 && /\/api\/appointment-types/.test(failure.url ?? ''),
      'el paso `states` provoca un 500 para medir el estado de error de la lista'
    )

    evidence.fixtures = { ...evidence.fixtures, ...fixtureSummary(fixtures) }
    evidence.counts = walkCounts(evidence.steps)

    audit('walk-appointment-types', evidence)
  }

  expect(evidence.pageErrors, 'page errors during the appointment types walkthrough').toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    'declared steps of the appointment types walkthrough'
  ).toEqual([
    '01-list',
    '02-filter',
    '03-create-empty',
    '04-create',
    '05-edit',
    '06-deactivate',
    '07-detail',
    '08-delete',
    '09-back',
    '10-states',
    '11-flags'
  ])
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    'every step of the appointment types walkthrough has its capture'
  ).toBe(true)
})
