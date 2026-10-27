// Plan #13 (issue #372, U9) — layer 4: the branches ABM, action by action.
//
// `/settings/branches` filters a copy in the browser: neither the search nor the
// status selector issues a request, so the walkthrough records the absence of a
// list call and measures the filter against the rows on screen. The API does
// allow `DELETE` for a branch nothing references, and the surface must not offer
// it: the walk probes that door on the branch it created (the probe is also the
// cleanup) and records every control it looked at, including the detail page.
//
// The detail surface's loading placeholder is measured by delaying its payload:
// #372 S1 recorded that `BranchDetailPage` had no placeholder at `8b72d37`, so
// the observation is part of the evidence.
//
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

const SURFACE = '/settings/branches'
const SLUG = 'branches'
const MARKER = 'AUDIT-U9'
const ROWS = 'main .data-table-row'
const DIALOG = '[role="dialog"]:visible'
const SEARCH = 'main input[placeholder^="Buscar por código"]'
const STATUS = '[data-status-filter="status"] > button'

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

/** Fills the client-side search; the page issues no request for it. */
const search = async (page, value) => {
  const filled = await fillField(page, 'Buscar por código, nombre, ciudad o contacto', value)
  await settleLayout(page)
  await page.waitForTimeout(120)

  return filled
}

const pickStatus = async (page, label) => {
  const picked = await pickOption(page, STATUS, label)
  await settleLayout(page)
  await page.waitForTimeout(120)

  return picked
}

const statusTrigger = page =>
  page
    .locator(STATUS)
    .innerText()
    .then(text => text.trim().replace(/\s+/g, ' '))
    .catch(() => null)

test('@U9 walks sucursales', async ({ page }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const session = await login('administrador')
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)

  const walkName = `${MARKER}-${fixtures.token} sucursal de la caminata`
  const editedName = `${walkName} (editada)`
  const walkCode = `W9${fixtures.token}`

  const created = { branch: null, deleted: false }

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks sucursales',
    role: 'administrador',
    steps: [],
    counts: null,
    fixtures: { marker: fixtures.marker, ok: fixtures.ok, token: fixtures.token },
    created: { name: walkName, code: walkCode },
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
      'Abrir /settings/branches como administración',
      'La lista pinta sus filas, las cinco cabeceras del dominio, el estado de cada sede y los cuatro KPI con lo que cuenta cada uno',
      async entry => {
        const painted = await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        const rows = await rowCount(page, ROWS)
        const headers = await tableHeaders(page)
        const statuses = await visibleTexts(page, 'main [data-branch-status]')
        const cards = await kpiCards(page)
        const primitive = await tablePrimitive(page)
        const caption = (await visibleTexts(page, '[data-hero-caption]'))[0] ?? null
        const count = (await visibleTexts(page, '[data-filter-count]'))[0] ?? null
        const wanted = ['código', 'sucursal', 'ciudad', 'contacto', 'estado']
        const headersNamed = wanted.every(label =>
          headers.some(header => header.toLowerCase() === label)
        )
        const badged =
          statuses.length === rows && statuses.every(label => /^(Activa|Inactiva)$/.test(label))
        const total = cards.find(card => card.key === 'total')
        const kpisComplete =
          cards.length === 4 && cards.every(card => card.value !== null && card.caption)

        entry.obtained = `${rows} filas · cabeceras ${headers.length} ${JSON.stringify(headers)} · estados ${JSON.stringify([...new Set(statuses)])} · KPI ${JSON.stringify(cards.map(card => `${card.label} ${card.value} (${card.caption})`))} · «${count ?? '—'}» · «${caption ?? '—'}» · lista: ${primitive.primitive} (data-table-scroll ${primitive.dataTables}, affordance de scroll ${primitive.scrollAffordance})`
        entry.request = null
        entry.detail = {
          painted,
          rows,
          headers,
          statuses,
          cards,
          primitive,
          caption,
          count,
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
      'Buscar la sucursal de la fixture, recorrer el filtro de estado y limpiar los filtros',
      'La búsqueda y el estado filtran en el navegador sin pedir la lista y limpiar devuelve el total',
      async entry => {
        const fixture = fixtures.branches.target
        const before = await rowCount(page, ROWS)
        const requestsBefore = requests.filter(
          row => row.method === 'GET' && row.path === '/api/branches'
        ).length

        await search(page, fixture.name)

        const filtered = await rowCount(page, ROWS)
        const filteredTexts = await visibleTexts(page, ROWS)
        const hitsFixture = Boolean(filteredTexts[0]?.includes(fixture.name))
        const countFiltered = (await visibleTexts(page, '[data-filter-count]'))[0] ?? null

        await search(page, '')
        await page.waitForTimeout(120)
        const afterClear = await rowCount(page, ROWS)

        const active = await pickStatus(page, 'Activas')
        const activeRows = await rowCount(page, ROWS)
        const activeStatuses = await visibleTexts(page, 'main [data-branch-status]')

        const inactive = await pickStatus(page, 'Inactivas')
        const inactiveRows = await rowCount(page, ROWS)
        const inactiveStatuses = await visibleTexts(page, 'main [data-branch-status]')
        const inactiveEmpty = inactiveRows === 0 ? await listText(page) : null

        const all = await pickStatus(page, 'Todas')
        const allRows = await rowCount(page, ROWS)

        await search(page, 'ZZZ-AUDIT-U9-SIN-RESULTADOS')
        await page.waitForTimeout(120)

        const noMatchBody = await listText(page)
        const noMatchClear = await page
          .locator('main button')
          .filter({ hasText: 'Limpiar filtros' })
          .count()

        await clickByLabel(page, 'Limpiar filtros')
        await page.waitForTimeout(200)

        const afterNoMatch = await rowCount(page, ROWS)
        const searchValue = await page
          .locator(SEARCH)
          .inputValue()
          .catch(() => null)
        const statusAfterClear = await statusTrigger(page)
        const requestsAfter = requests.filter(
          row => row.method === 'GET' && row.path === '/api/branches'
        ).length

        entry.obtained = `«${fixture.name}» → ${filtered} fila (${hitsFixture ? 'la buscada' : 'otra'}) · «${countFiltered ?? '—'}» · limpiar → ${afterClear} filas · Activas → ${activeRows} filas ${JSON.stringify([...new Set(activeStatuses)])} · Inactivas → ${inactiveRows} filas ${JSON.stringify([...new Set(inactiveStatuses)])}${inactiveEmpty ? ` («${inactiveEmpty.slice(0, 80)}»)` : ''} · Todas → ${allRows} filas · sin coincidencias «${noMatchBody.slice(0, 120)}» con «Limpiar filtros» ${noMatchClear} · tras limpiar ${afterNoMatch} filas, búsqueda «${searchValue ?? ''}», estado «${statusAfterClear ?? '—'}» · GET /api/branches durante el paso ${requestsAfter - requestsBefore}`
        entry.request = null
        entry.detail = {
          before,
          filtered,
          hitsFixture,
          countFiltered,
          afterClear,
          active: { picked: active, rows: activeRows, statuses: activeStatuses },
          inactive: {
            picked: inactive,
            rows: inactiveRows,
            statuses: inactiveStatuses,
            empty: inactiveEmpty
          },
          all: { picked: all, rows: allRows },
          noMatch: { body: noMatchBody, clearOffered: noMatchClear > 0 },
          afterNoMatch,
          searchValue,
          statusAfterClear,
          requestsDuringFilter: requestsAfter - requestsBefore
        }
        const populationOk =
          filtered === 1 &&
          hitsFixture &&
          afterClear === before &&
          activeRows > 0 &&
          activeStatuses.every(label => label === 'Activa') &&
          inactiveStatuses.every(label => label === 'Inactiva') &&
          allRows === before &&
          noMatchClear > 0 &&
          afterNoMatch === before &&
          searchValue === '' &&
          requestsAfter === requestsBefore
        const emptyStatusOk = statusAfterClear === 'Todas'

        entry.verdict = populationOk ? (emptyStatusOk ? 'OK' : 'CONFUSO') : 'DEFECTO'
        entry.notes = populationOk
          ? emptyStatusOk
            ? ''
            : `la lista vuelve a sus ${afterNoMatch} filas con la búsqueda vacía, pero el selector de estado pinta «${statusAfterClear ?? '—'}» en vez de «Todas»: el control no deja leer qué población se está viendo`
          : 'el filtro no deja la población que promete, o la superficie consulta la lista para filtrar en vez de hacerlo sobre los datos ya cargados'

        return entry
      }
    )

    await step(
      'create-empty',
      'Abrir «Nueva sucursal» y pulsar «Crear» con el formulario vacío',
      'El formulario marca el código, el nombre y la ciudad en su campo y no dispara ningún POST',
      async entry => {
        const opened = await clickByLabel(page, 'Nueva sucursal')

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
          row => row.method === 'POST' && row.path === '/api/branches'
        ).length

        const attempted = await respondTo(
          page,
          { method: 'POST', path: '/api/branches' },
          () => page.locator(`${DIALOG} [data-modal-submit]`).click({ timeout: 8000 }),
          4000
        )

        await page.waitForTimeout(300)

        const errors = await fieldErrors(page)
        const toast = await toastText(page)
        const postsAfter = requests.filter(
          row => row.method === 'POST' && row.path === '/api/branches'
        ).length
        const required = errors.messages.filter(message => /requerido/i.test(message)).length

        entry.obtained = `diálogo «${title}» abierto ${opened.ok} · mensajes ${JSON.stringify(errors.messages)} · campos inválidos ${JSON.stringify(errors.invalid)} · POST /api/branches ${postsBefore} → ${postsAfter}${attempted.status ? ` (${attempted.status})` : ''} · toast «${toast}»`
        entry.request = requestOf(attempted)
        entry.detail = {
          title,
          errors,
          toast,
          postsBefore,
          postsAfter,
          requiredMessages: required,
          dialogOpen: await dialog.isVisible().catch(() => false)
        }
        entry.verdict = painted && postsAfter === postsBefore && required >= 3 ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el envío vacío no señala el código, el nombre y la ciudad en su campo, o dispara una escritura'

        await closeDialog(page)

        return entry
      }
    )

    await step(
      'create',
      'Crear una sucursal con código, nombre, dirección y ciudad',
      'El POST responde 201 con los cuatro valores, el toast confirma y la fila aparece con el badge «Activa»',
      async entry => {
        await clickByLabel(page, 'Nueva sucursal')

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        await fillField(page, 'Código', walkCode)
        await fillField(page, 'Nombre de la sucursal', walkName)
        await fillField(page, 'Dirección', 'Av. Auditoría 999')
        await fillField(page, 'Ciudad', 'Lima')

        const sent = await respondTo(page, { method: 'POST', path: '/api/branches' }, () =>
          page.locator(`${DIALOG} [data-modal-submit]`).click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)
        const data = sent.body?.data ?? null
        const id = data?.id ?? null

        if (id) created.branch = { id, code: walkCode, name: walkName }

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})

        await search(page, walkName)

        const rows = await rowCount(page, ROWS)
        const rowText = rows > 0 ? (await visibleTexts(page, ROWS))[0] : null
        const badge = (await visibleTexts(page, 'main [data-branch-status]'))[0] ?? null

        entry.obtained = `POST ${sent.status ?? 'sin petición'} · id ${id ?? '—'} · código «${data?.code ?? '—'}» · dirección «${data?.address ?? '—'}» · ciudad «${data?.city ?? '—'}» · toast «${toast}» · ${rows} fila ${rowText?.includes(walkName) ? 'con el nombre nuevo' : 'sin el nombre'} · badge «${badge ?? '—'}»`
        entry.request = requestOf(sent)
        entry.detail = {
          id,
          data: data
            ? { code: data.code, name: data.name, address: data.address, city: data.city }
            : null,
          toast,
          rows,
          rowText,
          badge
        }
        entry.verdict =
          sent.status === 201 &&
          id !== null &&
          data?.code === walkCode &&
          data?.name === walkName &&
          data?.address === 'Av. Auditoría 999' &&
          data?.city === 'Lima' &&
          rows === 1 &&
          badge === 'Activa'
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el alta no responde 201 con los cuatro campos o la fila no queda visible con el badge «Activa»'

        return entry
      }
    )

    await step(
      'edit',
      'Editar la sucursal de la caminata y cambiarle el nombre',
      'El código no se puede editar, el PUT responde 200 y la fila muestra el nombre nuevo',
      async entry => {
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

        await fillField(page, 'Nombre de la sucursal', editedName)

        const sent = await respondTo(
          page,
          { method: 'PUT', path: new RegExp(`^/api/branches/${created.branch?.id}$`) },
          () => page.locator(`${DIALOG} [data-modal-submit]`).click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)
        const data = sent.body?.data ?? null

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})

        await search(page, editedName)

        const rows = await rowCount(page, ROWS)
        const rowText = rows > 0 ? (await visibleTexts(page, ROWS))[0] : null

        entry.obtained = `código editable ${!field.disabled && !field.readOnly} (disabled ${field.disabled}, readonly ${field.readOnly}) · valor «${field.value ?? '—'}» · pista «${field.hint ?? '—'}» · PUT ${sent.status ?? 'sin petición'} · nombre devuelto «${data?.name ?? '—'}» · código devuelto «${data?.code ?? '—'}» · ${rows} fila ${rowText?.includes(editedName) ? 'con el nombre editado' : 'sin el nombre editado'} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          field,
          returned: data ? { name: data.name, code: data.code } : null,
          toast,
          rows,
          rowText
        }
        entry.verdict =
          sent.status === 200 &&
          data?.name === editedName &&
          data?.code === walkCode &&
          field.disabled === true &&
          rows === 1 &&
          Boolean(rowText?.includes(editedName))
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : field.disabled !== true
              ? 'el código es editable después de crear la sede'
              : 'el PUT no responde 200 o la fila no refleja el nombre editado'

        return entry
      }
    )

    await step(
      'deactivate',
      'Desactivar la sucursal de la caminata desde la acción de su fila',
      'La confirmación ejecuta el PUT is_active=false, el badge pasa a «Inactiva» y el filtro la muestra',
      async entry => {
        await search(page, editedName)
        await pickStatus(page, 'Todas')

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

        const sent = await respondTo(
          page,
          { method: 'PUT', path: new RegExp(`^/api/branches/${created.branch?.id}$`) },
          () => confirmDialog(page, 'Desactivar')
        )

        await page.waitForTimeout(400)
        await settleLayout(page)

        const badge = (await visibleTexts(page, 'main [data-branch-status]'))[0] ?? null
        const inactive = await pickStatus(page, 'Inactivas')
        const inactiveRows = await rowCount(page, ROWS)
        const inactiveTexts = await visibleTexts(page, ROWS)
        const inactiveBadge = (await visibleTexts(page, 'main [data-branch-status]'))[0] ?? null

        await pickStatus(page, 'Todas')

        entry.obtained = `confirmación «${confirmText}» · PUT ${sent.status ?? 'sin petición'} is_active=${sent.body?.data?.is_active ?? '—'} · badge «${badge ?? '—'}» · filtro Inactivas ${inactive.status ?? '—'} → ${inactiveRows} fila(s) ${JSON.stringify(inactiveTexts.map(text => text.slice(0, 60)))} badge «${inactiveBadge ?? '—'}»`
        entry.request = requestOf(sent)
        entry.detail = {
          confirmText,
          status: sent.status,
          isActive: sent.body?.data?.is_active ?? null,
          badge,
          inactive: {
            picked: inactive,
            rows: inactiveRows,
            texts: inactiveTexts,
            badge: inactiveBadge
          }
        }
        entry.verdict =
          sent.status === 200 &&
          sent.body?.data?.is_active === false &&
          badge === 'Inactiva' &&
          inactiveRows === 1 &&
          inactiveBadge === 'Inactiva'
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la desactivación no se guarda o el filtro «Inactivas» no devuelve la fila con su badge'

        return entry
      }
    )

    await step(
      'activate',
      'Activar la sucursal desactivada',
      'La confirmación ejecuta el PUT is_active=true y la fila vuelve al badge «Activa»',
      async entry => {
        await search(page, editedName)
        await pickStatus(page, 'Todas')

        const row = rowNamed(page, editedName)
        const visible = await row
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!visible) {
          entry.obtained = `la fila «${editedName}» no está visible para activarla`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'no se pudo abrir la fila que la caminata creó'

          return entry
        }

        await row.locator('[data-row-action="toggle"]').click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const sent = await respondTo(
          page,
          { method: 'PUT', path: new RegExp(`^/api/branches/${created.branch?.id}$`) },
          () => confirmDialog(page, 'Activar')
        )

        await page.waitForTimeout(400)
        await settleLayout(page)

        const badge = (await visibleTexts(page, 'main [data-branch-status]'))[0] ?? null

        entry.obtained = `confirmación «${confirmText}» · PUT ${sent.status ?? 'sin petición'} is_active=${sent.body?.data?.is_active ?? '—'} · badge «${badge ?? '—'}»`
        entry.request = requestOf(sent)
        entry.detail = {
          confirmText,
          status: sent.status,
          isActive: sent.body?.data?.is_active ?? null,
          badge
        }
        entry.verdict =
          sent.status === 200 && sent.body?.data?.is_active === true && badge === 'Activa'
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la reactivación no se guarda o la fila no vuelve a «Activa»'

        return entry
      }
    )

    await step(
      'no-delete',
      'Buscar un control de baja en la lista, en la confirmación de su fila, en el formulario y en el detalle',
      'Ninguna de las cuatro superficies ofrece «Eliminar» aunque la API acepte el borrado de una sede sin referencias',
      async entry => {
        const fixture = fixtures.branches.target

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

        await closeDialog(page)

        await search(page, fixture.name)

        const detailOpened = await rowNamed(page, fixture.name)
          .locator('[data-row-action="detail"]')
          .click({ timeout: 8000 })
          .then(() => true)
          .catch(() => false)

        await page
          .waitForURL(url => url.pathname.endsWith(`/settings/branches/${fixture.id}`), {
            timeout: 12000
          })
          .catch(() => {})
        await settle(page)

        const detailControls = detailOpened ? await buttonLabels(page.locator('main')) : []

        await clickByLabel(page, 'Volver')
        await settle(page)

        const deleteControls = [
          ...new Set([...rowControls, ...dialogControls, ...detailControls])
        ].filter(label => /eliminar|borrar/i.test(label))

        const probe = await api(session, 'DELETE', `/api/branches/${created.branch?.id}`)

        if (probe.status === 200) created.deleted = true

        entry.obtained = `acciones de la fila ${JSON.stringify(rowControls)} · acciones del formulario ${JSON.stringify(dialogControls)} · acciones del detalle ${JSON.stringify(detailControls)} · controles de baja ${JSON.stringify(deleteControls)} · DELETE /api/branches/${created.branch?.id} ${probe.status ?? 'sin respuesta'} «${probe.body?.meta?.message ?? probe.body?.message ?? '—'}»`
        entry.request = null
        entry.detail = {
          rowControls,
          dialogControls,
          detailControls,
          deleteControls,
          apiProbe: {
            method: 'DELETE',
            status: probe.status,
            message: probe.body?.meta?.message ?? probe.body?.message ?? null
          }
        }
        entry.verdict =
          rowVisible &&
          dialogOpen &&
          detailOpened &&
          deleteControls.length === 0 &&
          probe.status === 200
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'alguna superficie ofrece una baja que el ABM no debe publicar, o la API no acepta el borrado de una sede sin referencias'

        return entry
      }
    )

    await step(
      'detail',
      'Abrir «Ver» de la sucursal de la fixture y medir su placeholder de carga y sus secciones',
      'La ficha muestra un placeholder antes del payload, las tres secciones y la acción «Editar»',
      async entry => {
        const fixture = fixtures.branches.target

        await search(page, fixture.name)

        await page.route(`**/api/branches/${fixture.id}`, async route => {
          await new Promise(resolve => setTimeout(resolve, 1500))
          await route.continue().catch(() => {})
        })

        const opened = await rowNamed(page, fixture.name)
          .locator('[data-row-action="detail"]')
          .click({ timeout: 8000 })
          .then(() => true)
          .catch(() => false)

        const placeholder = await page
          .locator('[data-branch-detail-loading]')
          .waitFor({ state: 'visible', timeout: 5000 })
          .then(() => true)
          .catch(() => false)
        const placeholderLabel =
          (await page
            .locator('[data-branch-detail-loading] [aria-label]')
            .first()
            .getAttribute('aria-label')
            .catch(() => null)) ?? null
        const placeholderShot = placeholder
          ? await shot(page, `shots/walk/${SLUG}/${entry.id}-loading.png`)
          : null

        await page
          .locator('[data-branch-detail-hero]')
          .waitFor({ state: 'visible', timeout: 15000 })
          .catch(() => {})
        await page.unroute(`**/api/branches/${fixture.id}`)
        await settle(page)

        const path = new URL(page.url()).pathname
        const title =
          (await visibleTexts(page, '[data-branch-detail-hero] [data-hero-title]'))[0] ?? null
        const code =
          (await visibleTexts(page, '[data-branch-detail-hero] [data-hero-caption]'))[0] ?? null
        const statuses = await visibleTexts(page, 'main [data-branch-status]')
        const sections = await visibleTexts(page, '[data-branch-summary] h3')
        const fields = await visibleTexts(page, '[data-detail-field]')
        const edit = page.locator('[data-branch-detail-edit]').first()
        const editVisible = await edit.isVisible().catch(() => false)
        const editEnabled = await edit.isEnabled().catch(() => false)
        const back = page.locator('[data-detail-back]').first()
        const backVisible = await back.isVisible().catch(() => false)
        const call = lastCall(requests, 'GET', `/api/branches/${fixture.id}`)

        entry.obtained = `placeholder «${placeholderLabel ?? 'ausente'}» visible ${placeholder} · URL ${path} · título «${title ?? '—'}» · «${code ?? '—'}» · estado «${statuses[0] ?? '—'}» · secciones ${JSON.stringify(sections)} · ${fields.length} campos · «Editar» visible ${editVisible} habilitado ${editEnabled} · «Volver» visible ${backVisible} · GET ${call?.path ?? '—'} ${call?.status ?? '—'}`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = {
          opened,
          placeholder,
          placeholderLabel,
          placeholderShot,
          path,
          title,
          code,
          statuses,
          sections,
          fields,
          edit: { visible: editVisible, enabled: editEnabled },
          back: { visible: backVisible }
        }
        entry.verdict =
          placeholder &&
          path === `/settings/branches/${fixture.id}` &&
          title === fixture.name &&
          sections.map(section => section.toLowerCase()).join('|') ===
            'ubicación|contacto|registro y operación' &&
          fields.length >= 10 &&
          editVisible &&
          editEnabled &&
          backVisible &&
          call?.status === 200
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la ficha no muestra su placeholder de carga, sus tres secciones o la acción «Editar»'

        return entry
      }
    )

    await step(
      'back',
      'Volver a la lista con el botón del navegador',
      'La lista reaparece en /settings/branches con los filtros limpios',
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
        const status = await statusTrigger(page)
        const rows = await rowCount(page, ROWS)

        entry.obtained = `URL ${path} · búsqueda «${searchValue ?? ''}» · estado «${status ?? '—'}» · ${rows} filas`
        entry.detail = { path, searchValue, status, rows }
        const clean = path === SURFACE && searchValue === '' && rows > 0
        entry.verdict = clean ? (status === 'Todas' ? 'OK' : 'CONFUSO') : 'DEFECTO'
        entry.notes = clean
          ? status === 'Todas'
            ? ''
            : 'la lista vuelve con el filtro «Todas» aplicado, pero su selector pinta «Seleccionar...»: el control no deja leer el estado con el que se está viendo la lista'
          : 'el retorno del navegador no deja la lista con sus filtros limpios'

        return entry
      }
    )

    await step(
      'states',
      'Medir el estado de carga, el vacío de un filtro y el error con reintento',
      'La lista muestra su placeholder de carga, un vacío que explica el filtro con su acción y un error con reintento que recarga',
      async entry => {
        const detail = { loading: null, empty: null, error: null }

        await page.route('**/api/branches*', async route => {
          await new Promise(resolve => setTimeout(resolve, 1500))
          await route.continue().catch(() => {})
        })

        await page.goto(SURFACE, { waitUntil: 'domcontentloaded' })

        const loading = await page
          .locator('[data-branches-loading]')
          .waitFor({ state: 'visible', timeout: 6000 })
          .then(() => true)
          .catch(() => false)

        detail.loading = {
          visible: loading,
          label: await page
            .locator('[data-branches-loading] [aria-label]')
            .first()
            .getAttribute('aria-label')
            .catch(() => null),
          shot: loading ? await shot(page, `shots/walk/${SLUG}/${entry.id}-loading.png`) : null
        }

        await page.unroute('**/api/branches*')
        await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 15000 })
          .catch(() => {})

        await search(page, 'ZZZ-AUDIT-U9-SIN-RESULTADOS')
        await page.waitForTimeout(300)

        const emptyBody = await listText(page)
        const emptyClear = await page
          .locator('main button')
          .filter({ hasText: 'Limpiar filtros' })
          .count()

        detail.empty = {
          rows: await rowCount(page, ROWS),
          markers: (await page.locator('[data-branches-empty]').count()) > 0,
          title: /No se encontraron sucursales/.test(emptyBody),
          description: /Ninguna sucursal coincide con ZZZ-AUDIT-U9-SIN-RESULTADOS/.test(emptyBody),
          clearOffered: emptyClear > 0
        }

        await clickByLabel(page, 'Limpiar filtros')
        await page.waitForTimeout(300)

        await page.route('**/api/branches*', route =>
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
          .locator('[data-branches-error]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const errorText = (await visibleTexts(page, '[data-branches-error]')).join(' | ')
        const retry = page.locator('[data-retry-branches]').first()
        const retryOffered = (await retry.count()) > 0

        detail.error = {
          painted: errorPainted,
          text: errorText,
          retryOffered,
          shot: await shot(page, `shots/walk/${SLUG}/${entry.id}-error.png`)
        }

        await page.unroute('**/api/branches*')

        if (retryOffered) {
          await respondTo(page, { method: 'GET', path: '/api/branches' }, () =>
            retry.click({ timeout: 8000 })
          )
          await page.waitForTimeout(500)
        }

        const rowsAfterRetry = await rowCount(page, ROWS)

        detail.error.rowsAfterRetry = rowsAfterRetry

        entry.obtained = `carga: ${detail.loading.visible ? `muestra «${detail.loading.label ?? 'Cargando'}»` : 'sin placeholder observable'} · vacío: ${detail.empty.title && detail.empty.description ? '«No se encontraron sucursales» con su explicación' : 'sin texto de vacío'}${detail.empty.clearOffered ? ' y «Limpiar filtros»' : ' y sin acción'} · error 500: ${detail.error.painted ? `bloque «${detail.error.text.slice(0, 90)}»` : 'sin bloque de error'} · «Reintentar» ${detail.error.retryOffered ? 'ofrecido' : 'ausente'} · tras reintentar ${rowsAfterRetry} fila(s)`
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
    if (created.branch && !created.deleted) {
      const removed = await api(session, 'DELETE', `/api/branches/${created.branch.id}`)

      if (removed.status === 200) created.deleted = true

      evidence.cleanup.push({
        type: 'branch',
        id: created.branch.id,
        status: removed.status,
        deleted: removed.status === 200,
        message: removed.body?.meta?.message ?? removed.body?.message ?? null
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
      failure => failure.status === 500 && /\/api\/branches/.test(failure.url ?? ''),
      'el paso `states` provoca un 500 para medir el estado de error de la lista'
    )

    evidence.fixtures = { ...evidence.fixtures, ...fixtureSummary(fixtures) }
    evidence.counts = walkCounts(evidence.steps)

    audit('walk-branches', evidence)
  }

  expect(evidence.pageErrors, 'page errors during the branches walkthrough').toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    'declared steps of the branches walkthrough'
  ).toEqual([
    '01-list',
    '02-filter',
    '03-create-empty',
    '04-create',
    '05-edit',
    '06-deactivate',
    '07-activate',
    '08-no-delete',
    '09-detail',
    '10-back',
    '11-states'
  ])
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    'every step of the branches walkthrough has its capture'
  ).toBe(true)
})
