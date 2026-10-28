// Plan #13 (issue #372, U9) — layer 4: the payment methods ABM, action by action.
//
// The list declares seven columns (Método, Pasarela, Comisión, Familia,
// Referencia, Tipo, Estado) and its KPI strip describes the unfiltered
// directory, so a server filter that matches no rows cannot drag the counters
// to zero. The family filter is client-side and the `requires_reference` flag
// is published by the Referencia column. The system methods cannot be deleted:
// `canDelete` is `!is_system && transactions_count === 0`, and the surface hides
// the button while the form explains the rule.
//
// The walk creates its own method, changes its family from card to wallet,
// checks a system row offers no delete and finally deletes the row it created
// through the surface's own confirmation.
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
  readSelectOptions,
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

const SURFACE = '/settings/payment-methods'
const SLUG = 'payment-methods'
const MARKER = 'AUDIT-U9'
const ROWS = 'main .data-table-row'
const DIALOG = '[role="dialog"]:visible'
const SEARCH = '[data-pm-search] input'

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

const referenceCells = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('main .data-table-row [data-row-reference]')).map(
      node => ({
        text: (node.innerText ?? '').trim(),
        requires: node.getAttribute('data-requires-reference')
      })
    )
  )

const waitKpiTotal = (page, expected, timeout = 8000) =>
  page
    .waitForFunction(
      value => {
        const node = document.querySelector('[data-kpi-value="total"]')
        return node ? (node.innerText ?? '').trim() === String(value) : false
      },
      expected,
      { timeout, polling: 120 }
    )
    .then(() => true)
    .catch(() => false)

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
        if (
          response.request().method() !== 'GET' ||
          pathOf(response.url()) !== '/api/payment-methods'
        )
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

const pickStatus = async (page, label) => {
  const pending = page
    .waitForResponse(
      response =>
        response.request().method() === 'GET' && pathOf(response.url()) === '/api/payment-methods',
      { timeout: 8000 }
    )
    .catch(() => null)

  const picked = await pickOption(page, '[data-status-filter] button', label)
  const response = await pending

  await settle(page)

  return { picked, status: response?.status() ?? null }
}

test('@U9 walks métodos de pago', async ({ page }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const session = await login('administrador')
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)

  const walkName = `${MARKER}-${fixtures.token} método de la caminata`
  const editedName = `${walkName} (editado)`
  const code = `u9pm_${fixtures.token}`

  const created = { method: null, deleted: false }

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks métodos de pago',
    role: 'administrador',
    steps: [],
    counts: null,
    fixtures: { marker: fixtures.marker, ok: fixtures.ok, token: fixtures.token },
    created: { name: walkName, code },
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
      'Abrir /settings/payment-methods como administración',
      'La lista pinta sus filas, los cuatro KPI, las cabeceras de método/pasarela/comisión/familia/referencia/tipo y publica la referencia que cada método exige',
      async entry => {
        const painted = await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        const rows = await rowCount(page, ROWS)
        const headers = await tableHeaders(page)
        const cards = await kpiCards(page)
        const primitive = await tablePrimitive(page)
        const rowTexts = await visibleTexts(page, ROWS)
        const caption = (await visibleTexts(page, '[data-hero-caption]'))[0] ?? null
        const call = lastCall(requests, 'GET', '/api/payment-methods')
        const familyHeader = headers.some(header => /familia/i.test(header))
        const referenceHeader = headers.some(header => /referencia/i.test(header))
        const referenceValues = await referenceCells(page)
        const referencePublished =
          referenceValues.length === rows &&
          referenceValues.every(
            cell =>
              /^(Sí|No)$/.test(cell.text) &&
              (cell.text === 'Sí' ? cell.requires === 'true' : cell.requires === 'false')
          )
        const scopeHeader = headers.some(header => /^(tipo|ámbito)$/i.test(header))
        const familyMarks = rowTexts.filter(text =>
          /(Efectivo|Tarjeta|Transferencia|Billetera digital)/.test(text)
        ).length
        const scopeMarks = rowTexts.filter(text => /(Sistema|Personalizado)/.test(text)).length
        const detailLinks = await page.locator('main a[href*="/settings/payment-methods/"]').count()
        const total = cards.find(card => card.key === 'total')
        const kpisComplete =
          cards.length === 4 && cards.every(card => card.value !== null && card.caption)

        entry.obtained = `${rows} filas · cabeceras ${headers.length} ${JSON.stringify(headers)} · filas con familia ${familyMarks}/${rows} y ámbito ${scopeMarks}/${rows} · columna de referencia ${referenceHeader} con ${referenceValues.length} celda(s) ${JSON.stringify(referenceValues.slice(0, 6))} · KPI ${JSON.stringify(cards.map(card => `${card.label} ${card.value} (${card.caption})`))} · «${caption ?? '—'}» · lista: ${primitive.primitive} (data-table-scroll ${primitive.dataTables}, affordance de scroll ${primitive.scrollAffordance}) · ficha de detalle: enlaces ${detailLinks}`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = {
          painted,
          rows,
          headers,
          cards,
          rowTexts: rowTexts.slice(0, 8),
          primitive,
          caption,
          familyHeader,
          referenceHeader,
          referenceValues,
          referencePublished,
          scopeHeader,
          detailSurface: { rowAction: false, links: detailLinks },
          totalMatchesRows: total?.value === String(rows)
        }
        entry.verdict =
          rows > 0 &&
          kpisComplete &&
          total?.value === String(rows) &&
          familyHeader &&
          referenceHeader &&
          referencePublished &&
          scopeHeader &&
          familyMarks === rows &&
          scopeMarks === rows &&
          detailLinks === 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la lista no publica la referencia que cada método exige en su columna, o la familia, el ámbito y los KPI no describen las filas pintadas'

        return entry
      }
    )

    await step(
      'filter',
      'Buscar el método de la fixture, recorrer el filtro de estado, aislar una familia y buscar un término sin coincidencias',
      'La búsqueda deja exactamente su fila, el estado deja su población, la familia aísla sus métodos y los KPI conservan la población completa cuando un filtro no devuelve filas',
      async entry => {
        const { fixture } = fixtures.paymentMethods
        const before = await rowCount(page, ROWS)

        const searched = await search(page, fixture.name)
        const filtered = await rowCount(page, ROWS)
        const filteredTexts = await visibleTexts(page, ROWS)
        // A filtered read is paired with an unfiltered population read on the
        // same path, so the last call is the population one: the search is the
        // call that carries `q`.
        const searchCall =
          [...requests]
            .reverse()
            .find(
              entry =>
                entry.method === 'GET' &&
                entry.path === '/api/payment-methods' &&
                entry.query?.q === fixture.name
            ) ?? null
        const hitsFixture = Boolean(filteredTexts[0]?.includes(fixture.name))

        const restored = await search(page, '')
        const afterSearchClear = await rowCount(page, ROWS)

        const active = await pickStatus(page, 'Activos')
        const activeRows = await rowCount(page, ROWS)
        const activeTexts = await visibleTexts(page, ROWS)
        const activeInactive = activeTexts.filter(text => /\bInactivo\b/.test(text)).length

        const inactive = await pickStatus(page, 'Inactivos')
        const inactiveRows = await rowCount(page, ROWS)
        const inactiveTexts = await visibleTexts(page, ROWS)
        const inactiveActive = inactiveTexts.filter(text => /\bActivo\b/.test(text)).length
        const inactiveEmpty = inactiveRows === 0 ? await listText(page) : null

        const all = await pickStatus(page, 'Todos los estados')
        const allRows = await rowCount(page, ROWS)

        const familyTrigger =
          (await visibleTexts(page, '[data-type-filter] button'))[0]?.trim() ?? null
        const familyOptions = await readSelectOptions(page, '[data-type-filter] button')
        const familyPicked = await pickOption(page, '[data-type-filter] button', 'Tarjeta')
        await page.waitForTimeout(200)

        const familyRows = await rowCount(page, ROWS)
        const familyTexts = await visibleTexts(page, `${ROWS} td:nth-child(4)`)
        const familyIsolated =
          familyPicked.ok &&
          familyRows > 0 &&
          familyTexts.length === familyRows &&
          familyTexts.every(text => text === 'Tarjeta')

        const familyReset = await pickOption(
          page,
          '[data-type-filter] button',
          'Todas las familias'
        )
        await page.waitForTimeout(200)
        const familyResetRows = await rowCount(page, ROWS)

        const populationPending = page
          .waitForResponse(
            response => {
              if (response.request().method() !== 'GET') return false

              try {
                const url = new URL(response.url())
                return (
                  url.pathname === '/api/payment-methods' &&
                  !url.searchParams.has('q') &&
                  !url.searchParams.has('is_active')
                )
              } catch {
                return false
              }
            },
            { timeout: 8000 }
          )
          .catch(() => null)

        const noMatch = await search(page, 'ZZZ-AUDIT-U9-SIN-RESULTADOS')
        const populationResponse = await populationPending
        const noMatchRows = await rowCount(page, ROWS)
        const noMatchBody = await listText(page)
        const noMatchEmpty = /No se encontraron métodos de pago/.test(noMatchBody)
        const kpisWithNoMatch = await waitKpiTotal(page, before).then(kept => {
          return kpiCards(page).then(cards => ({ kept, cards }))
        })
        const noMatchTotal = kpisWithNoMatch.cards.find(card => card.key === 'total')?.value ?? null
        const kpiKeptPopulation = kpisWithNoMatch.kept && noMatchTotal === String(before)

        const cleared = await search(page, '')
        const afterClear = await rowCount(page, ROWS)
        const searchValue = await page
          .locator(SEARCH)
          .inputValue()
          .catch(() => null)
        const familyTriggerAfter =
          (await visibleTexts(page, '[data-type-filter] button'))[0]?.trim() ?? null

        entry.obtained = `«${fixture.name}» → ${filtered} fila (${hitsFixture ? 'la buscada' : 'otra'}) · q=${searchCall?.query?.q ?? '—'} ${searched.status ?? 'sin respuesta'} · limpiar búsqueda → ${afterSearchClear} filas · Activos → ${activeRows} filas, ${activeInactive} inactivas · Inactivos → ${inactiveRows} filas, ${inactiveActive} activas${inactiveEmpty ? ` («${inactiveEmpty.slice(0, 80)}»)` : ''} · Todos → ${allRows} filas · familia «${familyTrigger ?? '—'}» con opciones ${JSON.stringify(familyOptions.options ?? [])} → «${familyPicked.selected ?? `no elegida (${familyPicked.reason})`}» ${familyRows} fila(s) ${JSON.stringify(familyTexts.slice(0, 6))} · restaurar «${familyReset.selected ?? `no elegido (${familyReset.reason})`}» → ${familyResetRows} filas · sin coincidencias «ZZZ-AUDIT-U9-SIN-RESULTADOS» → ${noMatchRows} fila(s)${noMatchEmpty ? ' con su vacío' : ''} · KPI con la lista vacía ${JSON.stringify(kpisWithNoMatch.cards.map(card => `${card.label} ${card.value}`))} (población conservada ${kpiKeptPopulation}) · limpiar → ${afterClear} filas, búsqueda «${searchValue ?? ''}», familia «${familyTriggerAfter ?? '—'}»`
        entry.request = searchCall
          ? { method: searchCall.method, path: searchCall.path, status: searchCall.status }
          : null
        entry.detail = {
          before,
          searched,
          filtered,
          hitsFixture,
          searchCall,
          restored,
          afterSearchClear,
          active: { picked: active, rows: activeRows, texts: activeTexts },
          inactive: {
            picked: inactive,
            rows: inactiveRows,
            texts: inactiveTexts,
            empty: inactiveEmpty
          },
          all: { picked: all, rows: allRows },
          familyTrigger,
          familyOptions,
          familyPicked,
          familyRows,
          familyTexts,
          familyIsolated,
          familyReset,
          familyResetRows,
          noMatch,
          noMatchPopulationStatus: populationResponse?.status() ?? null,
          noMatchRows,
          noMatchEmpty,
          kpisWithNoMatch,
          kpiKeptPopulation,
          cleared,
          afterClear,
          searchValue,
          familyTriggerAfter
        }
        entry.verdict =
          filtered === 1 &&
          hitsFixture &&
          searchCall?.query?.q === fixture.name &&
          afterSearchClear === before &&
          activeRows > 0 &&
          activeInactive === 0 &&
          inactiveActive === 0 &&
          allRows === before &&
          familyOptions.ok &&
          familyPicked.ok &&
          familyIsolated &&
          familyReset.ok &&
          familyResetRows === before &&
          noMatch.status === 200 &&
          populationResponse?.status() === 200 &&
          noMatchRows === 0 &&
          noMatchEmpty &&
          kpiKeptPopulation &&
          afterClear === before &&
          searchValue === '' &&
          familyTriggerAfter === 'Todas las familias'
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : !familyOptions.ok || !familyPicked.ok || !familyIsolated
              ? 'la superficie no ofrece o no aplica el filtro por familia: el ABM no puede aislar las tarjetas, las transferencias ni las billeteras'
              : !kpiKeptPopulation
                ? 'los KPI describen la lista filtrada y no la población: con un filtro sin coincidencias la tira deja de publicar el catálogo completo'
                : 'la búsqueda o el filtro de estado no dejan la población que prometen'

        return entry
      }
    )

    await step(
      'create-empty',
      'Abrir «Nuevo método» y pulsar «Crear método» con el formulario vacío',
      'El formulario marca el código y el nombre en su campo y no dispara ningún POST',
      async entry => {
        const opened = await clickByLabel(page, 'Nuevo método')

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
          row => row.method === 'POST' && row.path === '/api/payment-methods'
        ).length

        const attempted = await respondTo(
          page,
          { method: 'POST', path: '/api/payment-methods' },
          () => page.locator(`${DIALOG} [data-modal-submit]`).click({ timeout: 8000 }),
          4000
        )

        await page.waitForTimeout(300)

        const errors = await fieldErrors(page)
        const toast = await toastText(page)
        const postsAfter = requests.filter(
          row => row.method === 'POST' && row.path === '/api/payment-methods'
        ).length
        const namesCode = errors.messages.some(message => /código/i.test(message))
        const namesName = errors.messages.some(message => /nombre/i.test(message))

        entry.obtained = `diálogo «${title}» abierto ${opened.ok} · mensajes ${JSON.stringify(errors.messages)} · campos inválidos ${JSON.stringify(errors.invalid)} · POST /api/payment-methods ${postsBefore} → ${postsAfter}${attempted.status ? ` (${attempted.status})` : ''} · toast «${toast}»`
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
      'Crear un método con familia tarjeta, referencia exigida, pasarela manual y comisión',
      'El POST responde 201 con los cinco valores y la fila aparece con su familia y su ámbito',
      async entry => {
        await clickByLabel(page, 'Nuevo método')

        const dialog = page.locator(DIALOG).last()
        await dialog.waitFor({ state: 'visible', timeout: 12000 }).catch(() => {})

        await fillField(page, 'Código', code)
        await fillField(page, 'Nombre visible', walkName)
        await pickOption(page, `${DIALOG} [data-field="type"] button`, 'Tarjeta')
        await page.locator(`${DIALOG} #requires_reference`).check({ timeout: 8000 })
        await pickOption(
          page,
          `${DIALOG} [data-field="gateway_type"] button`,
          'Manual (sin pasarela)'
        )
        await fillField(page, 'Comisión (%)', '2.5')

        const referenceChecked = await page
          .locator(`${DIALOG} #requires_reference`)
          .isChecked()
          .catch(() => null)

        const sent = await respondTo(page, { method: 'POST', path: '/api/payment-methods' }, () =>
          page.locator(`${DIALOG} [data-modal-submit]`).click({ timeout: 8000 })
        )

        await page.waitForTimeout(400)

        const toast = await toastText(page)
        const data = sent.body?.data ?? null
        const id = data?.id ?? null

        if (id) created.method = { id, code, name: walkName }

        await page
          .locator(DIALOG)
          .waitFor({ state: 'hidden', timeout: 6000 })
          .catch(() => {})

        const listed = await search(page, walkName)
        const rows = await rowCount(page, ROWS)
        const rowText = rows > 0 ? (await visibleTexts(page, ROWS))[0] : null

        entry.obtained = `POST ${sent.status ?? 'sin petición'} · id ${id ?? '—'} · familia «${data?.type ?? '—'}» · referencia ${data?.requires_reference ?? '—'} (casilla ${referenceChecked}) · pasarela «${data?.gateway_type ?? '—'}» · comisión ${data?.commission_percentage ?? '—'} · toast «${toast}» · ${rows} fila «${rowText ?? '—'}»`
        entry.request = requestOf(sent)
        entry.detail = {
          id,
          data: data
            ? {
                type: data.type,
                requiresReference: data.requires_reference,
                gateway: data.gateway_type,
                commission: data.commission_percentage,
                scope: data.is_system
              }
            : null,
          referenceChecked,
          toast,
          rows,
          rowText,
          searchStatus: listed.status
        }
        entry.verdict =
          sent.status === 201 &&
          id !== null &&
          data?.type === 'card' &&
          data?.requires_reference === true &&
          data?.gateway_type === 'manual' &&
          Number(data?.commission_percentage) === 2.5 &&
          rows === 1 &&
          /Tarjeta/.test(rowText ?? '') &&
          /Personalizado/.test(rowText ?? '') &&
          /Activo/.test(rowText ?? '')
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el alta no responde 201 con familia tarjeta, referencia y pasarela manual, o la fila no los refleja'

        return entry
      }
    )

    await step(
      'edit',
      'Editar el método de la caminata y cambiar su familia a billetera digital',
      'El código no se puede editar, el PUT responde 200 con type=wallet y la referencia exigida sigue activa',
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
          value: await codeInput.inputValue().catch(() => null)
        }

        const family = await pickOption(
          page,
          `${DIALOG} [data-field="type"] button`,
          'Billetera digital'
        )
        await fillField(page, 'Nombre visible', editedName)

        const referenceChecked = await page
          .locator(`${DIALOG} #requires_reference`)
          .isChecked()
          .catch(() => null)

        const sent = await respondTo(
          page,
          { method: 'PUT', path: new RegExp(`^/api/payment-methods/${created.method?.id}$`) },
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

        entry.obtained = `código editable ${!field.disabled && !field.readOnly} (disabled ${field.disabled}, readonly ${field.readOnly}) · familia elegida «${family.selected ?? `no elegida (${family.reason})`}» · casilla referencia ${referenceChecked} · PUT ${sent.status ?? 'sin petición'} · familia devuelta «${data?.type ?? '—'}» · referencia devuelta ${data?.requires_reference ?? '—'} · ${rows} fila «${rowText ?? '—'}» · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          field,
          family,
          referenceChecked,
          returned: data ? { type: data.type, requiresReference: data.requires_reference } : null,
          toast,
          rows,
          rowText
        }
        entry.verdict =
          sent.status === 200 &&
          data?.type === 'wallet' &&
          data?.requires_reference === true &&
          field.disabled === true &&
          rows === 1 &&
          /Billetera digital/.test(rowText ?? '')
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : field.disabled !== true
              ? 'el código es editable después de crear el método'
              : 'el PUT no cambia la familia o la fila no la refleja'

        return entry
      }
    )

    await step(
      'system-method-no-delete',
      'Buscar un método del sistema y comprobar que no ofrece «Eliminar»',
      'La fila publica el ámbito «Sistema», no ofrece la baja y el formulario explica por qué',
      async entry => {
        const systemName = 'Efectivo'

        await search(page, systemName)

        const row = rowNamed(page, systemName)
        const visible = await row
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)

        if (!visible) {
          entry.obtained = `la fila del método del sistema «${systemName}» no está visible`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'la base de auditoría no tiene el método del sistema «Efectivo»'

          return entry
        }

        const rowText = (await visibleTexts(page, ROWS))[0] ?? ''
        const rowControls = await buttonLabels(row)
        const deleteOffered = await row.locator('[data-row-action="delete"]').count()
        const listed = await api(session, 'GET', '/api/payment-methods?q=Efectivo')
        const apiRow = (listed.body?.data ?? []).find(entry => entry.is_system) ?? null
        const canDelete =
          Boolean(apiRow) && !apiRow.is_system && Number(apiRow.transactions_count) === 0
        const call = lastCall(requests, 'GET', '/api/payment-methods')

        await row.locator('[data-row-action="edit"]').click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        const dialogOpen = await dialog
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        const notice =
          (
            await dialog
              .locator('[data-system-notice]')
              .allInnerTexts()
              .catch(() => [])
          )[0]?.trim() ?? null
        const dialogControls = dialogOpen ? await buttonLabels(dialog) : []
        const dialogDelete = dialogControls.filter(label => /eliminar|borrar/i.test(label))

        await closeDialog(page)

        entry.obtained = `fila «${rowText}» · acciones ${JSON.stringify(rowControls)} · acciones de baja en la fila ${deleteOffered} · API is_system ${apiRow?.is_system ?? '—'}, transactions_count ${apiRow?.transactions_count ?? '—'} → canDelete ${canDelete} · aviso del formulario «${notice ?? '—'}» · acciones del formulario ${JSON.stringify(dialogControls)}`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = {
          rowText,
          rowControls,
          deleteOffered,
          api: apiRow
            ? { isSystem: apiRow.is_system, transactionsCount: apiRow.transactions_count }
            : null,
          canDelete,
          notice,
          dialogControls,
          dialogDelete
        }
        entry.verdict =
          visible &&
          /Sistema/.test(rowText) &&
          deleteOffered === 0 &&
          !rowControls.some(label => /eliminar|borrar/i.test(label)) &&
          apiRow?.is_system === true &&
          canDelete === false &&
          /no eliminar/i.test(String(notice)) &&
          dialogDelete.length === 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'un método del sistema ofrece la baja que canDelete prohíbe, o el formulario no explica la regla'

        return entry
      }
    )

    await step(
      'delete-own',
      'Eliminar el método que esta caminata creó',
      'La confirmación ejecuta el DELETE 200 y la fila desaparece de la lista',
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

        const controls = await buttonLabels(row)

        await row.locator('[data-row-action="delete"]').click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const sent = await respondTo(
          page,
          { method: 'DELETE', path: new RegExp(`^/api/payment-methods/${created.method?.id}$`) },
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
        const emptyShown = /No se encontraron métodos de pago/.test(await listText(page))

        entry.obtained = `acciones de la fila ${JSON.stringify(controls)} · confirmación «${confirmText}» · DELETE ${sent.status ?? 'sin petición'} · ${rows} fila(s) tras el borrado · vacío ${emptyShown} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = { controls, confirmText, toast, rows, emptyShown }
        entry.verdict = sent.status === 200 && rows === 0 ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el borrado del método propio no responde 200 o la fila permanece en la lista'

        return entry
      }
    )

    await step(
      'states',
      'Medir el estado de carga, el vacío de un filtro y el error con reintento',
      'La lista muestra su placeholder de carga, un vacío que explica el filtro con su acción y un error con reintento que recarga',
      async entry => {
        const detail = { loading: null, empty: null, error: null }

        await page.route('**/api/payment-methods*', async route => {
          await new Promise(resolve => setTimeout(resolve, 1500))
          await route.continue().catch(() => {})
        })

        await page.goto(SURFACE, { waitUntil: 'domcontentloaded' })

        const loading = await page
          .locator('[data-pm-loading]')
          .waitFor({ state: 'visible', timeout: 6000 })
          .then(() => true)
          .catch(() => false)

        detail.loading = {
          visible: loading,
          label: await page
            .locator('[data-pm-loading] [aria-label]')
            .first()
            .getAttribute('aria-label')
            .catch(() => null),
          shot: loading ? await shot(page, `shots/walk/${SLUG}/${entry.id}-loading.png`) : null
        }

        await page.unroute('**/api/payment-methods*')
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
          markers: (await page.locator('[data-pm-empty]').count()) > 0,
          title: /No se encontraron métodos de pago/.test(emptyBody),
          description: /Prueba con otros términos de búsqueda/.test(emptyBody),
          clearOffered: emptyClear > 0
        }

        await clickByLabel(page, 'Limpiar filtros')
        await page.waitForTimeout(300)

        await page.route('**/api/payment-methods*', route =>
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
          .locator('[data-pm-error]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const errorText = (await visibleTexts(page, '[data-pm-error]')).join(' | ')
        const retry = page.locator('[data-pm-retry]').first()
        const retryOffered = (await retry.count()) > 0

        detail.error = {
          painted: errorPainted,
          text: errorText,
          retryOffered,
          shot: await shot(page, `shots/walk/${SLUG}/${entry.id}-error.png`)
        }

        await page.unroute('**/api/payment-methods*')

        if (retryOffered) {
          await respondTo(page, { method: 'GET', path: '/api/payment-methods' }, () =>
            retry.click({ timeout: 8000 })
          )
          await page.waitForTimeout(500)
        }

        const rowsAfterRetry = await rowCount(page, ROWS)

        detail.error.rowsAfterRetry = rowsAfterRetry

        entry.obtained = `carga: ${detail.loading.visible ? `muestra «${detail.loading.label ?? 'Cargando'}»` : 'sin placeholder observable'} · vacío: ${detail.empty.title && detail.empty.description ? '«No se encontraron métodos de pago» con su explicación' : 'sin texto de vacío'}${detail.empty.clearOffered ? ' y «Limpiar filtros»' : ' y sin acción'} · error 500: ${detail.error.painted ? `bloque «${detail.error.text.slice(0, 90)}»` : 'sin bloque de error'} · «Reintentar» ${detail.error.retryOffered ? 'ofrecido' : 'ausente'} · tras reintentar ${rowsAfterRetry} fila(s)`
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
    if (created.method && !created.deleted) {
      const removed = await api(session, 'DELETE', `/api/payment-methods/${created.method.id}`)

      if (removed.status === 200) created.deleted = true

      evidence.cleanup.push({
        type: 'payment-method',
        id: created.method.id,
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
      failure => failure.status === 500 && /\/api\/payment-methods/.test(failure.url ?? ''),
      'el paso `states` provoca un 500 para medir el estado de error de la lista'
    )

    evidence.fixtures = { ...evidence.fixtures, ...fixtureSummary(fixtures) }
    evidence.counts = walkCounts(evidence.steps)

    audit('walk-payment-methods', evidence)
  }

  expect(evidence.pageErrors, 'page errors during the payment methods walkthrough').toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    'declared steps of the payment methods walkthrough'
  ).toEqual([
    '01-list',
    '02-filter',
    '03-create-empty',
    '04-create',
    '05-edit',
    '06-system-method-no-delete',
    '07-delete-own',
    '08-states'
  ])
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    'every step of the payment methods walkthrough has its capture'
  ).toBe(true)
})
