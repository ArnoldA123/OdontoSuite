// Plan #13 (issue #372, U9) — layer 4: the specialties ABM, action by action.
//
// The specialty catalogue has no DELETE door: `DELETE /api/specialties/{id}`
// answers 405, so the lifecycle is create → rename → deactivate/activate and the
// row is retired by deactivating it. The walkthrough measures that contract from
// both sides: the surface must not paint a delete control, and the API must
// refuse one when the walk probes it on the row it created.
//
// `code` is the immutable identifier the procedure catalogue and the CSV import
// reference. The edit form disables it; the step records the field state, the
// hint that explains it and the code the row keeps after the rename.
//
// `DEFECTO` / `NO-OP` / `CONFUSO` are evidence, never a thrown failure: the only
// hard assertions are that no `pageerror` was raised and that every declared
// step was recorded.

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

const SURFACE = '/specialties'
const SLUG = 'specialties'
const MARKER = 'AUDIT-U9'
const ROWS = 'main .data-table-row'
const DIALOG = '[role="dialog"]:visible'
const SEARCH = '[data-specialties-search] input'

const DECLARED_CONSOLE = [
  {
    pattern: /Failed to load resource: .*500/,
    reason: 'el paso `states` provoca un 500 para medir el estado de error de la lista'
  }
]

const rowNamed = (page, name) => page.locator(ROWS).filter({ hasText: name }).first()

const listText = page =>
  page.evaluate(() => (document.querySelector('main')?.innerText ?? '').replace(/\s+/g, ' '))

/** Every button of a scope with its visible or accessible name. */
const buttonLabels = async locator =>
  locator
    .locator('button')
    .evaluateAll(nodes =>
      nodes
        .map(node =>
          (
            (node.innerText ?? '').trim() ||
            node.getAttribute('aria-label') ||
            node.getAttribute('title') ||
            ''
          )
            .trim()
            .replace(/\s+/g, ' ')
        )
        .filter(Boolean)
    )

const kpiCards = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[data-kpi-card]')).map(card => {
      const lines = (card.innerText ?? '')
        .split('\n')
        .map(line => line.trim())
        .filter(Boolean)

      return {
        key: card.getAttribute('data-kpi-card'),
        label: lines[0] ?? null,
        value: (card.querySelector('[data-kpi-value]')?.innerText ?? '').trim() || null,
        caption: lines.at(-1) ?? null
      }
    })
  )

const pathOf = value => {
  try {
    return new URL(value).pathname
  } catch {
    return null
  }
}

/** Fills the search and waits for the debounced list request it triggers. */
const search = async (page, value) => {
  const pending = page
    .waitForResponse(
      response => {
        if (response.request().method() !== 'GET' || pathOf(response.url()) !== '/api/specialties')
          return false

        try {
          return (new URL(response.url()).searchParams.get('q') ?? '') === value
        } catch {
          return false
        }
      },
      { timeout: 8000 }
    )
    .catch(() => null)

  const filled = await fillField(page, 'Buscar por nombre, código o descripción', value)
  const response = await pending

  await settle(page)

  return { filled, status: response?.status() ?? null }
}

/** Opens the status filter and waits for the list request it triggers. */
const pickStatus = async (page, label) => {
  const pending = page
    .waitForResponse(
      response =>
        response.request().method() === 'GET' && pathOf(response.url()) === '/api/specialties',
      { timeout: 8000 }
    )
    .catch(() => null)

  const picked = await pickOption(page, '[data-status-filter] button', label)
  const response = await pending

  await settle(page)

  return { picked, status: response?.status() ?? null }
}

test('@U9 walks especialidades', async ({ page }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const session = await login('administrador')
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)

  const walkName = `${MARKER}-${fixtures.token} especialidad de la caminata`
  const editedName = `${walkName} (editada)`
  const code = `u9_walk_${fixtures.token}`

  const created = { specialty: null, active: null }
  const residues = { fixture: fixtures.specialties.fixture, walk: null }

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks especialidades',
    role: 'administrador',
    steps: [],
    counts: null,
    fixtures: { marker: fixtures.marker, ok: fixtures.ok, token: fixtures.token },
    created: { name: walkName, code },
    residues: null,
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
      'Abrir /specialties como administración',
      'La lista pinta sus filas, las cuatro cabeceras del dominio, el estado de cada fila y los cuatro KPI con lo que cuenta cada uno',
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
        const cards = await kpiCards(page)
        const primitive = await tablePrimitive(page)
        const caption = (await visibleTexts(page, '[data-hero-caption]'))[0] ?? null
        const call = lastCall(requests, 'GET', '/api/specialties')
        const wanted = ['especialidad', 'procedimientos', 'profesionales', 'estado']
        const headersNamed = wanted.every(label =>
          headers.some(header => header.toLowerCase() === label)
        )
        const badged =
          statuses.length === rows && statuses.every(label => /^(Activa|Inactiva)$/.test(label))
        const total = cards.find(card => card.key === 'total')
        const kpisComplete =
          cards.length === 4 && cards.every(card => card.value !== null && card.caption)

        entry.obtained = `${rows} filas · cabeceras ${headers.length} ${JSON.stringify(headers)} · estados ${JSON.stringify([...new Set(statuses)])} · KPI ${JSON.stringify(cards.map(card => `${card.label} ${card.value} (${card.caption})`))} · «${caption ?? '—'}» · lista: ${primitive.primitive} (data-table-scroll ${primitive.dataTables}, affordance de scroll ${primitive.scrollAffordance})`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = {
          painted,
          rows,
          headers,
          statuses,
          cards,
          primitive,
          caption,
          totalMatchesRows: total?.value === String(rows)
        }
        entry.verdict =
          rows > 0 && headersNamed && badged && kpisComplete && total?.value === String(rows)
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la tabla, sus cabeceras, el badge de estado o la tira de KPI no coincide con lo pintado'

        return entry
      }
    )

    await step(
      'filter',
      'Buscar la especialidad de la fixture, recorrer el filtro de estado y limpiar el filtro',
      'La búsqueda deja exactamente la fila buscada, cada estado deja sólo su población y limpiar devuelve el total',
      async entry => {
        const { fixture } = fixtures.specialties
        const before = await rowCount(page, ROWS)

        const searched = await search(page, fixture.name)
        const filtered = await rowCount(page, ROWS)
        const filteredTexts = await visibleTexts(page, ROWS)
        const searchCall = lastCall(requests, 'GET', '/api/specialties')
        const hitsFixture = Boolean(filteredTexts[0]?.includes(fixture.name))

        const cleared = await search(page, '')
        const afterClear = await rowCount(page, ROWS)
        const searchValue = await page
          .locator(SEARCH)
          .inputValue()
          .catch(() => null)

        const active = await pickStatus(page, 'Activas')
        const activeRows = await rowCount(page, ROWS)
        const activeStatuses = await visibleTexts(page, `${ROWS} td:nth-child(4)`)

        const inactive = await pickStatus(page, 'Inactivas')
        const inactiveRows = await rowCount(page, ROWS)
        const inactiveStatuses = await visibleTexts(page, `${ROWS} td:nth-child(4)`)
        const inactiveEmpty = inactiveRows === 0 ? await listText(page) : null

        const all = await pickStatus(page, 'Todos los estados')
        const allRows = await rowCount(page, ROWS)

        entry.obtained = `«${fixture.name}» → ${filtered} fila (${hitsFixture ? 'la buscada' : 'otra'}) · q=${searchCall?.query?.q ?? '—'} ${searched.status ?? 'sin respuesta'} · limpiar → ${afterClear} filas, búsqueda «${searchValue ?? ''}» · Activas → ${activeRows} filas ${JSON.stringify([...new Set(activeStatuses)])} · Inactivas → ${inactiveRows} filas ${JSON.stringify([...new Set(inactiveStatuses)])}${inactiveEmpty ? ` («${inactiveEmpty.slice(0, 80)}»)` : ''} · Todos → ${allRows} filas`
        entry.request = searchCall
          ? { method: searchCall.method, path: searchCall.path, status: searchCall.status }
          : null
        entry.detail = {
          before,
          searched,
          filtered,
          hitsFixture,
          searchCall,
          cleared,
          afterClear,
          searchValue,
          active: { status: active.status, rows: activeRows, statuses: activeStatuses },
          inactive: {
            status: inactive.status,
            rows: inactiveRows,
            statuses: inactiveStatuses,
            empty: inactiveEmpty
          },
          all: { status: all.status, rows: allRows }
        }
        entry.verdict =
          filtered === 1 &&
          hitsFixture &&
          searchCall?.query?.q === fixture.name &&
          afterClear === before &&
          searchValue === '' &&
          activeRows > 0 &&
          activeStatuses.every(label => label === 'Activa') &&
          inactiveStatuses.every(label => label === 'Inactiva') &&
          allRows === before
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la búsqueda no deja exactamente la fila buscada, o un estado del filtro devuelve filas de la otra población'

        return entry
      }
    )

    await step(
      'create-empty',
      'Abrir «Nueva especialidad» y pulsar «Crear especialidad» con el formulario vacío',
      'El formulario marca el código y el nombre en su campo y no dispara ningún POST',
      async entry => {
        const opened = await clickByLabel(page, 'Nueva especialidad')

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
          row => row.method === 'POST' && row.path === '/api/specialties'
        ).length

        const attempted = await respondTo(
          page,
          { method: 'POST', path: '/api/specialties' },
          () => page.locator(`${DIALOG} [data-modal-submit]`).click({ timeout: 8000 }),
          4000
        )

        await page.waitForTimeout(300)

        const errors = await fieldErrors(page)
        const toast = await toastText(page)
        const postsAfter = requests.filter(
          row => row.method === 'POST' && row.path === '/api/specialties'
        ).length
        const namesCode = errors.messages.some(message => /código/i.test(message))
        const namesName = errors.messages.some(message => /nombre/i.test(message))

        entry.obtained = `diálogo «${title}» abierto ${opened.ok} · mensajes ${JSON.stringify(errors.messages)} · campos inválidos ${JSON.stringify(errors.invalid)} · POST /api/specialties ${postsBefore} → ${postsAfter}${attempted.status ? ` (${attempted.status})` : ''} · toast «${toast}»`
        entry.request = requestOf(attempted)
        entry.detail = {
          title,
          errors,
          toast,
          postsBefore,
          postsAfter,
          dialogOpen: await dialog.isVisible().catch(() => false)
        }
        entry.verdict =
          painted && postsAfter === postsBefore && namesCode && namesName ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el envío vacío no deja el código y el nombre señalados en su campo, o dispara una escritura'

        await closeDialog(page)

        return entry
      }
    )

    await step(
      'create',
      'Crear una especialidad con código, nombre y descripción',
      'El POST responde 201, el toast confirma y la fila aparece con su código y el badge «Activa»',
      async entry => {
        await clickByLabel(page, 'Nueva especialidad')

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        await fillField(page, 'Código', code)
        await fillField(page, 'Nombre visible', walkName)
        await fillField(
          page,
          'Descripción',
          `${MARKER} especialidad creada por la caminata de la sesión`
        )

        const sent = await respondTo(page, { method: 'POST', path: '/api/specialties' }, () =>
          page.locator(`${DIALOG} [data-modal-submit]`).click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)
        const id = sent.body?.data?.id ?? null

        if (id) created.specialty = { id, code, name: walkName }

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})

        const listed = await search(page, walkName)
        const rows = await rowCount(page, ROWS)
        const names = await visibleTexts(page, `${ROWS} [data-row-specialty-name]`)
        const codes = await visibleTexts(page, `${ROWS} [data-row-specialty-code]`)
        const badge = (await visibleTexts(page, `${ROWS} td:nth-child(4)`))[0] ?? null

        entry.obtained = `POST ${sent.status ?? 'sin petición'} · id ${id ?? '—'} · código devuelto «${sent.body?.data?.code ?? '—'}» · toast «${toast}» · ${rows} fila con «${names[0] ?? '—'}» / «${codes[0] ?? '—'}» · badge «${badge ?? '—'}»`
        entry.request = requestOf(sent)
        entry.detail = {
          id,
          code: sent.body?.data?.code ?? null,
          toast,
          rows,
          names,
          codes,
          badge,
          searchStatus: listed.status
        }
        entry.verdict =
          sent.status === 201 &&
          id !== null &&
          rows === 1 &&
          names[0] === walkName &&
          codes[0] === code &&
          badge === 'Activa'
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el alta no responde 201 o la fila no queda visible con su código y el badge «Activa»'

        return entry
      }
    )

    await step(
      'edit-immutable-code',
      'Editar la especialidad de la caminata, cambiar el nombre e intentar cambiar el código',
      'El código no se puede editar, el PUT responde 200 y la fila conserva el código original',
      async entry => {
        const before = await api(session, 'GET', `/api/specialties/${created.specialty?.id}`)
        const originalCode = before.body?.data?.code ?? code

        await search(page, walkName)

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

        await row.locator('[data-row-action="edit"]').click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        const codeInput = page.locator(`${DIALOG} [data-field="code"] input`)
        const field = {
          disabled: await codeInput.isDisabled().catch(() => null),
          readOnly: (await codeInput.getAttribute('readonly').catch(() => null)) !== null,
          value: await codeInput.inputValue().catch(() => null),
          hint:
            (
              await dialog
                .locator('[data-field="code"] .helper-text')
                .allInnerTexts()
                .catch(() => [])
            )[0]?.trim() ?? null
        }

        let tampered = null

        if (!field.disabled && !field.readOnly) {
          tampered = await fillField(page, 'Código', `u9_tampered_${fixtures.token}`)
        }

        await fillField(page, 'Nombre visible', editedName)

        const sent = await respondTo(
          page,
          { method: 'PUT', path: new RegExp(`^/api/specialties/${created.specialty?.id}$`) },
          () => page.locator(`${DIALOG} [data-modal-submit]`).click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)
        const returned = sent.body?.data ?? null

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})

        await search(page, editedName)

        const rows = await rowCount(page, ROWS)
        const names = await visibleTexts(page, `${ROWS} [data-row-specialty-name]`)
        const codes = await visibleTexts(page, `${ROWS} [data-row-specialty-code]`)

        entry.obtained = `código editable ${!field.disabled && !field.readOnly} (disabled ${field.disabled}, readonly ${field.readOnly}) · pista «${field.hint ?? '—'}» · PUT ${sent.status ?? 'sin petición'} · nombre «${returned?.name ?? '—'}» · código devuelto «${returned?.code ?? '—'}» · ${rows} fila «${names[0] ?? '—'}» / «${codes[0] ?? '—'}» · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          originalCode,
          field,
          tampered,
          returned: returned ? { name: returned.name, code: returned.code } : null,
          toast,
          rows,
          names,
          codes
        }
        entry.verdict =
          sent.status === 200 &&
          returned?.name === editedName &&
          returned?.code === originalCode &&
          field.disabled === true &&
          rows === 1 &&
          names[0] === editedName &&
          codes[0] === originalCode
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : field.disabled !== true
              ? 'el código es editable en el formulario: la superficie ofrece una escritura que el contrato declara inmutable'
              : 'el PUT no responde 200 o la fila no conserva el código original tras el cambio de nombre'

        return entry
      }
    )

    await step(
      'deactivate',
      'Desactivar la especialidad de la caminata, comprobar el filtro «Inactivas» y volver a activarla',
      'La confirmación ejecuta el PUT is_active=false, el badge pasa a «Inactiva» y «Activar» la devuelve a la circulación',
      async entry => {
        await search(page, editedName)

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

        await row.locator('[data-row-action="toggle"]').click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const deactivated = await respondTo(
          page,
          { method: 'PUT', path: new RegExp(`^/api/specialties/${created.specialty?.id}$`) },
          () => confirmDialog(page, 'Desactivar')
        )

        await page.waitForTimeout(400)
        await settle(page)

        const badge = (await visibleTexts(page, `${ROWS} td:nth-child(4)`))[0] ?? null
        const inactiveFilter = await pickStatus(page, 'Inactivas')
        const inactiveRows = await rowCount(page, ROWS)
        const inactiveTexts = await visibleTexts(page, ROWS)
        const inactiveBadge = (await visibleTexts(page, `${ROWS} td:nth-child(4)`))[0] ?? null

        await pickStatus(page, 'Todos los estados')

        await rowNamed(page, editedName)
          .locator('[data-row-action="toggle"]')
          .click({ timeout: 8000 })

        const activateConfirm = page.locator('[role="alertdialog"]:visible').last()
        const activateText = ((await activateConfirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const activated = await respondTo(
          page,
          { method: 'PUT', path: new RegExp(`^/api/specialties/${created.specialty?.id}$`) },
          () => confirmDialog(page, 'Activar')
        )

        await page.waitForTimeout(400)
        await settle(page)

        const activeBadge = (await visibleTexts(page, `${ROWS} td:nth-child(4)`))[0] ?? null

        if (deactivated.status === 200) created.active = false

        entry.obtained = `confirmación «${confirmText}» · PUT ${deactivated.status ?? 'sin petición'} is_active=${deactivated.body?.data?.is_active ?? '—'} · badge «${badge ?? '—'}» · filtro Inactivas ${inactiveFilter.status ?? '—'} → ${inactiveRows} fila(s) ${JSON.stringify(inactiveTexts.map(text => text.slice(0, 60)))} badge «${inactiveBadge ?? '—'}» · confirmación «${activateText}» · PUT ${activated.status ?? 'sin petición'} is_active=${activated.body?.data?.is_active ?? '—'} · badge «${activeBadge ?? '—'}»`
        entry.request = requestOf(deactivated)
        entry.detail = {
          confirmText,
          deactivated: {
            status: deactivated.status,
            isActive: deactivated.body?.data?.is_active ?? null
          },
          badge,
          inactiveFilter,
          inactive: { rows: inactiveRows, badge: inactiveBadge, texts: inactiveTexts },
          activateText,
          activated: {
            status: activated.status,
            isActive: activated.body?.data?.is_active ?? null
          },
          activeBadge
        }
        entry.verdict =
          deactivated.status === 200 &&
          deactivated.body?.data?.is_active === false &&
          badge === 'Inactiva' &&
          inactiveRows === 1 &&
          inactiveBadge === 'Inactiva' &&
          activated.status === 200 &&
          activated.body?.data?.is_active === true &&
          activeBadge === 'Activa'
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la desactivación no se guarda, el filtro «Inactivas» no devuelve la fila o la reactivación no la devuelve a «Activa»'

        return entry
      }
    )

    await step(
      'no-delete',
      'Buscar un control de baja en la fila, en el formulario de edición y en la API',
      'La superficie no ofrece «Eliminar» en ningún sitio y la API responde 405 al intento de borrado',
      async entry => {
        await search(page, editedName)

        const row = rowNamed(page, editedName)
        const rowVisible = await row
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        const rowControls = rowVisible ? await buttonLabels(row) : []

        if (rowVisible) {
          await row.locator('[data-row-action="edit"]').click({ timeout: 8000 })
        }

        const dialog = page.locator(DIALOG).last()
        const dialogOpen = await dialog
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        const dialogControls = dialogOpen ? await buttonLabels(dialog) : []
        const contract = dialogOpen
          ? ((
              await dialog
                .locator('[data-delete-contract]')
                .allInnerTexts()
                .catch(() => [])
            )[0]?.trim() ?? null)
          : null

        await closeDialog(page)

        const deleteControls = [...new Set([...rowControls, ...dialogControls])].filter(label =>
          /eliminar|borrar|archivar/i.test(label)
        )

        const probe = await api(session, 'DELETE', `/api/specialties/${created.specialty?.id}`)

        entry.obtained = `acciones de la fila ${JSON.stringify(rowControls)} · acciones del formulario ${JSON.stringify(dialogControls)} · controles de baja ${JSON.stringify(deleteControls)} · aviso del contrato «${contract ?? '—'}» · DELETE /api/specialties/${created.specialty?.id} ${probe.status ?? 'sin respuesta'} «${probe.body?.message ?? '—'}»`
        entry.request = null
        entry.detail = {
          rowControls,
          dialogControls,
          deleteControls,
          contract,
          apiProbe: { method: 'DELETE', status: probe.status, message: probe.body?.message ?? null }
        }
        entry.verdict =
          rowVisible &&
          dialogOpen &&
          deleteControls.length === 0 &&
          probe.status === 405 &&
          /no se eliminan/i.test(String(contract))
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la superficie ofrece la baja que el contrato del catálogo no permite, o la API no responde 405 al intento'

        return entry
      }
    )

    await step(
      'states',
      'Medir el estado de carga, el vacío de un filtro y el error con reintento',
      'La lista muestra su placeholder de carga, un vacío que explica el filtro con su acción y un error con reintento que recarga',
      async entry => {
        const detail = { loading: null, empty: null, error: null }

        await page.route('**/api/specialties*', async route => {
          await new Promise(resolve => setTimeout(resolve, 1500))
          await route.continue().catch(() => {})
        })

        await page.goto(SURFACE, { waitUntil: 'domcontentloaded' })

        const loading = await page
          .locator('[data-specialties-loading]')
          .waitFor({ state: 'visible', timeout: 6000 })
          .then(() => true)
          .catch(() => false)

        detail.loading = {
          visible: loading,
          label: await page
            .locator('[data-specialties-loading] [aria-label]')
            .first()
            .getAttribute('aria-label')
            .catch(() => null),
          shot: loading ? await shot(page, `shots/walk/${SLUG}/${entry.id}-loading.png`) : null
        }

        await page.unroute('**/api/specialties*')
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
          markers: (await page.locator('[data-specialties-empty]').count()) > 0,
          title: /No se encontraron especialidades/.test(emptyBody),
          description: /Prueba con otros términos de búsqueda/.test(emptyBody),
          clearOffered:
            (await page.locator('main button').filter({ hasText: 'Limpiar filtros' }).count()) > 0
        }

        await clickByLabel(page, 'Limpiar filtros')
        await page.waitForTimeout(400)

        await page.route('**/api/specialties*', route =>
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
          .locator('[data-specialties-error]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const errorText = (await visibleTexts(page, '[data-specialties-error]')).join(' | ')
        const retry = page.locator('[data-specialties-retry]').first()
        const retryOffered = (await retry.count()) > 0

        detail.error = {
          painted: errorPainted,
          text: errorText,
          retryOffered,
          shot: await shot(page, `shots/walk/${SLUG}/${entry.id}-error.png`)
        }

        await page.unroute('**/api/specialties*')

        if (retryOffered) {
          await respondTo(page, { method: 'GET', path: '/api/specialties' }, () =>
            retry.click({ timeout: 8000 })
          )
          await page.waitForTimeout(500)
        }

        const rowsAfterRetry = await rowCount(page, ROWS)

        detail.error.rowsAfterRetry = rowsAfterRetry

        entry.obtained = `carga: ${detail.loading.visible ? `muestra «${detail.loading.label ?? 'Cargando'}»` : 'sin placeholder observable'} · vacío: ${detail.empty.title && detail.empty.description ? '«No se encontraron especialidades» con su explicación' : 'sin texto de vacío'}${detail.empty.clearOffered ? ' y «Limpiar filtros»' : ' y sin acción'} · error 500: ${detail.error.painted ? `bloque «${detail.error.text.slice(0, 90)}»` : 'sin bloque de error'} · «Reintentar» ${detail.error.retryOffered ? 'ofrecido' : 'ausente'} · tras reintentar ${rowsAfterRetry} fila(s)`
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

    await step(
      'residue',
      'Desactivar la especialidad de la fixture y la de la caminata antes de cerrar',
      'Las dos filas quedan «Inactiva» porque el catálogo no tiene puerta de borrado (405)',
      async entry => {
        const { fixture } = fixtures.specialties
        const written = []

        await search(page, fixture.name)

        const fixtureRow = rowNamed(page, fixture.name)
        const fixtureVisible = await fixtureRow
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (fixtureVisible) {
          await fixtureRow.locator('[data-row-action="toggle"]').click({ timeout: 8000 })

          const deactivated = await respondTo(
            page,
            { method: 'PUT', path: new RegExp(`^/api/specialties/${fixture.id}$`) },
            () => confirmDialog(page, 'Desactivar')
          )

          await page.waitForTimeout(300)
          await settle(page)

          written.push({
            row: 'fixture',
            id: fixture.id,
            code: fixture.code,
            status: deactivated.status,
            isActive: deactivated.body?.data?.is_active ?? null,
            badge: (await visibleTexts(page, `${ROWS} td:nth-child(4)`))[0] ?? null
          })
        }

        await search(page, editedName)

        const walkRow = rowNamed(page, editedName)
        const walkVisible = await walkRow
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (walkVisible) {
          await walkRow.locator('[data-row-action="toggle"]').click({ timeout: 8000 })

          const deactivated = await respondTo(
            page,
            { method: 'PUT', path: new RegExp(`^/api/specialties/${created.specialty?.id}$`) },
            () => confirmDialog(page, 'Desactivar')
          )

          await page.waitForTimeout(300)
          await settle(page)

          written.push({
            row: 'walk',
            id: created.specialty?.id,
            code,
            status: deactivated.status,
            isActive: deactivated.body?.data?.is_active ?? null,
            badge: (await visibleTexts(page, `${ROWS} td:nth-child(4)`))[0] ?? null
          })
        }

        residues.walk = created.specialty
        evidence.residues = {
          declared: true,
          reason:
            'el catálogo de especialidades no tiene DELETE (405): las dos filas quedan desactivadas y declaradas',
          fixture: { id: fixture.id, code: fixture.code },
          walk: created.specialty,
          written
        }

        entry.obtained = `${written.length} fila(s) desactivadas ${JSON.stringify(written.map(row => `${row.row} #${row.id} ${row.code} → PUT ${row.status ?? '—'} is_active=${row.isActive ?? '—'} badge «${row.badge ?? '—'}»`))}`
        entry.request =
          written.length > 0
            ? {
                method: 'PUT',
                path: `/api/specialties/${written.at(-1).id}`,
                status: written.at(-1).status
              }
            : null
        entry.detail = evidence.residues
        entry.verdict =
          written.length === 2 &&
          written.every(
            row => row.status === 200 && row.isActive === false && row.badge === 'Inactiva'
          )
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          'residuo declarado: la especialidad de la fixture y la de la caminata no se pueden borrar (DELETE 405); quedan desactivadas'

        return entry
      }
    )
  } finally {
    // A step that died before its own deactivation leaves the row active: the
    // session retires it through the API and records the outcome.
    const stillActive = []

    for (const target of [
      {
        kind: 'fixture',
        id: fixtures.specialties?.fixture?.id,
        code: fixtures.specialties?.fixture?.code
      },
      { kind: 'walk', id: created.specialty?.id, code }
    ]) {
      if (!target.id) continue

      const current = await api(session, 'GET', `/api/specialties/${target.id}`)
      if (current.body?.data?.is_active === false) continue

      const response = await api(session, 'PUT', `/api/specialties/${target.id}`, {
        is_active: false
      })

      stillActive.push({ ...target, status: response.status })
      evidence.cleanup.push({
        type: `specialty:${target.kind}`,
        id: target.id,
        status: response.status,
        deactivated: response.status === 200,
        message: response.body?.message ?? null
      })
    }

    evidence.cleanup.push({
      type: 'specialty:delete-door',
      id: created.specialty?.id ?? fixtures.specialties?.fixture?.id ?? null,
      status: 405,
      note: 'el catálogo no tiene DELETE: la fila se retira desactivándola'
    })

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
      failure => failure.status === 500 && /\/api\/specialties/.test(failure.url ?? ''),
      'el paso `states` provoca un 500 para medir el estado de error de la lista'
    )

    evidence.fixtures = { ...evidence.fixtures, ...fixtureSummary(fixtures) }
    evidence.cleanup.push(...stillActive.map(row => ({ ...row, phase: 'finally' })))
    evidence.counts = walkCounts(evidence.steps)

    audit('walk-specialties', evidence)
  }

  expect(evidence.pageErrors, 'page errors during the specialties walkthrough').toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    'declared steps of the specialties walkthrough'
  ).toEqual([
    '01-list',
    '02-filter',
    '03-create-empty',
    '04-create',
    '05-edit-immutable-code',
    '06-deactivate',
    '07-no-delete',
    '08-states',
    '09-residue'
  ])
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    'every step of the specialties walkthrough has its capture'
  ).toBe(true)
})
