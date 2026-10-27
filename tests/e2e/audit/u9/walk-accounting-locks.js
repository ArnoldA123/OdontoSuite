// Plan #13 (issue #372, U9) — layer 4: the accounting lock, action by action.
//
// The surface publishes four modules, their effective date and the origin of the
// freeze (sede, global or none). The walk fixes a lock with a past date, changes
// it, sets a future date to measure whether the row stays honest about the date
// it will apply, and finally retires it. The lock it writes is a global row for
// a module that had none; the fixture's `quotations` row is only read.
//
// `06-future-date` is the effective-date honesty criterion: if the API accepts a
// future stop, the row has to show exactly that date, because the freeze applies
// to every record dated on or before it.
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

const SURFACE = '/settings/accounting-locks'
const SLUG = 'accounting-locks'
const ROWS = 'main .data-table-row'
const DIALOG = '[role="dialog"]:visible'

const DECLARED_CONSOLE = [
  {
    pattern: /Failed to load resource: .*500/,
    reason: 'el paso `states` provoca un 500 para medir el estado de error de la lista'
  }
]

const localDay = (offset = 0) => {
  const date = new Date()
  date.setDate(date.getDate() + offset)
  const pad = value => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'set', 'oct', 'nov', 'dic']

/** The literal-parts reader of `formatLockDate` in `accountingLockMeta.js`. */
const humanDate = value => {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value ?? ''))
  if (!match) return ''

  return `${match[3]} ${MONTHS[Number(match[2]) - 1]} ${match[1]}`
}

const listText = page =>
  page.evaluate(() => (document.querySelector('main')?.innerText ?? '').replace(/\s+/g, ' '))

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

const lockRows = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('main .data-table-row')).map(row => {
      const module = row.querySelector('[data-row-module]')
      const code = module?.parentElement?.querySelector('.font-mono')

      return {
        module: (module?.innerText ?? '').trim() || null,
        code: (code?.innerText ?? '').trim() || null,
        date: (row.querySelector('[data-row-locked-through]')?.innerText ?? '').trim() || null,
        source: (row.querySelector('[data-row-source]')?.innerText ?? '').trim() || null,
        setBy: (row.querySelector('[data-row-set-by]')?.innerText ?? '').trim() || null,
        setAt: (row.querySelector('[data-row-set-at]')?.innerText ?? '').trim() || null
      }
    })
  )

const lockRow = async (page, module) =>
  (await lockRows(page)).find(row => row.code === module) ?? null

const rowForModule = (page, module) => page.locator(ROWS).filter({ hasText: module }).first()

const openLock = async (page, module) => {
  await rowForModule(page, module).locator('[data-row-action="lock"]').click({ timeout: 8000 })

  const dialog = page.locator(DIALOG).last()
  const opened = await dialog
    .waitFor({ state: 'visible', timeout: 12000 })
    .then(() => true)
    .catch(() => false)

  return { dialog, opened }
}

const dialogFacts = async page => {
  const dialog = page.locator(DIALOG).last()
  const dateInput = page.locator('[data-lock-date] input')

  return {
    title:
      (
        (await dialog
          .locator('h2')
          .first()
          .innerText()
          .catch(() => '')) || ''
      ).trim() || null,
    caption: (await visibleTexts(page, '[data-lock-modal-caption]'))[0] ?? null,
    label: (await visibleTexts(page, '[data-lock-date] label'))[0] ?? null,
    type: await dateInput.getAttribute('type').catch(() => null),
    required: (await dateInput.getAttribute('required').catch(() => null)) !== null,
    value: await dateInput.inputValue().catch(() => null)
  }
}

test('@U9 walks bloqueo contable', async ({ page }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const session = await login('administrador')
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)

  const pastDate = localDay(-3)
  const secondDate = localDay(-5)
  const futureDate = localDay(5)

  const created = { module: null, retired: false }
  const dates = { pastDate, secondDate, futureDate }

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks bloqueo contable',
    role: 'administrador',
    steps: [],
    counts: null,
    fixtures: { marker: fixtures.marker, ok: fixtures.ok, token: fixtures.token },
    created: { module: null, dates },
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
      'Abrir /settings/accounting-locks como administración',
      'La lista pinta los cuatro módulos con su fecha efectiva, su origen (sede, global o sin bloqueo) y los tres KPI',
      async entry => {
        const painted = await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)
        const rows = await lockRows(page)
        const headers = await tableHeaders(page)
        const cards = await kpiCards(page)
        const primitive = await tablePrimitive(page)
        const caption = (await visibleTexts(page, '[data-hero-caption]'))[0] ?? null
        const scope = (await visibleTexts(page, '[data-scope-name]'))[0] ?? null
        const call = lastCall(requests, 'GET', '/api/accounting-locks')
        const wanted = ['módulo', 'fecha efectiva', 'origen', 'actualizado por', 'actualizado el']
        const headersNamed = wanted.every(label =>
          headers.some(header => header.toLowerCase() === label)
        )
        const origins = rows.map(row => row.source)
        const everyModule = rows.length === 4 && rows.every(row => row.code && row.module)
        const originsKnown = rows.every(row => /^(Sede|Global|Sin bloqueo)$/.test(row.source ?? ''))
        const locked = rows.filter(row => row.source !== 'Sin bloqueo')
        const datesHonest = locked.every(row => row.date && row.date !== 'Sin bloqueo')
        const kpisComplete =
          cards.length === 3 &&
          cards.every(card => card.value !== null && card.caption) &&
          cards.find(card => card.key === 'modules')?.value === '4'

        entry.obtained = `${rows.length} módulos ${JSON.stringify(rows.map(row => `${row.code} «${row.module}» → ${row.date} / ${row.source}`))} · cabeceras ${JSON.stringify(headers)} · KPI ${JSON.stringify(cards.map(card => `${card.label} ${card.value} (${card.caption})`))} · alcance «${scope ?? '—'}» · «${caption ?? '—'}» · lista: ${primitive.primitive} (data-table-scroll ${primitive.dataTables}, affordance de scroll ${primitive.scrollAffordance})`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = {
          painted,
          rows,
          headers,
          cards,
          primitive,
          caption,
          scope,
          origins,
          kpisComplete
        }
        entry.verdict =
          painted && headersNamed && everyModule && originsKnown && datesHonest && kpisComplete
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la lista no publica los cuatro módulos con su fecha efectiva, su origen o los tres KPI'

        return entry
      }
    )

    await step(
      'open',
      'Abrir «Fijar» del primer módulo sin bloqueo y leer el modal',
      'El modal nombra el módulo, el alcance y su campo de fecha de cierre',
      async entry => {
        const rows = await lockRows(page)
        const unlocked = rows.find(row => row.source === 'Sin bloqueo')

        created.module = unlocked?.code ?? fixtures.lockModule

        const opened = await openLock(page, created.module)
        const facts = opened.opened ? await dialogFacts(page) : null

        const cancel = await clickByLabel(page, 'Cancelar')

        await page
          .locator(DIALOG)
          .last()
          .waitFor({ state: 'hidden', timeout: 4000 })
          .catch(() => {})

        const stillOpen = await page
          .locator(DIALOG)
          .last()
          .isVisible()
          .catch(() => false)

        const puts = requests.filter(
          row => row.method === 'PUT' && row.path === `/api/accounting-locks/${created.module}`
        ).length

        entry.obtained = `módulo elegido «${created.module}» (${unlocked ? 'sin bloqueo' : 'con bloqueo: no había fila sin bloquear'}) · modal ${opened.opened} · título «${facts?.title ?? '—'}» · alcance «${facts?.caption ?? '—'}» · campo «${facts?.label ?? '—'}» type=${facts?.type ?? '—'} required=${facts?.required ?? '—'} · «Cancelar» ${cancel.ok ? 'cierra' : 'no cierra'} · modal visible tras cancelar ${stillOpen} · PUT durante el paso ${puts}`
        entry.request = null
        entry.detail = {
          module: created.module,
          unlocked: unlocked ?? null,
          opened: opened.opened,
          facts,
          cancel,
          stillOpen,
          puts
        }
        entry.verdict =
          opened.opened &&
          Boolean(facts?.title?.includes('cierre')) &&
          facts?.type === 'date' &&
          /fecha de cierre/i.test(String(facts?.label)) &&
          !stillOpen &&
          puts === 0
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el modal no nombra el módulo, no publica su campo de fecha o «Cancelar» no lo cierra'

        return entry
      }
    )

    await step(
      'empty',
      'Enviar el modal de cierre sin fecha',
      'El error se pinta en el campo de fecha y no se envía ningún PUT',
      async entry => {
        const opened = await openLock(page, created.module)
        const before = requests.filter(
          row => row.method === 'PUT' && row.path === `/api/accounting-locks/${created.module}`
        ).length

        const attempted = await respondTo(
          page,
          { method: 'PUT', path: `/api/accounting-locks/${created.module}` },
          () => page.locator('[data-lock-submit]').click({ timeout: 8000 }),
          4000
        )

        await page.waitForTimeout(300)

        const errors = await fieldErrors(page)
        const toast = await toastText(page)
        const after = requests.filter(
          row => row.method === 'PUT' && row.path === `/api/accounting-locks/${created.module}`
        ).length

        entry.obtained = `modal ${opened.opened} · mensajes ${JSON.stringify(errors.messages)} · campos inválidos ${JSON.stringify(errors.invalid)} · PUT ${before} → ${after}${attempted.status ? ` (${attempted.status})` : ''} · toast «${toast}»`
        entry.request = requestOf(attempted)
        entry.detail = { errors, toast, before, after }
        entry.verdict =
          opened.opened &&
          after === before &&
          errors.messages.some(message => /fecha de cierre/i.test(message))
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el envío vacío no señala la fecha en su campo o dispara una escritura'

        await closeDialog(page)

        return entry
      }
    )

    await step(
      'set',
      'Fijar el cierre del módulo con una fecha pasada',
      'El PUT responde 200 y la fila muestra la fecha, el origen «Global», quién lo fijó y cuándo',
      async entry => {
        const opened = await openLock(page, created.module)

        await fillField(page, 'Fecha de cierre contable', pastDate)

        const sent = await respondTo(
          page,
          { method: 'PUT', path: `/api/accounting-locks/${created.module}` },
          () => page.locator('[data-lock-submit]').click({ timeout: 8000 })
        )

        await page.waitForTimeout(500)
        await settle(page)

        const toast = await toastText(page)
        const row = await lockRow(page, created.module)
        const expected = humanDate(pastDate)

        entry.obtained = `modal ${opened.opened} · fecha ${pastDate} · PUT ${sent.status ?? 'sin petición'} · locked_through «${sent.body?.data?.locked_through ?? '—'}» · source «${sent.body?.data?.source ?? '—'}» · fila «${row?.date ?? '—'}» / «${row?.source ?? '—'}» / «${row?.setBy ?? '—'}» / «${row?.setAt ?? '—'}» · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          opened: opened.opened,
          date: pastDate,
          expected,
          body: sent.body?.data ?? null,
          row,
          toast
        }
        entry.verdict =
          sent.status === 200 &&
          sent.body?.data?.locked_through === pastDate &&
          sent.body?.data?.source === 'global' &&
          row?.date === expected &&
          row?.source === 'Global' &&
          /^Usuario #\d+$/.test(row?.setBy ?? '') &&
          Boolean(row?.setAt) &&
          row.setAt !== '—'
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el cierre no se guarda o la fila no publica la fecha aplicada, su origen y su autor'

        return entry
      }
    )

    await step(
      'update',
      'Volver a fijar el mismo módulo con otra fecha',
      'La fecha de la fila cambia y el módulo sigue teniendo una sola fila',
      async entry => {
        const opened = await openLock(page, created.module)

        await fillField(page, 'Fecha de cierre contable', secondDate)

        const sent = await respondTo(
          page,
          { method: 'PUT', path: `/api/accounting-locks/${created.module}` },
          () => page.locator('[data-lock-submit]').click({ timeout: 8000 })
        )

        await page.waitForTimeout(500)
        await settle(page)

        const toast = await toastText(page)
        const rows = await lockRows(page)
        const row = rows.find(entry => entry.code === created.module) ?? null
        const sameModuleRows = rows.filter(entry => entry.code === created.module).length
        const expected = humanDate(secondDate)

        entry.obtained = `modal ${opened.opened} · fecha ${secondDate} · PUT ${sent.status ?? 'sin petición'} · locked_through «${sent.body?.data?.locked_through ?? '—'}» · fila «${row?.date ?? '—'}» · filas del módulo ${sameModuleRows} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = {
          opened: opened.opened,
          date: secondDate,
          expected,
          body: sent.body?.data ?? null,
          row,
          sameModuleRows,
          toast
        }
        entry.verdict =
          sent.status === 200 &&
          sent.body?.data?.locked_through === secondDate &&
          row?.date === expected &&
          sameModuleRows === 1
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la nueva fecha no reemplaza la anterior o el módulo acumula más de una fila'

        return entry
      }
    )

    await step(
      'future-date',
      'Fijar el mismo módulo con una fecha futura',
      'La fecha se acepta o se rechaza de forma explicable y, si se acepta, la fila muestra exactamente la fecha fijada',
      async entry => {
        const before = await lockRow(page, created.module)
        const opened = await openLock(page, created.module)

        await fillField(page, 'Fecha de cierre contable', futureDate)

        const sent = await respondTo(
          page,
          { method: 'PUT', path: `/api/accounting-locks/${created.module}` },
          () => page.locator('[data-lock-submit]').click({ timeout: 8000 })
        )

        await page.waitForTimeout(500)
        await settle(page)

        const toast = await toastText(page)
        const errors = await fieldErrors(page)
        const row = await lockRow(page, created.module)
        const expected = humanDate(futureDate)
        const accepted = sent.status === 200
        const honest = accepted ? row?.date === expected : row?.date === before?.date

        entry.obtained = `modal ${opened.opened} · fecha ${futureDate} · PUT ${sent.status ?? 'sin petición'} · locked_through «${sent.body?.data?.locked_through ?? '—'}» · source «${sent.body?.data?.source ?? '—'}» · fila «${row?.date ?? '—'}» (esperada «${expected}») · toast «${toast}» · errores en el campo ${JSON.stringify(errors.messages)}`
        entry.request = requestOf(sent)
        entry.detail = {
          opened: opened.opened,
          date: futureDate,
          expected,
          accepted,
          honest,
          before,
          row,
          body: sent.body?.data ?? null,
          toast,
          errors
        }
        entry.verdict =
          accepted && honest
            ? 'OK'
            : !accepted &&
                row?.date === before?.date &&
                (errors.messages.length > 0 || Boolean(toast))
              ? 'OK'
              : 'DEFECTO'
        entry.notes = accepted
          ? 'la API acepta una fecha futura y la fila la publica tal cual: el cierre bloquea todo registro con fecha anterior o igual a la fijada, sin aviso adicional en la fila'
          : entry.verdict === 'OK'
            ? ''
            : 'la fecha futura se acepta sin que la fila la refleje o se rechaza sin explicarlo'

        return entry
      }
    )

    await step(
      'retire',
      'Retirar el cierre del módulo desde la acción de su fila',
      'La confirmación ejecuta el DELETE 200 y la fila vuelve a «Sin bloqueo»',
      async entry => {
        const row = rowForModule(page, created.module)

        await row.locator('[data-row-action="clear"]').click({ timeout: 8000 })

        const confirm = page.locator('[role="alertdialog"]:visible').last()
        const confirmText = ((await confirm.innerText().catch(() => '')) || '')
          .trim()
          .replace(/\s+/g, ' ')

        const sent = await respondTo(
          page,
          { method: 'DELETE', path: `/api/accounting-locks/${created.module}` },
          () => confirmDialog(page, 'Retirar')
        )

        await page.waitForTimeout(500)
        await settle(page)

        const toast = await toastText(page)
        const after = await lockRow(page, created.module)
        const rows = await lockRows(page)

        if (sent.status === 200) created.retired = true

        entry.obtained = `confirmación «${confirmText}» · DELETE ${sent.status ?? 'sin petición'} «${sent.body?.meta?.message ?? '—'}» · fila «${after?.date ?? '—'}» / «${after?.source ?? '—'}» · filas del módulo ${rows.filter(entry => entry.code === created.module).length} · toast «${toast}»`
        entry.request = requestOf(sent)
        entry.detail = { confirmText, status: sent.status, row: after, toast }
        entry.verdict =
          sent.status === 200 &&
          after?.date === 'Sin bloqueo' &&
          after?.source === 'Sin bloqueo' &&
          rows.filter(entry => entry.code === created.module).length === 1
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el retiro no responde 200 o la fila conserva su fecha y su origen'

        return entry
      }
    )

    await step(
      'states',
      'Medir el estado de carga, el vacío del servicio y el error con reintento',
      'La lista muestra su placeholder de carga, un vacío declarado y un error con reintento que recarga',
      async entry => {
        const detail = { loading: null, empty: null, error: null }

        await page.route('**/api/accounting-locks*', async route => {
          await new Promise(resolve => setTimeout(resolve, 1500))
          await route.continue().catch(() => {})
        })

        await page.goto(SURFACE, { waitUntil: 'domcontentloaded' })

        const loading = await page
          .locator('[data-locks-loading]')
          .waitFor({ state: 'visible', timeout: 6000 })
          .then(() => true)
          .catch(() => false)

        detail.loading = {
          visible: loading,
          busy: await page
            .locator('[data-locks-loading]')
            .getAttribute('aria-busy')
            .catch(() => null),
          label: await page
            .locator('[data-locks-loading] [aria-label]')
            .first()
            .getAttribute('aria-label')
            .catch(() => null),
          shot: loading ? await shot(page, `shots/walk/${SLUG}/${entry.id}-loading.png`) : null
        }

        await page.unroute('**/api/accounting-locks*')
        await page
          .locator(ROWS)
          .first()
          .waitFor({ state: 'visible', timeout: 15000 })
          .catch(() => {})

        await page.route('**/api/accounting-locks*', route =>
          route
            .fulfill({
              status: 200,
              contentType: 'application/json',
              body: JSON.stringify({ data: [] })
            })
            .catch(() => {})
        )

        await page.reload({ waitUntil: 'domcontentloaded' })

        const emptyPainted = await page
          .locator('[data-locks-empty]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const emptyBody = await listText(page)

        detail.empty = {
          painted: emptyPainted,
          markers: (await page.locator('[data-locks-empty]').count()) > 0,
          title: /No hay módulos para mostrar/.test(emptyBody),
          description: /no devolvió módulos de bloqueo contable/.test(emptyBody),
          shot: await shot(page, `shots/walk/${SLUG}/${entry.id}-empty.png`)
        }

        await page.unroute('**/api/accounting-locks*')

        await page.route('**/api/accounting-locks*', route =>
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
          .locator('[data-locks-error]')
          .waitFor({ state: 'visible', timeout: 10000 })
          .then(() => true)
          .catch(() => false)
        const errorText = (await visibleTexts(page, '[data-locks-error]')).join(' | ')
        const retry = page.locator('[data-locks-retry]').first()
        const retryOffered = (await retry.count()) > 0

        detail.error = {
          painted: errorPainted,
          text: errorText,
          retryOffered,
          shot: await shot(page, `shots/walk/${SLUG}/${entry.id}-error.png`)
        }

        await page.unroute('**/api/accounting-locks*')

        if (retryOffered) {
          await respondTo(page, { method: 'GET', path: '/api/accounting-locks' }, () =>
            retry.click({ timeout: 8000 })
          )
          await page.waitForTimeout(500)
        }

        const rowsAfterRetry = await rowCount(page, ROWS)

        detail.error.rowsAfterRetry = rowsAfterRetry

        entry.obtained = `carga: ${detail.loading.visible ? `muestra «${detail.loading.label ?? 'Cargando'}» (aria-busy ${detail.loading.busy ?? '—'})` : 'sin placeholder observable'} · vacío: ${detail.empty.title && detail.empty.description ? '«No hay módulos para mostrar» con su explicación' : 'sin texto de vacío'} · error 500: ${detail.error.painted ? `bloque «${detail.error.text.slice(0, 90)}»` : 'sin bloque de error'} · «Reintentar» ${detail.error.retryOffered ? 'ofrecido' : 'ausente'} · tras reintentar ${rowsAfterRetry} fila(s)`
        entry.detail = detail
        entry.verdict =
          detail.loading.visible &&
          detail.loading.busy === 'true' &&
          detail.empty.title &&
          detail.empty.description &&
          detail.error.painted &&
          detail.error.retryOffered &&
          rowsAfterRetry === 4
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'la lista no cubre carga, vacío y error con reintento, o el reintento no devuelve los cuatro módulos'

        return entry
      }
    )
  } finally {
    if (created.module) {
      const row = await api(session, 'GET', '/api/accounting-locks')
      const current = (row.body?.data ?? []).find(entry => entry.module === created.module) ?? null

      if (current && current.source !== 'none') {
        const removed = await api(session, 'DELETE', `/api/accounting-locks/${created.module}`)

        evidence.cleanup.push({
          type: 'accounting-lock',
          module: created.module,
          status: removed.status,
          retired: removed.status === 200,
          message: removed.body?.meta?.message ?? null
        })
      }
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
      failure => failure.status === 500 && /\/api\/accounting-locks/.test(failure.url ?? ''),
      'el paso `states` provoca un 500 para medir el estado de error de la lista'
    )

    evidence.fixtures = { ...evidence.fixtures, ...fixtureSummary(fixtures) }
    evidence.created = { module: created.module, dates, retired: created.retired }
    evidence.counts = walkCounts(evidence.steps)

    audit('walk-accounting-locks', evidence)
  }

  expect(evidence.pageErrors, 'page errors during the accounting lock walkthrough').toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    'declared steps of the accounting lock walkthrough'
  ).toEqual([
    '01-list',
    '02-open',
    '03-empty',
    '04-set',
    '05-update',
    '06-future-date',
    '07-retire',
    '08-states'
  ])
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    'every step of the accounting lock walkthrough has its capture'
  ).toBe(true)
})
