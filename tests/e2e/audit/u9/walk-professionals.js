// Plan #13 (issue #372, U9) — layer 4: the professionals ABM, action by action.
//
// The walkthrough drives `/professionals` the way administration does: read the
// list, search it, submit the empty form, create a professional, rename it,
// deactivate it, press the delete door on a seeded doctor who holds history,
// delete the account the walk created, open the fixture's detail with both tabs,
// and come back with the browser.
//
// Two facts #372 asks for are measured, not assumed:
//  * «Ninguna superficie ofrece una acción que la API prohíbe»: the row offers
//    «Eliminar» on every account, seeded or not. The step presses it on a seeded
//    doctor and quotes the 409 the API answers together with what the surface
//    actually tells the user (the reason, or a generic line).
//  * the list is a hand-written `<table>` with its own `overflow-x-auto` wrapper,
//    so it carries neither `UiDataTable`'s pagination nor its scroll affordance.
//
// Every write goes through the product's own doors on rows this run created; the
// seeded accounts and chairs are only read. `DEFECTO` / `NO-OP` / `CONFUSO` are
// evidence, never a thrown failure: the only hard assertions are that no
// `pageerror` was raised and that every declared step was recorded.

import { test, expect } from 'playwright/test'

import {
  PASSWORD,
  classifyFailures,
  collectDiagnostics,
  primeSession,
  settleLayout
} from '../harness.js'

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

const SURFACE = '/professionals'
const SLUG = 'professionals'
const MARKER = 'AUDIT-U9'
const ROWS = 'main [data-team-row]'
const SEARCH = '[data-professionals-search] input'
const DIALOG = '[role="dialog"]:visible'
const TAB_SELECTOR = 'main nav[aria-label="Secciones del profesional"] button'

const DECLARED_CONSOLE = [
  {
    pattern: /Failed to load resource: .*409/,
    reason:
      'la caminata pulsa «Eliminar» sobre un odontólogo sembrado con historia: el 409 es el contrato que se mide'
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

const search = async (page, value) => {
  await fillField(page, 'Buscar profesionales por nombre', value)
  await settleLayout(page)
  await page.waitForTimeout(150)
}

const listText = page =>
  page.evaluate(() => (document.querySelector('main')?.innerText ?? '').replace(/\s+/g, ' '))

test('@U9 walks profesionales', async ({ page }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const session = await login('administrador')
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const created = { professional: null, deleted: false }

  const walkName = `${MARKER}-${fixtures.token} profesional de la caminata`
  const editedName = `${walkName} (editado)`
  const username = `audit_u9_walk_${fixtures.token}`
  const email = `audit-u9-walk-${fixtures.token}@audit.local`
  const license = `AUD9-${fixtures.token}-W`

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks profesionales',
    role: 'administrador',
    steps: [],
    counts: null,
    fixtures: { marker: fixtures.marker, ok: fixtures.ok, token: fixtures.token },
    created: { name: walkName, username },
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
      'Abrir /professionals como administración',
      'La lista pinta sus filas, las siete cabeceras y el estado de cada fila',
      async entry => {
        const painted = await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        const rows = await rowCount(page, ROWS)
        const headers = await tableHeaders(page)
        const statuses = await visibleTexts(page, `${ROWS} td:nth-child(6)`)
        const caption = (await visibleTexts(page, '[data-team-results]'))[0] ?? null
        const kpis = await visibleTexts(page, '[data-stat]')
        const primitive = await tablePrimitive(page)
        const call = lastCall(requests, 'GET', '/api/users')
        const badged =
          statuses.length === rows && statuses.every(label => /^(Activo|Inactivo)$/.test(label))

        entry.obtained = `${rows} filas · cabeceras ${headers.length} ${JSON.stringify(headers)} · estados ${JSON.stringify([...new Set(statuses)])} · «${caption ?? '—'}» · KPIs ${JSON.stringify(kpis)} · lista: ${primitive.primitive} (data-table-scroll ${primitive.dataTables}, affordance de scroll ${primitive.scrollAffordance})`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = {
          painted,
          rows,
          headers,
          statuses: statuses.slice(0, 12),
          kpis,
          caption,
          primitive
        }
        entry.verdict = rows > 0 && headers.length === 7 && badged ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la tabla, sus cabeceras o el badge de estado de las filas no coincide con lo pintado'

        return entry
      }
    )

    await step(
      'search',
      'Buscar el profesional de la fixture por su nombre y limpiar el filtro',
      'El filtro deja exactamente su fila y «Limpiar filtros» devuelve el total',
      async entry => {
        const fixture = fixtures.professionals.plain
        const before = await rowCount(page, ROWS)

        await search(page, fixture.name)

        const filtered = await rowCount(page, ROWS)
        const filteredTexts = await visibleTexts(page, ROWS)
        const caption = (await visibleTexts(page, '[data-team-results]'))[0] ?? null
        const clearOffered = await page.locator('[data-team-clear-filters]:visible').count()
        const cleared = await clickByLabel(page, 'Limpiar filtros')

        await settleLayout(page)
        await page.waitForTimeout(150)

        const after = await rowCount(page, ROWS)
        const searchValue = await page
          .locator(SEARCH)
          .inputValue()
          .catch(() => null)
        const hitsFixture = Boolean(filteredTexts[0]?.includes(fixture.name))

        entry.obtained = `«${fixture.name}» → ${filtered} fila (${hitsFixture ? 'la fila buscada' : 'otra fila'}) · «${caption ?? '—'}» · «Limpiar filtros» visible ${clearOffered > 0} · limpiar → ${after} filas, búsqueda «${searchValue ?? ''}»`
        entry.detail = {
          before,
          filtered,
          after,
          caption,
          searchValue,
          clearOffered,
          clicked: cleared
        }
        entry.verdict =
          filtered === 1 && hitsFixture && after === before && searchValue === '' && cleared.ok
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el filtro o su limpieza no dejan el estado que la superficie promete'

        return entry
      }
    )

    await step(
      'create-empty',
      'Abrir «Nuevo Profesional» y pulsar «Crear» con el formulario vacío',
      'El formulario marca nombre y email en el campo y no dispara ningún POST',
      async entry => {
        const opened = await clickByLabel(page, 'Nuevo Profesional')
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
        const postsBefore = requests.filter(
          row => row.method === 'POST' && row.path === '/api/users'
        ).length

        const attempted = await respondTo(
          page,
          { method: 'POST', path: '/api/users' },
          () =>
            dialog
              .locator('button')
              .filter({ hasText: 'Guardar profesional' })
              .first()
              .click({ timeout: 8000 }),
          4000
        )

        await page.waitForTimeout(300)

        const errors = await fieldErrors(page)
        const postsAfter = requests.filter(
          row => row.method === 'POST' && row.path === '/api/users'
        ).length
        const namesName = errors.messages.some(message => /nombre/i.test(message))
        const namesEmail = errors.messages.some(message => /email/i.test(message))

        entry.obtained = `diálogo «${title}» abierto ${opened.ok} · mensajes ${JSON.stringify(errors.messages)} · POST /api/users ${postsBefore} → ${postsAfter}${attempted.status ? ` (${attempted.status})` : ''}`
        entry.request = requestOf(attempted)
        entry.detail = {
          title,
          errors,
          postsBefore,
          postsAfter,
          dialogOpen: await dialog.isVisible().catch(() => false)
        }
        entry.verdict =
          painted && postsAfter === postsBefore && namesName && namesEmail ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el envío vacío no deja el nombre y el email señalados en su campo, o dispara una escritura'

        await closeDialog(page)

        return entry
      }
    )

    await step(
      'create',
      'Crear un profesional con los campos mínimos del formulario',
      'El POST responde 201, el toast confirma y la fila aparece en la lista',
      async entry => {
        await clickByLabel(page, 'Nuevo Profesional')

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        await fillField(page, 'Nombre Completo', walkName)
        await fillField(page, 'Usuario', username)
        await fillField(page, 'Email', email)
        await fillField(page, 'Teléfono', '+51999000222')
        await fillField(page, 'Matrícula', license)
        await fillField(page, 'Contraseña', PASSWORD)

        const specialty = await pickOption(
          page,
          page
            .locator(`${DIALOG} .select-container`)
            .filter({ hasText: 'Seleccionar especialidad' }),
          'Odontología General'
        )

        const sent = await respondTo(page, { method: 'POST', path: '/api/users' }, () =>
          dialog
            .locator('button')
            .filter({ hasText: 'Guardar profesional' })
            .first()
            .click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)
        const id = sent.body?.data?.id ?? null

        if (id) created.professional = { id, name: walkName }

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})
        await search(page, walkName)
        await page.waitForTimeout(300)

        const rows = await rowCount(page, ROWS)
        const rowText = rows > 0 ? (await visibleTexts(page, ROWS))[0] : null
        const listed = Boolean(rowText?.includes(walkName))

        entry.obtained = `POST ${sent.status ?? 'sin petición'} · id ${id ?? '—'} · especialidad «${specialty.selected ?? `no elegida (${specialty.reason})`}» · toast «${toast}» · ${rows} fila ${listed ? 'con el nombre nuevo' : 'sin el nombre'}`
        entry.request = requestOf(sent)
        entry.detail = { id, toast, specialty, rows, rowText }
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
      'Editar el profesional recién creado y cambiarle el nombre',
      'El PUT responde 200 y la fila muestra el nombre nuevo',
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
        await fillField(page, 'Nombre', editedName)

        const sent = await respondTo(page, { method: 'PUT', path: /^\/api\/users\/\d+$/ }, () =>
          dialog
            .locator('button')
            .filter({ hasText: 'Actualizar' })
            .first()
            .click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})
        await search(page, editedName)
        await page.waitForTimeout(300)

        const rows = await rowCount(page, ROWS)
        const rowText = rows > 0 ? (await visibleTexts(page, ROWS))[0] : null
        const listed = Boolean(rowText?.includes(editedName))

        entry.obtained = `PUT ${sent.status ?? 'sin petición'} · nombre devuelto «${sent.body?.data?.name ?? '—'}» · ${rows} fila ${listed ? 'con el nombre editado' : 'sin el nombre editado'} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = { toast, rows, rowText, name: sent.body?.data?.name ?? null }
        entry.verdict = sent.status === 200 && listed ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el PUT no devuelve 200 o la fila no refleja el nombre nuevo'

        return entry
      }
    )

    await step(
      'deactivate',
      'Editar el profesional y cambiar su estado a «Inactivo»',
      'El PUT guarda el estado y la fila pinta el badge «Inactivo»',
      async entry => {
        const row = rowNamed(page, editedName)
        const visible = await row
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!visible) {
          entry.obtained = `la fila «${editedName}» no está visible para desactivarla`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'no se pudo abrir la fila que la caminata creó'

          return entry
        }

        await row.locator('button').filter({ hasText: 'Editar' }).first().click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        const status = await pickOption(
          page,
          page.locator(`${DIALOG} [data-professionals-edit-status] button`),
          'Inactivo'
        )

        const sent = await respondTo(page, { method: 'PUT', path: /^\/api\/users\/\d+$/ }, () =>
          dialog
            .locator('button')
            .filter({ hasText: 'Actualizar' })
            .first()
            .click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})
        await search(page, editedName)
        await page.waitForTimeout(300)

        const badge = (await visibleTexts(page, `${ROWS} td:nth-child(6)`))[0] ?? null
        const inactive = badge === 'Inactivo'
        const deleteStillOffered = (await buttonLabels(rowNamed(page, editedName))).includes(
          'Eliminar'
        )

        entry.obtained = `estado elegido «${status.selected ?? `no elegido (${status.reason})`}» · PUT ${sent.status ?? 'sin petición'} · badge «${badge ?? '—'}» · toast «${toast}» · «Eliminar» sigue ofrecido ${deleteStillOffered}`
        entry.request = requestOf(sent)
        entry.detail = {
          status,
          badge,
          toast,
          inactive,
          deleteStillOffered,
          isActive: sent.body?.data?.is_active ?? null
        }
        entry.verdict = sent.status === 200 && inactive ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el estado no se guarda o la fila no pinta «Inactivo»; la desactivación es el camino de baja del ABM'

        return entry
      }
    )

    await step(
      'delete-guard',
      'Pulsar «Eliminar» sobre un odontólogo sembrado con historia y confirmar',
      'La API rechaza el borrado (409) y la superficie le explica el motivo al usuario',
      async entry => {
        const fixture = fixtures.professionals.plain

        await search(page, fixture.name)
        const fixtureActions = await buttonLabels(rowNamed(page, fixture.name))

        await search(page, editedName)
        const ownActions = await buttonLabels(rowNamed(page, editedName))

        const roster = await api(
          session,
          'GET',
          '/api/users?role=odontologo,implantologo,tecnico_dental,asistente'
        )
        const history = await historyIds(session)
        const withHistory = new Set(history.users)
        const seeded = (roster.body?.data ?? []).find(
          row =>
            withHistory.has(row.id) && row.role === 'odontologo' && !row.name.startsWith(MARKER)
        )

        if (!seeded) {
          entry.obtained = `${roster.body?.data?.length ?? 0} cuentas clínicas · ${withHistory.size} con citas · ninguna sembrada con rol odontólogo`
          entry.verdict = 'NO PROBADO'
          entry.notes =
            'la base de auditoría no tiene un odontólogo sembrado con citas: no hay fila que la API deba proteger'

          return entry
        }

        await search(page, seeded.name)
        const row = rowNamed(page, seeded.name)
        const seededActions = await buttonLabels(row)

        await row.locator('button').filter({ hasText: 'Eliminar' }).first().click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const sent = await respondTo(page, { method: 'DELETE', path: /^\/api\/users\/\d+$/ }, () =>
          confirmDialog(page, 'Eliminar')
        )

        await page.waitForTimeout(500)

        const toast = await toastText(page)
        const rows = await rowCount(page, ROWS)
        const apiMessage = sent.body?.message ?? null
        const actionable = /Desactívalo en su lugar\./.test(apiMessage ?? '')
        const told = Boolean(toast) && Boolean(apiMessage) && toast.includes(apiMessage)
        const deliversActionable = told && actionable
        const genericToast = /Error al eliminar el profesional/.test(toast)

        entry.obtained = `fila sembrada «${seeded.name}» (id ${seeded.id}) · acciones de la fila ${JSON.stringify(seededActions)} · confirmación «${confirmText}» · DELETE ${sent.status ?? 'sin petición'} · mensaje de la API «${apiMessage ?? '—'}» (accionable ${actionable}) · toast «${toast}» (mensaje íntegro ${told}, aviso genérico ${genericToast}) · la fila sigue en la lista ${rows > 0}`
        entry.request = requestOf(sent)
        entry.detail = {
          seeded: { id: seeded.id, name: seeded.name, role: seeded.role },
          seededActions,
          fixtureActions,
          ownActions,
          confirmText,
          apiMessage,
          actionable,
          tellsApiMessage: told,
          deliversActionable,
          genericToast,
          toast,
          rows,
          historyStatus: history.status,
          deleteDoorOfferedOnEveryRow: [seededActions, fixtureActions, ownActions].every(labels =>
            labels.includes('Eliminar')
          )
        }
        entry.verdict =
          sent.status === 409 && rows > 0 ? (deliversActionable ? 'OK' : 'DEFECTO') : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la API rechaza el borrado con un motivo accionable (409) y la superficie no entrega ese mensaje: el motivo no llega al usuario, y «Eliminar» se ofrece igual sobre la fila que la API protege'

        return entry
      }
    )

    await step(
      'delete',
      'Eliminar el profesional que esta caminata creó',
      'El DELETE responde 200 y la fila desaparece de la lista',
      async entry => {
        await search(page, editedName)

        const row = rowNamed(page, editedName)
        const visible = await row
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!visible) {
          entry.obtained = `la fila «${editedName}» no está visible para eliminarla`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'no se pudo abrir la fila que la caminata creó'

          return entry
        }

        await row.locator('button').filter({ hasText: 'Eliminar' }).first().click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const sent = await respondTo(page, { method: 'DELETE', path: /^\/api\/users\/\d+$/ }, () =>
          confirmDialog(page, 'Eliminar')
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

        entry.obtained = `confirmación «${confirmText}» · DELETE ${sent.status ?? 'sin petición'} · ${rows} fila(s) tras el borrado · estado vacío ${emptyShown} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = { confirmText, toast, rows, emptyShown }
        entry.verdict = sent.status === 200 && rows === 0 ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el borrado de una cuenta sin historia no responde 200 o la fila permanece en la lista'

        return entry
      }
    )

    await step(
      'detail',
      'Abrir «Ver Detalle» del profesional de la fixture',
      'La ficha muestra su nombre, sus dos pestañas y el historial de auditoría de la cuenta',
      async entry => {
        const fixture = fixtures.professionals.plain
        const auditPath = `/api/audit-logs/user/${fixture.id}`

        await search(page, fixture.name)

        const opened = await rowNamed(page, fixture.name)
          .locator('button')
          .filter({ hasText: 'Ver Detalle' })
          .first()
          .click({ timeout: 8000 })
          .then(() => true)
          .catch(() => false)

        await page
          .waitForURL(url => url.pathname.endsWith(`/professionals/${fixture.id}`), {
            timeout: 12000
          })
          .catch(() => {})
        await settle(page)

        const path = new URL(page.url()).pathname
        const h1 = (await visibleTexts(page, '[data-professional-detail-hero] h1'))[0] ?? null
        const tabs = await visibleTexts(page, TAB_SELECTOR)
        const facts = await rowCount(page, '[data-fact-row]')

        const audit = await respondTo(page, { method: 'GET', path: auditPath }, () =>
          page
            .locator(TAB_SELECTOR)
            .filter({ hasText: 'Historial' })
            .first()
            .click({ timeout: 8000 })
        )

        await page.waitForTimeout(700)

        const panel = (await visibleTexts(page, '[data-professional-detail]')).join(' | ')
        const fromApi = await api(session, 'GET', auditPath)
        const entries = Array.isArray(fromApi.body?.data) ? fromApi.body.data.length : null
        const empty = /No hay historial de auditoría/.test(panel)
        const painted = entries === 0 ? empty : /por /i.test(panel)

        entry.obtained = `URL ${path} · h1 «${h1 ?? '—'}» · pestañas ${JSON.stringify(tabs)} · ${facts} filas de datos · «Historial» → GET ${auditPath} ${audit.status ?? 'sin petición'} · ${entries ?? '—'} entradas en la API · la tab pinta ${empty ? 'el estado vacío declarado' : painted ? 'las entradas' : 'nada reconocible'}`
        entry.request = requestOf(audit)
        entry.detail = {
          path,
          opened,
          h1,
          tabs,
          facts,
          entries,
          empty,
          painted,
          panel: panel.slice(0, 600)
        }
        entry.verdict =
          path === `/professionals/${fixture.id}` &&
          Boolean(h1?.includes(fixture.name)) &&
          tabs.join('|') === 'Datos|Historial' &&
          facts >= 8 &&
          audit.status === 200 &&
          painted
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la ficha no muestra el nombre, sus dos secciones o el historial que publica la API'

        return entry
      }
    )

    await step(
      'back',
      'Volver a la lista con el botón del navegador',
      'La lista reaparece en /professionals con la búsqueda limpia',
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
        const caption = (await visibleTexts(page, '[data-team-results]'))[0] ?? null

        entry.obtained = `URL ${path} · búsqueda «${searchValue ?? ''}» · ${rows} filas · «${caption ?? '—'}»`
        entry.detail = { path, searchValue, rows, caption }
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
      'La lista muestra su placeholder de carga, un vacío que explica el filtro y un bloque de error con reintento que declara «N/D» en vez de un equipo vacío',
      async entry => {
        const detail = { loading: null, empty: null, error: null }

        // Carga: the list request is delayed so the placeholder is observable.
        await page.route('**/api/users*', async route => {
          await new Promise(resolve => setTimeout(resolve, 1500))
          await route.continue().catch(() => {})
        })

        await page.goto(SURFACE, { waitUntil: 'domcontentloaded' })

        const loading = await page
          .waitForFunction(
            () => /Cargando profesionales/i.test(document.querySelector('main')?.innerText ?? ''),
            undefined,
            { timeout: 6000, polling: 100 }
          )
          .then(() => true)
          .catch(() => false)

        detail.loading = {
          visible: loading,
          shot: loading ? await shot(page, `shots/walk/${SLUG}/${entry.id}-loading.png`) : null,
          text: loading ? (await listText(page)).slice(0, 160) : null
        }

        await page.unroute('**/api/users*')
        await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 15000 })
          .catch(() => {})

        // Vacío: a filter nobody matches.
        await search(page, 'ZZZ-AUDIT-U9-SIN-RESULTADOS')
        await page.waitForTimeout(300)

        const emptyBody = await listText(page)
        const emptyClear = await page.locator('[data-team-clear-filters]:visible').count()

        detail.empty = {
          rows: await rowCount(page, ROWS),
          title: /Sin resultados/.test(emptyBody),
          description: /Ningún profesional coincide con los filtros activos/.test(emptyBody),
          clearOffered: emptyClear > 0
        }

        await clickByLabel(page, 'Limpiar filtros')
        await page.waitForTimeout(200)

        // Error: one forced 500 on the list endpoint.
        await page.route('**/api/users*', route =>
          route
            .fulfill({
              status: 500,
              contentType: 'application/json',
              body: JSON.stringify({ message: 'AUDIT-U9 error provocado' })
            })
            .catch(() => {})
        )

        await page.reload({ waitUntil: 'domcontentloaded' })
        await page.waitForTimeout(1500)

        const errorPainted = await page
          .locator('[data-team-error]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const errorText = (await visibleTexts(page, '[data-team-error]')).join(' | ')
        const errorBody = await listText(page)
        const kpisInError = await visibleTexts(page, '[data-stat]')
        const retry = page.locator('[data-team-retry]').first()
        const retryOffered = (await retry.count()) > 0
        const emptyShown = /Sin profesionales registrados/.test(errorBody)
        const toast = await toastText(page)

        detail.error = {
          painted: errorPainted,
          text: errorText,
          kpis: kpisInError,
          allKpisUnavailable:
            kpisInError.length === 4 && kpisInError.every(value => value === 'N/D'),
          emptyShown,
          toast,
          retryOffered,
          shot: await shot(page, `shots/walk/${SLUG}/${entry.id}-error.png`)
        }

        await page.unroute('**/api/users*')

        if (retryOffered) {
          await respondTo(page, { method: 'GET', path: '/api/users' }, () =>
            retry.click({ timeout: 8000 })
          )
          await page.waitForTimeout(500)
          await page
            .locator(ROWS)
            .first()
            .waitFor({ state: 'visible', timeout: 10000 })
            .catch(() => {})
        }

        detail.error.rowsAfterRetry = await rowCount(page, ROWS)
        detail.error.kpisAfterRetry = await visibleTexts(page, '[data-stat]')

        entry.obtained = `carga: ${detail.loading.visible ? 'muestra «Cargando profesionales...»' : 'sin placeholder observable'} · vacío: ${detail.empty.title && detail.empty.description ? '«Sin resultados» con su explicación' : 'sin texto de vacío'}${detail.empty.clearOffered ? ' y «Limpiar filtros»' : ' y sin acción'} · error 500: ${detail.error.painted ? `bloque «${detail.error.text.slice(0, 90)}»` : 'sin bloque de error'} · KPIs ${JSON.stringify(detail.error.kpis)} · «Sin profesionales registrados» ${detail.error.emptyShown ? 'pintado (vacío falso)' : 'no pintado'} · «Reintentar» ${detail.error.retryOffered ? 'ofrecido' : 'ausente'} · tras reintentar ${detail.error.rowsAfterRetry} fila(s) · KPIs ${JSON.stringify(detail.error.kpisAfterRetry)} · toast «${detail.error.toast}»`
        entry.detail = detail
        entry.verdict =
          detail.loading.visible &&
          detail.empty.title &&
          detail.empty.description &&
          detail.empty.clearOffered &&
          detail.error.painted &&
          detail.error.allKpisUnavailable &&
          !detail.error.emptyShown &&
          detail.error.retryOffered &&
          detail.error.rowsAfterRetry > 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el fallo de carga se presenta como un vacío, no declara «N/D» en sus KPIs o no ofrece un reintento que devuelva el equipo'

        return entry
      }
    )
  } finally {
    // A step that died before its own delete leaves the row behind: the session
    // retires what it created, and the outcome is recorded.
    if (created.professional && !created.deleted) {
      const removed = await api(session, 'DELETE', `/api/users/${created.professional.id}`)

      evidence.cleanup.push({
        type: 'professional',
        id: created.professional.id,
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
      failure => failure.status === 409 && /\/api\/users\/\d+$/.test(failure.url ?? ''),
      'la caminata pulsa «Eliminar» sobre un odontólogo sembrado con historia: el 409 es el contrato medido'
    )
    markExpected(
      evidence.failedRequests,
      failure => failure.status === 500 && /\/api\/users/.test(failure.url ?? ''),
      'el paso `states` provoca un 500 para medir el estado de error de la lista'
    )

    evidence.fixtures = { ...evidence.fixtures, ...fixtureSummary(fixtures) }
    evidence.counts = walkCounts(evidence.steps)

    audit('walk-professionals', evidence)
  }

  expect(evidence.pageErrors, 'page errors during the professionals walkthrough').toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    'declared steps of the professionals walkthrough'
  ).toEqual([
    '01-list',
    '02-search',
    '03-create-empty',
    '04-create',
    '05-edit',
    '06-deactivate',
    '07-delete-guard',
    '08-delete',
    '09-detail',
    '10-back',
    '11-states'
  ])
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    'every step of the professionals walkthrough has its capture'
  ).toBe(true)
})
