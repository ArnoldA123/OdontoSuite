// Plan #13 (issue #372, U9) — layer 4: the environments ABM, action by action.
//
// `/environments` is the only one of the three lists that rides the shared
// primitive (`UiDataTable`) and the only one whose delete door surfaces the 409
// the API answers: the walkthrough measures both, and searches by name so the
// filter reaches the server (`/api/dental-chairs/search`) instead of filtering a
// copy in the browser.
//
// The one destructive probe presses «Eliminar» on a seeded chair that holds
// appointments; the API refuses it with the reason and the page prints that
// reason. The chair this walk creates is archived, reactivated and finally
// deleted, so nothing of the session survives it.
//
// `DEFECTO` / `NO-OP` / `CONFUSO` are evidence, never a thrown failure: the only
// hard assertions are that no `pageerror` was raised and that every declared step
// was recorded.

import { test, expect } from 'playwright/test'

import { classifyFailures, collectDiagnostics, primeSession } from '../harness.js'

import { ensureFixtures, fixtureSummary } from './fixtures.js'

import {
  DESKTOP,
  SESSION,
  api,
  audit,
  classifyConsole,
  clickByLabel,
  closeDialog,
  confirmDialog,
  dismissToasts,
  fieldErrors,
  fillField,
  gotoSurface,
  historyIds,
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

const SURFACE = '/environments'
const SLUG = 'environments'
const MARKER = 'AUDIT-U9'
const ROWS = 'main .data-table-row'
const SEARCH = 'main input[placeholder^="Buscar ambientes"]'
const DIALOG = '[role="dialog"]:visible'

const DECLARED_CONSOLE = [
  {
    pattern: /Failed to load resource: .*409/,
    reason:
      'la caminata pulsa «Eliminar» sobre un sillón sembrado con citas: el 409 es el contrato que se mide'
  },
  {
    pattern: /Failed to load resource: .*422/,
    reason: 'el envío vacío del alta es un 422 que la propia caminata provoca'
  },
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

const isChairCall = url => {
  try {
    const path = new URL(url).pathname
    return path === '/api/dental-chairs' || path === '/api/dental-chairs/search'
  } catch {
    return false
  }
}

/** Runs an action and waits for the list request it must trigger. */
const awaitList = async (page, action, timeout = 12000) => {
  const pending = page
    .waitForResponse(
      response => response.request().method() === 'GET' && isChairCall(response.url()),
      { timeout }
    )
    .catch(() => null)

  await Promise.resolve(action()).catch(() => {})

  const response = await pending
  await settle(page)

  return response?.status() ?? null
}

const search = async (page, value) => {
  const status = await awaitList(page, () => fillField(page, 'Buscar ambientes por nombre', value))
  await page.waitForTimeout(200)

  return status
}

test('@U9 walks ambientes', async ({ page }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const session = await login('administrador')
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const created = { chair: null, deleted: false }

  const walkName = `${MARKER}-${fixtures.token} consultorio de la caminata`

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks ambientes',
    role: 'administrador',
    steps: [],
    counts: null,
    fixtures: { marker: fixtures.marker, ok: fixtures.ok, token: fixtures.token },
    created: { name: walkName, seededName: null },
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
      'Abrir /environments como administración',
      'La lista pinta sus filas, sus cabeceras y el estado de cada ambiente',
      async entry => {
        const painted = await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        const rows = await rowCount(page, ROWS)
        const headers = await tableHeaders(page)
        const statuses = await visibleTexts(page, `${ROWS} td:nth-child(4)`)
        const primitive = await tablePrimitive(page)
        const statusTrigger = (
          (await visibleTexts(page, '[data-status-filter] button'))[0] ?? null
        )?.trim()
        const archivedTrigger = (
          (await visibleTexts(page, '[data-archived-filter] button'))[0] ?? null
        )?.trim()
        const call = lastCall(requests, 'GET', '/api/dental-chairs')
        const badged = statuses.length === rows && statuses.every(label => label.length > 0)
        const headersNamed = headers.includes('NOMBRE') && headers.includes('ESTADO')

        entry.obtained = `${rows} filas · cabeceras ${headers.length} ${JSON.stringify(headers)} · estados ${JSON.stringify([...new Set(statuses)])} · filtros «${statusTrigger ?? '—'}» / «${archivedTrigger ?? '—'}» · lista: ${primitive.primitive} (data-table-scroll ${primitive.dataTables}, affordance de scroll ${primitive.scrollAffordance}${primitive.scrollHint ? `: «${primitive.scrollHint}»` : ''})`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = {
          painted,
          rows,
          headers,
          statuses,
          primitive,
          statusTrigger,
          archivedTrigger
        }
        entry.verdict = rows > 0 && headersNamed && badged ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la tabla, sus cabeceras o el estado de las filas no coincide con lo que la página pinta'

        return entry
      }
    )

    await step(
      'filter',
      'Buscar el ambiente de la fixture por su nombre y limpiar el filtro',
      'La búsqueda llega al servidor, deja exactamente su fila y limpiarla devuelve el total',
      async entry => {
        const fixture = fixtures.chairs.detail
        const before = await rowCount(page, ROWS)

        const searchStatus = await search(page, fixture.name)

        const filtered = await rowCount(page, ROWS)
        const filteredTexts = await visibleTexts(page, ROWS)
        const searchCall = lastCall(requests, 'GET', '/api/dental-chairs/search')

        const clearStatus = await search(page, '')

        const after = await rowCount(page, ROWS)
        const searchValue = await page
          .locator(SEARCH)
          .inputValue()
          .catch(() => null)
        const listCall = lastCall(requests, 'GET', '/api/dental-chairs')
        const hitsFixture = Boolean(filteredTexts[0]?.includes(fixture.name))

        entry.obtained = `«${fixture.name}» → ${filtered} fila (${hitsFixture ? 'la fila buscada' : 'otra fila'}) · GET /api/dental-chairs/search?q=${searchCall?.query?.q ?? '—'} ${searchStatus ?? 'sin respuesta'} · limpiar → ${after} filas, búsqueda «${searchValue ?? ''}» ${clearStatus ?? ''}`
        entry.request = searchCall
          ? { method: searchCall.method, path: searchCall.path, status: searchCall.status }
          : null
        entry.detail = {
          before,
          filtered,
          after,
          searchCall,
          listCall,
          searchValue,
          searchStatus,
          clearStatus
        }
        entry.verdict =
          filtered === 1 &&
          hitsFixture &&
          searchCall?.query?.q === fixture.name &&
          after === before &&
          searchValue === ''
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la búsqueda no viaja al servidor con su término o no deja exactamente la fila buscada'

        return entry
      }
    )

    await step(
      'create-empty',
      'Abrir «Nuevo ambiente» y pulsar «Crear ambiente» sin nombre',
      'El envío vacío se rechaza con el motivo escrito en el campo del nombre; se registra si además queda un aviso genérico',
      async entry => {
        await clickByLabel(page, 'Nuevo ambiente')

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

        const sent = await respondTo(page, { method: 'POST', path: '/api/dental-chairs' }, () =>
          page.locator('[data-modal-submit="create"]').click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)
        const errors = await fieldErrors(page)
        const apiMessage = sent.body?.message ?? null
        const apiFields = sent.body?.errors ? Object.keys(sent.body.errors) : []
        const apiFieldMessage = sent.body?.errors?.name?.[0] ?? null
        const nameField = dialog.locator('[data-field="name"]')
        const nameFieldText = ((await nameField.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')
        // The input label also carries `text-error-500` when the field is
        // invalid (Input.vue marks the label with the error class), so the
        // reader has to skip it and take the paragraph that holds the message.
        const inlineMessage =
          (
            (await nameField
              .locator('p.text-error-500, [data-error]')
              .first()
              .innerText()
              .catch(() => '')) || ''
          )
            .trim()
            .replace(/\s+/g, ' ') || null
        const inlineNamesField = Boolean(
          inlineMessage &&
          /nombre|name/i.test(nameFieldText) &&
          /obligator|requerid/i.test(inlineMessage)
        )
        const inlineCarriesServerMessage = Boolean(
          inlineMessage &&
          (!apiFieldMessage ||
            inlineMessage.includes(apiFieldMessage) ||
            apiFieldMessage.includes(inlineMessage))
        )
        const toastFired = Boolean(toast)
        const dialogOpen = await dialog.isVisible().catch(() => false)

        entry.obtained = `diálogo «${title}» · POST ${sent.status ?? 'sin petición'} · mensaje de la API «${apiMessage ?? '—'}» con campos ${JSON.stringify(apiFields)} y motivo del campo «${apiFieldMessage ?? '—'}» · mensaje en el campo del nombre «${inlineMessage ?? 'ninguno'}» · mensajes de campo ${JSON.stringify(errors.messages)} · toast ${toastFired ? `«${toast}»` : 'sin aviso genérico'}`
        entry.request = requestOf(sent)
        entry.detail = {
          title,
          toast,
          toastFired,
          errors,
          apiMessage,
          apiFields,
          apiFieldMessage,
          nameFieldText,
          inlineMessage,
          inlineNamesField,
          inlineCarriesServerMessage,
          dialogOpen
        }
        entry.verdict =
          sent.status === 422 && inlineNamesField && inlineCarriesServerMessage && dialogOpen
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la API rechaza el alta vacía con el campo que falta, pero el motivo no se escribe en el campo del nombre del formulario'

        return entry
      }
    )

    await step(
      'create',
      'Crear un ambiente con nombre y estado',
      'El POST responde 201, el toast confirma y la fila aparece en la lista',
      async entry => {
        await fillField(page, 'Nombre del ambiente', walkName)

        const sent = await respondTo(page, { method: 'POST', path: '/api/dental-chairs' }, () =>
          page.locator('[data-modal-submit="create"]').click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)
        const id = sent.body?.data?.id ?? null

        if (id) created.chair = { id, name: walkName }

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})
        await search(page, walkName)
        await page.waitForTimeout(300)

        const rows = await rowCount(page, ROWS)
        const rowText = rows > 0 ? (await visibleTexts(page, ROWS))[0] : null
        const listed = Boolean(rowText?.includes(walkName))
        const badge = (await visibleTexts(page, `${ROWS} td:nth-child(4)`))[0] ?? null

        entry.obtained = `POST ${sent.status ?? 'sin petición'} · id ${id ?? '—'} · estado «${badge ?? '—'}» · toast «${toast}» · ${rows} fila ${listed ? 'con el nombre' : 'sin el nombre'}`
        entry.request = requestOf(sent)
        entry.detail = { id, toast, rows, rowText, badge, status: sent.body?.data?.status ?? null }
        entry.verdict =
          sent.status === 201 && id !== null && rows === 1 && listed ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el alta no responde 201 o la fila no queda visible con su nombre'

        return entry
      }
    )

    await step(
      'edit',
      'Editar el ambiente recién creado y cambiar su estado a «Mantenimiento»',
      'El PUT responde 200 y la fila muestra el estado nuevo',
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

        await row.locator('button').filter({ hasText: 'Editar' }).first().click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        const status = await pickOption(
          page,
          page.locator(`${DIALOG} [data-environment-form="edit"] [data-field="status"] button`),
          'Mantenimiento'
        )

        const sent = await respondTo(
          page,
          { method: 'PUT', path: /^\/api\/dental-chairs\/\d+$/ },
          () => page.locator('[data-modal-submit="edit"]').click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})
        await search(page, walkName)
        await page.waitForTimeout(300)

        const badge = (await visibleTexts(page, `${ROWS} td:nth-child(4)`))[0] ?? null
        const maintenance = badge === 'Mantenimiento'

        entry.obtained = `estado elegido «${status.selected ?? `no elegido (${status.reason})`}» · PUT ${sent.status ?? 'sin petición'} · estado de la fila «${badge ?? '—'}» · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          status,
          badge,
          toast,
          maintenance,
          apiStatus: sent.body?.data?.status ?? null
        }
        entry.verdict = sent.status === 200 && maintenance ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK' ? '' : 'el estado nuevo no se guarda o la fila no lo refleja'

        return entry
      }
    )

    await step(
      'archive',
      'Archivar el ambiente de la caminata desde la confirmación de su fila',
      'La confirmación ejecuta el PUT is_active=false y la fila deja la lista activa',
      async entry => {
        const row = rowNamed(page, walkName)
        const visible = await row
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!visible) {
          entry.obtained = `la fila «${walkName}» no está visible para archivarla`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'no se pudo abrir la fila que la caminata creó'

          return entry
        }

        await row.locator('button').filter({ hasText: 'Archivar' }).first().click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const sent = await respondTo(
          page,
          { method: 'PUT', path: /^\/api\/dental-chairs\/\d+$/ },
          () => confirmDialog(page, 'Archivar')
        )

        await page.waitForTimeout(500)

        const toast = await toastText(page)

        await page
          .waitForFunction(selector => document.querySelectorAll(selector).length === 0, ROWS, {
            timeout: 8000,
            polling: 120
          })
          .catch(() => {})

        const rows = await rowCount(page, ROWS)
        const body = await listText(page)
        const emptyShown = /No se encontraron ambientes/.test(body)

        entry.obtained = `confirmación «${confirmText}» · PUT ${sent.status ?? 'sin petición'} · is_active ${sent.body?.data?.is_active ?? '—'} · ${rows} fila(s) · estado vacío del filtro ${emptyShown} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          confirmText,
          toast,
          rows,
          emptyShown,
          isActive: sent.body?.data?.is_active ?? null
        }
        entry.verdict = sent.status === 200 && rows === 0 ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'archivar no retira la fila de la lista activa o la API no responde 200'

        return entry
      }
    )

    await step(
      'reactivate',
      'Reactivar el ambiente archivado desde el filtro «Solo archivados»',
      'El archivado aparece con su badge, «Reactivar» responde 200 y sale de esa lista',
      async entry => {
        await awaitList(page, () =>
          pickOption(page, page.locator('[data-archived-filter] button'), 'Solo archivados')
        )
        await page.waitForTimeout(300)

        const archivedBadge = (await visibleTexts(page, ROWS)).find(text => text.includes(walkName))
        const row = rowNamed(page, walkName)
        const visible = await row
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!visible) {
          entry.obtained = `el ambiente archivado «${walkName}» no aparece con el filtro «Solo archivados»`
          entry.verdict = 'DEFECTO'
          entry.notes =
            'el archivo no es reversible desde la superficie: la fila no reaparece en su filtro'

          return entry
        }

        const actions = await buttonLabels(row)

        const sent = await respondTo(
          page,
          { method: 'PUT', path: /^\/api\/dental-chairs\/\d+$/ },
          () =>
            row.locator('button').filter({ hasText: 'Reactivar' }).first().click({ timeout: 8000 })
        )

        await page.waitForTimeout(600)

        const toast = await toastText(page)
        const rows = await rowCount(page, ROWS)

        await awaitList(page, () =>
          pickOption(page, page.locator('[data-archived-filter] button'), 'Sin archivados')
        )
        await search(page, '')

        entry.obtained = `filtro «Solo archivados» → fila con «Archivado» ${Boolean(archivedBadge)} · acciones ${JSON.stringify(actions)} · PUT ${sent.status ?? 'sin petición'} · is_active ${sent.body?.data?.is_active ?? '—'} · filas en el filtro tras reactivar ${rows} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          archivedBadge: archivedBadge ?? null,
          actions,
          toast,
          rows,
          isActive: sent.body?.data?.is_active ?? null
        }
        entry.verdict =
          visible && sent.status === 200 && rows === 0 && actions.includes('Reactivar')
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'reactivar no devuelve el ambiente a la operación o no lo retira de la lista de archivados'

        return entry
      }
    )

    await step(
      'delete-guard',
      'Pulsar «Eliminar» sobre un sillón sembrado con citas y confirmar',
      'La API rechaza el borrado (409) y la superficie le explica el motivo al usuario',
      async entry => {
        const fixture = fixtures.chairs.detail

        await search(page, fixture.name)
        const fixtureActions = await buttonLabels(rowNamed(page, fixture.name))

        await search(page, walkName)
        const ownActions = await buttonLabels(rowNamed(page, walkName))

        const chairs = await api(session, 'GET', '/api/dental-chairs')
        const history = await historyIds(session)
        const withHistory = new Set(history.chairs)
        const seeded = (chairs.body?.data ?? []).find(
          chair => withHistory.has(chair.id) && !chair.name.startsWith(MARKER) && chair.is_active
        )

        if (!seeded) {
          entry.obtained = `${chairs.body?.data?.length ?? 0} ambientes activos · ${withHistory.size} con citas · ninguno sembrado y activo`
          entry.verdict = 'NO PROBADO'
          entry.notes =
            'la base de auditoría no tiene un sillón sembrado con citas y activo: no hay fila que la API deba proteger'
          evidence.created.seededName = null

          return entry
        }

        evidence.created.seededName = seeded.name

        await search(page, seeded.name)
        const row = rowNamed(page, seeded.name)
        const seededActions = await buttonLabels(row)

        await row.locator('button').filter({ hasText: 'Eliminar' }).first().click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const sent = await respondTo(
          page,
          { method: 'DELETE', path: /^\/api\/dental-chairs\/\d+$/ },
          () => confirmDialog(page, 'Eliminar')
        )

        await page.waitForTimeout(500)

        const toast = await toastText(page)
        const rows = await rowCount(page, ROWS)
        const apiMessage = sent.body?.message ?? null
        const told = Boolean(toast) && Boolean(apiMessage) && toast.includes(apiMessage)

        entry.obtained = `sillón sembrado «${seeded.name}» (id ${seeded.id}) · acciones de la fila ${JSON.stringify(seededActions)} · confirmación «${confirmText}» · DELETE ${sent.status ?? 'sin petición'} · mensaje de la API «${apiMessage ?? '—'}» · toast «${toast}» · la fila sigue en la lista ${rows > 0}`
        entry.request = requestOf(sent)
        entry.detail = {
          seeded: { id: seeded.id, name: seeded.name, status: seeded.status },
          seededActions,
          fixtureActions,
          ownActions,
          confirmText,
          apiMessage,
          toast,
          rows,
          historyStatus: history.status,
          deleteDoorOfferedOnEveryRow: [seededActions, fixtureActions, ownActions].every(labels =>
            labels.includes('Eliminar')
          )
        }
        entry.verdict = sent.status === 409 && rows > 0 ? (told ? 'OK' : 'DEFECTO') : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la superficie muestra una puerta que la API rechaza sin explicar el motivo, o pierde la fila'

        return entry
      }
    )

    await step(
      'delete',
      'Eliminar el ambiente que esta caminata creó',
      'El DELETE responde 200 y la fila desaparece de la lista',
      async entry => {
        await search(page, walkName)

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

        await row.locator('button').filter({ hasText: 'Eliminar' }).first().click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const sent = await respondTo(
          page,
          { method: 'DELETE', path: /^\/api\/dental-chairs\/\d+$/ },
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
        const emptyShown = /No se encontraron ambientes/.test(await listText(page))

        entry.obtained = `confirmación «${confirmText}» · DELETE ${sent.status ?? 'sin petición'} · ${rows} fila(s) · estado vacío del filtro ${emptyShown} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = { confirmText, toast, rows, emptyShown }
        entry.verdict = sent.status === 200 && rows === 0 ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el borrado de un ambiente sin citas no responde 200 o la fila permanece'

        return entry
      }
    )

    await step(
      'detail',
      'Abrir «Ver detalle» del ambiente de la fixture y recorrer sus dos pestañas',
      'La ficha muestra el ambiente y la pestaña «Auditoría» carga su historial',
      async entry => {
        const fixture = fixtures.chairs.detail
        const auditPath = `/api/audit-logs/dental-chair/${fixture.id}`

        await search(page, fixture.name)

        await rowNamed(page, fixture.name)
          .locator('button')
          .filter({ hasText: 'Ver detalle' })
          .first()
          .click({ timeout: 8000 })
          .catch(() => {})

        await page
          .waitForURL(url => url.pathname.endsWith(`/environments/${fixture.id}`), {
            timeout: 12000
          })
          .catch(() => {})

        const summary = await page
          .locator('[data-environment-summary]')
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        await settle(page)

        const path = new URL(page.url()).pathname
        const h2 = (await visibleTexts(page, '[data-environment-summary] h2'))[0] ?? null
        const tabs = await visibleTexts(page, '[role="tab"]')

        const audit = await respondTo(page, { method: 'GET', path: auditPath }, () =>
          page.getByRole('tab', { name: 'Auditoría' }).first().click({ timeout: 8000 })
        )

        await page.waitForTimeout(800)

        const rows = await rowCount(page, '[data-environment-audit] ul li')
        const empty = (await page.locator('[data-environment-audit-empty]').count()) > 0
        const fromApi = await api(session, 'GET', auditPath)
        const entries = Array.isArray(fromApi.body?.data) ? fromApi.body.data.length : null
        const declared = entries === 0 ? empty : rows > 0

        entry.obtained = `URL ${path} · h2 «${h2 ?? '—'}» · pestañas ${JSON.stringify(tabs)} · «Auditoría» → GET ${auditPath} ${audit.status ?? 'sin petición'} · ${rows} entradas pintadas · ${entries ?? '—'} en la API · ${empty ? 'estado vacío declarado' : 'lista de entradas'}`
        entry.request = requestOf(audit)
        entry.detail = { path, summary, h2, tabs, rows, empty, entries, declared }
        entry.verdict =
          path === `/environments/${fixture.id}` &&
          Boolean(h2?.includes(fixture.name)) &&
          tabs.join('|') === 'Información|Auditoría' &&
          audit.status === 200 &&
          declared
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la ficha no muestra el ambiente, sus dos pestañas o el historial que publica la API'

        return entry
      }
    )

    await step(
      'back',
      'Volver a la lista con el botón del navegador',
      'La lista reaparece en /environments con la búsqueda limpia',
      async entry => {
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

        entry.obtained = `URL ${path} · búsqueda «${searchValue ?? ''}» · ${rows} filas`
        entry.detail = { path, searchValue, rows }
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

        await page.route('**/api/dental-chairs*', async route => {
          await new Promise(resolve => setTimeout(resolve, 1500))
          await route.continue().catch(() => {})
        })

        await page.goto(SURFACE, { waitUntil: 'domcontentloaded' })

        const loading = await page
          .locator('[data-environments-loading]')
          .waitFor({ state: 'visible', timeout: 6000 })
          .then(() => true)
          .catch(() => false)

        detail.loading = {
          visible: loading,
          label: await page
            .locator('[data-environments-loading] [aria-label]')
            .first()
            .getAttribute('aria-label')
            .catch(() => null),
          shot: loading ? await shot(page, `shots/walk/${SLUG}/${entry.id}-loading.png`) : null
        }

        await page.unroute('**/api/dental-chairs*')
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
          emptyMarker: (await page.locator('[data-environments-empty]').count()) > 0,
          title: /No se encontraron ambientes/.test(emptyBody),
          description: /Prueba con otros términos de búsqueda/.test(emptyBody),
          clearOffered:
            (await page.locator('main button').filter({ hasText: 'Limpiar filtros' }).count()) > 0
        }

        await clickByLabel(page, 'Limpiar filtros')
        await page.waitForTimeout(400)

        await page.route('**/api/dental-chairs*', route =>
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
          .locator('[data-environments-error]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const errorText = (await visibleTexts(page, '[data-environments-error]')).join(' | ')
        const retry = page
          .locator('[data-environments-error] button')
          .filter({ hasText: 'Reintentar' })
          .first()
        const retryOffered = (await retry.count()) > 0

        detail.error = {
          painted: errorPainted,
          text: errorText,
          retryOffered,
          shot: await shot(page, `shots/walk/${SLUG}/${entry.id}-error.png`)
        }

        await page.unroute('**/api/dental-chairs*')

        if (retryOffered) {
          await respondTo(page, { method: 'GET', path: '/api/dental-chairs' }, () =>
            retry.click({ timeout: 8000 })
          )
          await page.waitForTimeout(500)
        }

        const rowsAfterRetry = await rowCount(page, ROWS)

        detail.error.rowsAfterRetry = rowsAfterRetry
        detail.error.toast = await toastText(page)

        entry.obtained = `carga: ${detail.loading.visible ? `muestra «${detail.loading.label ?? 'Cargando'}»` : 'sin placeholder observable'} · vacío: ${detail.empty.title && detail.empty.description ? '«No se encontraron ambientes» con su explicación' : 'sin texto de vacío'}${detail.empty.clearOffered ? ' y «Limpiar filtros»' : ' y sin acción'} · error 500: ${detail.error.painted ? `bloque «${detail.error.text.slice(0, 90)}»` : 'sin bloque de error'} · «Reintentar» ${detail.error.retryOffered ? 'ofrecido' : 'ausente'} · tras reintentar ${rowsAfterRetry} fila(s)`
        entry.detail = detail
        entry.verdict =
          detail.loading.visible &&
          detail.empty.title &&
          detail.empty.description &&
          detail.empty.clearOffered &&
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
  } finally {
    if (created.chair && !created.deleted) {
      const removed = await api(session, 'DELETE', `/api/dental-chairs/${created.chair.id}`)

      evidence.cleanup.push({
        type: 'dental-chair',
        id: created.chair.id,
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
      failure => failure.status === 409 && /\/api\/dental-chairs\/\d+$/.test(failure.url ?? ''),
      'la caminata pulsa «Eliminar» sobre un sillón sembrado con citas: el 409 es el contrato medido'
    )
    markExpected(
      evidence.failedRequests,
      failure => failure.status === 422 && /\/api\/dental-chairs$/.test(failure.url ?? ''),
      'el envío vacío del alta es un 422 que la propia caminata provoca'
    )
    markExpected(
      evidence.failedRequests,
      failure => failure.status === 500 && /\/api\/dental-chairs/.test(failure.url ?? ''),
      'el paso `states` provoca un 500 para medir el estado de error de la lista'
    )

    evidence.fixtures = { ...evidence.fixtures, ...fixtureSummary(fixtures) }
    evidence.counts = walkCounts(evidence.steps)

    audit('walk-environments', evidence)
  }

  expect(evidence.pageErrors, 'page errors during the environments walkthrough').toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    'declared steps of the environments walkthrough'
  ).toEqual([
    '01-list',
    '02-filter',
    '03-create-empty',
    '04-create',
    '05-edit',
    '06-archive',
    '07-reactivate',
    '08-delete-guard',
    '09-delete',
    '10-detail',
    '11-back',
    '12-states'
  ])
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    'every step of the environments walkthrough has its capture'
  ).toBe(true)
})
