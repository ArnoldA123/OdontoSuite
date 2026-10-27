// Plan #13 (issue #373, U10) — layer 4: the continuity queue, action by action.
//
// The desk publishes two populations (overdue recalls and the conduct a
// professional left at the closure) behind four KPIs, and reception resolves both
// through their own doors: a recall leaves by being scheduled, a request leaves by
// being scheduled or dismissed with a reason. This walk measures the whole
// circuit for the row the fixture of the session wrote:
//
//   1. QUEUE    both sections, the four KPIs (with the raw compliance rate), the
//               row that carries `fixtures.token` with its reason/date/origin/
//               duration cells, and the three action hooks of the two desks.
//   2. DIALOGS  `assertDialogContract` over the schedule modal and over the
//               dismiss modal, one step each, every check row recorded.
//   3. RECALL   the prefilled date/duration/type of the recall form, the
//               professional + chair + type it demands, the 201, the toast, the
//               row leaving the section and the KPI moving.
//   4. CONFLICT the request scheduled over the slot `fixtures.conflict` already
//               occupies: the exact status, the field the message landed in and
//               the row still pending afterwards (S5: «el conflicto de agenda
//               deja la solicitud pendiente y lo dice»).
//   5. DISMISS  the submit disabled with a blank reason, what forcing the submit
//               reports and that no request left, then the 200 with a reason and
//               the row leaving the queue.
//   6. EMPTY    the two section empty states, or `NO PROBADO` with the reason
//               when another session still holds rows.
//   7. READ     as `odontologo`: the queue paints and no write affordance does;
//               the write door answers 403 with that role's real token.
//
// The order follows the state of the queue, not the issue's numbering: the two
// dialog contracts run before the request is resolved, because the dismiss modal
// is reached from the row that step 5 retires.
//
// `DEFECTO` / `NO-OP` / `CONFUSO` / `NO PROBADO` are evidence, never a thrown
// failure. The hard assertions are that no `pageerror` was raised, that every
// declared step was recorded and that each one left its capture.

import { expect, test } from 'playwright/test'

import {
  assertDialogContract,
  classifyFailures,
  collectDiagnostics,
  primeSession
} from '../harness.js'

import { ensureFixtures } from './fixtures.js'

import {
  DESKTOP,
  SESSION,
  api,
  audit,
  classifyConsole,
  closeDialog,
  dismissToasts,
  gotoSurface,
  lastCall,
  login,
  markExpected,
  pickOption,
  requestOf,
  respondTo,
  settle,
  shot,
  toastText,
  walkCounts,
  walkStep,
  watchRequests
} from './helpers.js'

const SURFACE = '/continuity'
const SLUG = 'continuity'
const ROLE = 'recepcionista'
const READ_ONLY_ROLE = 'odontologo'

const DIALOG = '[role="dialog"]:visible'
const QUEUE = '/api/continuity/queue'

// The exact sentence the dismiss door reports when the reason is blank. It lives
// in `DismissContinuityModal`, not in the API (`dismiss` accepts a null reason).
const REASON_REQUIRED = 'El motivo es obligatorio.'

/** Rows of a payload whose list may be `data` or `data.data`. */
const rowsOf = body => {
  const payload = body?.data

  if (Array.isArray(payload)) return payload
  if (Array.isArray(payload?.data)) return payload.data

  return []
}

const listOf = value => (Array.isArray(value) ? value : [])

/** The queue of the session's own write door, so both sides of a step exist. */
const panel = page =>
  page.evaluate(() => {
    const text = value => (value ?? '').trim().replace(/\s+/g, ' ')
    const sections = Array.from(document.querySelectorAll('[data-continuity-section]')).map(
      section => {
        const key = section.getAttribute('data-continuity-section')
        const empty = section.querySelector(`[data-continuity-empty="${key}"]`)
        const rows = Array.from(section.querySelectorAll('.data-table-row'))
          .map(row => ({
            cells: Array.from(row.querySelectorAll('td')).map(cell => text(cell.innerText)),
            schedule: Array.from(row.querySelectorAll('[data-continuity-schedule]')).map(node =>
              node.getAttribute('data-continuity-schedule')
            ),
            dismiss: row.querySelectorAll('[data-continuity-dismiss]').length > 0
          }))
          .filter(row => row.cells.some(Boolean))

        return {
          key,
          heading: text(section.querySelector('h3')?.innerText),
          rows,
          empty: empty
            ? {
                title: text(empty.querySelector('p')?.innerText) || null,
                description: text(empty.querySelectorAll('p')[1]?.innerText) || null,
                text: text(empty.innerText)
              }
            : null
        }
      }
    )

    const kpis = Array.from(document.querySelectorAll('[data-kpi-card]')).map(card => {
      const lines = (card.innerText ?? '')
        .split('\n')
        .map(line => line.trim())
        .filter(Boolean)
      const raw = card.querySelector('[data-kpi-raw]')

      return {
        key: card.getAttribute('data-kpi-card'),
        label: lines[0] ?? null,
        value: text(card.querySelector('[data-kpi-value]')?.innerText) || null,
        raw: raw ? raw.getAttribute('data-kpi-raw') : null,
        caption: lines.at(-1) ?? null
      }
    })

    return {
      sections,
      kpis,
      refresh: Boolean(document.querySelector('[data-continuity-refresh]')),
      error: text(document.querySelector('[data-continuity-error]')?.innerText) || null,
      loading: Boolean(document.querySelector('[data-continuity-loading]'))
    }
  })

/** The row of a section that carries the token, or `null` while it is absent. */
const rowWith = (view, key, token) =>
  (view.sections.find(section => section.key === key)?.rows ?? []).find(row =>
    row.cells.join(' ').includes(token)
  ) ?? null

const kpiOf = (view, key) => view.kpis.find(card => card.key === key) ?? null

const rowLocator = (page, key, token) =>
  page
    .locator(`[data-continuity-section="${key}"] .data-table-row`)
    .filter({ hasText: token })
    .first()

const waitForQueue = async (page, action, timeout = 15000) =>
  respondTo(page, { method: 'GET', path: QUEUE }, action, timeout)

/**
 * A `datetime-local` value for an API instant.
 *
 * The agenda serializes `scheduled_at` in the application's timezone with its
 * offset (`2026-10-28T10:15:00-05:00`), so the literal date and hour ARE the wall
 * clock the form speaks. Converting the offset through the runner's timezone
 * (UTC in CI) moved the conflict slot five hours and the walk measured a free
 * slot as if it were the occupied one. Only a UTC (`Z`) value needs conversion.
 */
const wallClock = value => {
  const source = String(value ?? '')
  const naive = /^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/.exec(source)

  if (naive && !/Z$/.test(source)) return `${naive[1]}T${naive[2]}`

  const date = new Date(source)
  if (Number.isNaN(date.getTime())) return ''

  const pad = number => String(number).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(
    date.getHours()
  )}:${pad(date.getMinutes())}`
}

/** The instant of an API datetime, or `null` when it cannot be parsed. */
const instantOf = value => {
  const date = new Date(String(value ?? '').replace(' ', 'T'))
  return Number.isNaN(date.getTime()) ? null : date
}

/** The schedule form's own prefilled values, read from the modal. */
const prefilled = page =>
  page.evaluate(() => {
    const text = value => (value ?? '').trim().replace(/\s+/g, ' ')

    return {
      scheduledAt: document.querySelector('[data-sc-scheduled-at] input')?.value ?? null,
      duration: document.querySelector('[data-sc-duration] input')?.value ?? null,
      type: text(document.querySelector('[data-sc-type] button')?.innerText) || null,
      professional: text(document.querySelector('[data-sc-user] button')?.innerText) || null,
      chair: text(document.querySelector('[data-sc-chair] button')?.innerText) || null,
      title: text(document.querySelector('[data-schedule-title]')?.innerText) || null
    }
  })

/** Every coloured message of the open schedule form, by field. */
const scheduleErrors = page =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[data-schedule-error]')).map(node => ({
      field: node.getAttribute('data-schedule-error'),
      text: (node.innerText ?? '').trim().replace(/\s+/g, ' ')
    }))
  )

/** The dismiss form's own state: the submit gate and its field message. */
const dismissState = page =>
  page.evaluate(() => {
    const text = value => (value ?? '').trim().replace(/\s+/g, ' ')
    const submit = document.querySelector('[data-dismiss-submit]')
    const error = document.querySelector('[data-dismiss-error]')

    return {
      disabled: submit ? submit.disabled : null,
      error: text(error?.innerText) || null,
      reason: document.querySelector('[data-dismiss-reason]')?.value ?? null
    }
  })

/** The next slots the recall form is retried on when the agenda refuses one. */
const nextSlots = (seed, minute = 0, count = 6) => {
  const slots = []
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(String(seed ?? ''))

  if (!match) return slots

  const base = new Date(
    Number(match[1]),
    Number(match[2]) - 1,
    Number(match[3]),
    Number(match[4]),
    Number(match[5]),
    0,
    0
  )
  const pad = number => String(number).padStart(2, '0')

  for (let index = 0; index < count; index += 1) {
    const slot = new Date(base.getTime())
    const days = Math.floor(index / 5)
    slot.setDate(slot.getDate() + days)
    slot.setHours(base.getHours() + (index % 5), minute, 0, 0)

    slots.push(
      `${slot.getFullYear()}-${pad(slot.getMonth() + 1)}-${pad(slot.getDate())}T${pad(
        slot.getHours()
      )}:${pad(slot.getMinutes())}`
    )
  }

  return [...new Set(slots)]
}

/** The dialog contract as the step's own rows, one entry per check. */
const contractRow = async (page, dialog) => {
  const result = await assertDialogContract(page, { dialog })

  return {
    dialog: result.dialog,
    checks: result.checks,
    failed: result.checks.filter(check => !check.ok).map(check => check.id)
  }
}

/** The `obtained` line of a contract step: every check, not only its failures. */
const contractLine = contract =>
  contract.checks.map(check => `${check.id}=${check.ok ? 'ok' : 'FALLA'}`).join(' · ')

/**
 * The walk itself.
 *
 * Exported so the session can run it outside the runner — the diagnostic that
 * validates the hooks while `surface.spec.js` still does not import this module
 * — and so a future row can compose it without re-reading the file.
 */
export const walkThroughContinuity = async ({ page, fixtures }) => {
  const session = await login(ROLE)
  const readOnly = await login(READ_ONLY_ROLE)
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)

  const { token } = fixtures
  const requestId = fixtures.continuity.request?.id ?? null
  const recallId = fixtures.continuity.recall?.id ?? null
  const conflict = fixtures.conflict ?? null

  // The form's selects publish `name`, not `username`, so the labels a step must
  // click are resolved from the same endpoints the modal reads.
  const activeUsers = rowsOf((await api(session, 'GET', '/api/users/active')).body)
  const activeChairs = rowsOf((await api(session, 'GET', '/api/dental-chairs/active')).body)
  const activeTypes = rowsOf((await api(session, 'GET', '/api/appointment-types/active')).body)

  const professional =
    activeUsers.find(row => row.id === fixtures.actors.professional?.id) ?? activeUsers[0] ?? null
  const chairPool = activeChairs.filter(row => row.name)
  const typePool = activeTypes.filter(row => row.name)

  const evidence = {
    session: SESSION,
    surface: SURFACE,
    slug: SLUG,
    test: 'walks continuidad',
    role: ROLE,
    roles: [ROLE, READ_ONLY_ROLE],
    steps: [],
    counts: null,
    fixtures: {
      marker: fixtures.marker,
      token,
      day: fixtures.day,
      ok: fixtures.ok,
      request: fixtures.continuity.request,
      recall: fixtures.continuity.recall,
      queueCounts: fixtures.continuity.queue?.counts ?? null,
      conflict: conflict
        ? {
            id: conflict.id,
            userId: conflict.userId,
            chairId: conflict.chairId,
            scheduledAt: conflict.scheduledAt,
            status: conflict.status,
            source: conflict.source
          }
        : null
    },
    lookups: {
      professional: professional ? { id: professional.id, name: professional.name } : null,
      chairs: chairPool.map(row => ({ id: row.id, name: row.name })).slice(0, 3),
      types: typePool.map(row => ({ id: row.id, name: row.name })).slice(0, 3)
    },
    captures: [],
    created: { recallAppointmentId: null, scheduledSlots: [], dismissed: false },
    pageErrors: [],
    consoleErrors: [],
    consoleClassified: null,
    failedRequests: null,
    traffic: null
  }

  const step = walkStep(page, evidence)

  // The conflict and the request schedule are refusals the walk provokes on the
  // page: they are reported with their reason instead of as surface failures.
  const declared = []
  const declaredFailure = {
    predicate: failure =>
      [403, 422].includes(failure.status) &&
      /\/api\/(continuity-requests|patient-recalls)\//.test(failure.url ?? ''),
    reason:
      'el paso `conflict` manda la solicitud al hueco que ya ocupa otra cita y el paso `read-only` prueba la puerta de escritura: los dos 422/403 son la medición'
  }

  page.setDefaultTimeout(8000)

  try {
    await page.setViewportSize(DESKTOP)
    await primeSession(page, session)

    const opened = await gotoSurface(page, SURFACE)

    await page
      .locator('[data-continuity-section], [data-continuity-loading]')
      .first()
      .waitFor({ state: 'visible', timeout: 15000 })
      .catch(() => {})
    await settle(page)

    await step(
      'queue',
      'Abrir /continuity como recepcionista con la sesión sembrada',
      'Pinta las dos secciones, los cuatro KPI (con la tasa de cumplimiento cruda), la fila que lleva el token de la sesión con su motivo/fecha/origen/duración y las tres acciones de las dos mesas',
      async entry => {
        const view = await panel(page)
        const recall = rowWith(view, 'recalls', token)
        const request = rowWith(view, 'requests', token)
        const recallKpi = kpiOf(view, 'recalls')
        const compliance = kpiOf(view, 'compliance')
        const keys = view.sections.map(section => section.key)
        const call = lastCall(requests, 'GET', QUEUE)
        const kpisComplete =
          view.kpis.length === 4 &&
          view.kpis.every(card => card.value !== null) &&
          Boolean(compliance?.raw)
        const bothSections = keys.includes('recalls') && keys.includes('requests')
        const requestActions =
          (request?.schedule ?? []).includes('request') && request?.dismiss === true
        const recallActions = (recall?.schedule ?? []).includes('recall')

        evidence.captures.push(await shot(page, 'shots/walk/continuity-queue.png'))

        entry.obtained = `HTTP ${opened.status} ${opened.url} · secciones ${JSON.stringify(
          keys.map(
            key =>
              `${key}: ${view.sections.find(section => section.key === key)?.rows.length ?? 0} fila(s)`
          )
        )} · KPI ${JSON.stringify(
          view.kpis.map(
            card => `${card.key}=${card.value}${card.raw !== null ? ` (raw ${card.raw})` : ''}`
          )
        )} · fila recall ${recall ? JSON.stringify(recall.cells.slice(0, 4)) : 'ausente'} acciones ${JSON.stringify(
          recall?.schedule ?? []
        )} · fila solicitud ${request ? JSON.stringify(request.cells.slice(0, 5)) : 'ausente'} acciones ${JSON.stringify(
          request?.schedule ?? []
        )} descartar=${request?.dismiss ?? '—'} · refresh ${view.refresh}`
        entry.request = call ? { method: call.method, path: call.path, status: call.status } : null
        entry.detail = {
          opened,
          keys,
          sections: view.sections,
          kpis: view.kpis,
          recall,
          request,
          requestActions,
          recallActions,
          kpisComplete,
          recallKpi: recallKpi?.value ?? null
        }
        entry.verdict =
          bothSections && kpisComplete && Boolean(recall) && requestActions && recallActions
            ? 'OK'
            : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : `la cola no publica las dos secciones con la fila de la sesión y sus acciones (recall ${Boolean(
                recall
              )}, solicitud ${Boolean(request)}, KPI ${view.kpis.length}, cumplimiento crudo ${
                compliance?.raw ?? 'ausente'
              })`

        return entry
      }
    )

    await step(
      'contract-schedule',
      'Abrir el modal de agenda de la solicitud y medir el contrato del diálogo',
      'Centrado, un solo h1, foco atrapado, scroll bloqueado, nombre accesible y Escape lo cierra: una fila por comprobación',
      async entry => {
        await closeDialog(page).catch(() => {})
        await dismissToasts(page).catch(() => {})

        await rowLocator(page, 'requests', token)
          .locator('[data-continuity-schedule="request"]')
          .click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        const isOpen = await dialog
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        if (!isOpen) {
          entry.obtained = 'el modal de agenda no se abrió desde la acción de la fila'
          entry.verdict = 'DEFECTO'
          entry.notes = 'la acción «Agendar» de la solicitud no pinta el diálogo'

          return entry
        }

        evidence.captures.push(await shot(page, 'shots/walk/continuity-dialog-schedule.png'))

        const contract = await contractRow(page, dialog)
        const closed = !(await dialog.isVisible().catch(() => true))

        entry.obtained = `«${contract.dialog.title}» ${JSON.stringify(contract.dialog.box)} · ${contractLine(
          contract
        )} · cerrado tras Escape ${closed}`
        entry.detail = { contract, closed }
        entry.verdict = contract.failed.length === 0 && closed ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : `el diálogo incumple ${JSON.stringify(contract.failed)}${closed ? '' : ' y Escape no lo cierra'}`

        return entry
      }
    )

    await step(
      'contract-dismiss',
      'Abrir el modal de descarte de la solicitud y medir el contrato del diálogo',
      'El mismo contrato de la primitiva: centrado, un h1, foco atrapado, scroll bloqueado, nombre accesible y Escape cierra',
      async entry => {
        await closeDialog(page).catch(() => {})
        await dismissToasts(page).catch(() => {})

        await rowLocator(page, 'requests', token)
          .locator('[data-continuity-dismiss]')
          .click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        const isOpen = await dialog
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        if (!isOpen) {
          entry.obtained = 'el modal de descarte no se abrió desde la acción de la fila'
          entry.verdict = 'DEFECTO'
          entry.notes = 'la acción «Descartar» de la solicitud no pinta el diálogo'

          return entry
        }

        evidence.captures.push(await shot(page, 'shots/walk/continuity-dialog-dismiss.png'))

        const contract = await contractRow(page, dialog)
        const closed = !(await dialog.isVisible().catch(() => true))

        entry.obtained = `«${contract.dialog.title}» ${JSON.stringify(contract.dialog.box)} · ${contractLine(
          contract
        )} · cerrado tras Escape ${closed}`
        entry.detail = { contract, closed }
        entry.verdict = contract.failed.length === 0 && closed ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : `el diálogo incumple ${JSON.stringify(contract.failed)}${closed ? '' : ' y Escape no lo cierra'}`

        return entry
      }
    )

    await step(
      'schedule-recall',
      'Agendar el recall vencido de la sesión desde la acción de su fila',
      'El modal precarga la fecha y la duración, exige profesional, sillón y tipo, el POST responde 201 y la fila sale de la sección mientras el KPI de recalls baja',
      async entry => {
        const before = await panel(page)
        const recallBefore = rowWith(before, 'recalls', token)

        if (!recallBefore || !recallId) {
          entry.obtained = `la fila del recall de la sesión no está en la cola (id ${recallId ?? 'sin id'})`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'otra ejecución resolvió el recall antes de este paso'

          return entry
        }

        await closeDialog(page).catch(() => {})
        await dismissToasts(page).catch(() => {})

        await rowLocator(page, 'recalls', token)
          .locator('[data-continuity-schedule="recall"]')
          .click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        const isOpen = await dialog
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        if (!isOpen) {
          entry.obtained = 'el modal de agenda del recall no se abrió'
          entry.verdict = 'DEFECTO'
          entry.notes = 'la acción «Agendar» del recall no pinta el diálogo'

          return entry
        }

        const prefill = await prefilled(page)

        // The modal loads its lookups when it opens. Picking before they arrive
        // measures a disabled trigger instead of the door, so the step waits for
        // the professional select to leave its loading state.
        await page
          .waitForFunction(
            () => {
              const button = document.querySelector('[data-sc-user] button')
              return Boolean(button) && !button.disabled
            },
            null,
            { timeout: 20000 }
          )
          .catch(() => {})
        await settle(page)

        const pickedProfessional = professional
          ? await pickOption(page, '[data-sc-user] button', professional.name)
          : { ok: false, reason: 'no active professional was published' }
        const pickedChair = chairPool[0]
          ? await pickOption(page, '[data-sc-chair] button', chairPool[0].name)
          : { ok: false, reason: 'no active chair was published' }
        // `readSelectOptions` closes the listbox with Escape, and the modal owns
        // that key: reading the list that way closed the dialog before the type
        // could be chosen. The type is read and picked in one opening instead.
        const pickedType = await (async () => {
          const trigger = page.locator('[data-sc-type] button').first()

          if (!typePool.length) {
            return { ok: false, reason: 'no active appointment type was published' }
          }
          if (!(await trigger.isVisible().catch(() => false))) {
            return { ok: false, reason: 'trigger not visible' }
          }

          await trigger.click({ timeout: 8000 }).catch(() => {})

          const list = page.locator('[role="listbox"]:visible').last()
          await list.waitFor({ state: 'visible', timeout: 8000 }).catch(() => {})

          const options = list.locator('button')
          const labels = (await options.allInnerTexts().catch(() => [])).map(value => value.trim())

          if (!labels.length) return { ok: false, reason: 'the type list published no option' }

          await options
            .first()
            .click({ timeout: 8000 })
            .catch(() => {})
          await list.waitFor({ state: 'hidden', timeout: 3000 }).catch(() => {})

          return { ok: true, selected: labels[0], options: labels }
        })()

        evidence.captures.push(await shot(page, 'shots/walk/continuity-schedule-recall.png'))

        const attempts = []
        let accepted = null

        // The agenda's uniqueness is per professional and window, so a slot an
        // earlier run burned stays burned. The first attempt is the door's own
        // preferred date (the measured default); the rest move the minute with
        // the run token so a repeated run still finds a free slot.
        const tokenMinute = 1 + (Number(String(fixtures.token).slice(-4)) % 58)
        const recallSlots = [
          ...new Set([prefill.scheduledAt, ...nextSlots(prefill.scheduledAt, tokenMinute)])
        ].filter(Boolean)

        for (const slot of recallSlots) {
          await page
            .locator('[data-sc-scheduled-at] input')
            .fill(slot)
            .catch(() => {})
          await settle(page)

          const enabled = await page
            .locator('[data-schedule-submit]')
            .isEnabled()
            .catch(() => false)

          if (!enabled) {
            attempts.push({ slot, status: null, reason: 'submit deshabilitado' })
            break
          }

          const sent = await respondTo(
            page,
            { method: 'POST', path: `/api/patient-recalls/${recallId}/schedule` },
            () => page.locator('[data-schedule-submit]').click({ timeout: 8000 }),
            20000
          )

          attempts.push({
            slot,
            status: sent.status,
            errors: sent.body?.errors ?? null,
            message: sent.body?.meta?.message ?? sent.body?.message ?? null
          })

          if (sent.status === 200 || sent.status === 201) {
            accepted = { ...sent, slot }
            break
          }

          // A slot the agenda refused is retried with the next candidate, and so
          // is a server error: the duplicate-slot crash is a product finding, not
          // a reason to leave the success door unmeasured. Any other message is a
          // different rule and is reported as it landed.
          if (sent.status < 500 && !sent.body?.errors?.scheduled_at) break
        }

        if (!accepted) {
          entry.obtained = `precarga ${JSON.stringify(prefill)} · profesional ${JSON.stringify(
            pickedProfessional
          )} · sillón ${JSON.stringify(pickedChair)} · tipo ${JSON.stringify(
            pickedType
          )} · intentos ${JSON.stringify(attempts)} · la fila sigue en la cola`
          entry.request = attempts.length
            ? {
                method: 'POST',
                path: `/api/patient-recalls/${recallId}/schedule`,
                status: attempts.at(-1).status
              }
            : null
          entry.detail = { prefill, pickedProfessional, pickedChair, pickedType, attempts }
          entry.verdict = 'DEFECTO'
          entry.notes = 'ningún hueco propuesto fue aceptado: el recall no sale de la cola'

          return entry
        }

        evidence.created.recallAppointmentId = accepted.body?.data?.appointment?.id ?? null
        evidence.created.scheduledSlots.push(accepted.slot)

        await page
          .waitForFunction(
            value =>
              !(
                document.querySelector('[data-continuity-section="recalls"]')?.innerText ?? ''
              ).includes(value),
            token,
            { timeout: 10000 }
          )
          .catch(() => {})
        await settle(page)

        const after = await panel(page)
        const stillQueued = Boolean(rowWith(after, 'recalls', token))
        const closeCheck = await page
          .locator(DIALOG)
          .last()
          .isVisible()
          .catch(() => false)
        const toast = await toastText(page)

        evidence.captures.push(await shot(page, 'shots/walk/continuity-queue-after-recall.png'))

        const kpiBefore = kpiOf(before, 'recalls')?.value ?? null
        const kpiAfter = kpiOf(after, 'recalls')?.value ?? null

        entry.obtained = `precarga fecha «${prefill.scheduledAt}» duración «${prefill.duration}» tipo «${
          prefill.type
        }» · hueco aceptado ${accepted.slot} · POST ${accepted.status} «${
          accepted.body?.meta?.message ?? '—'
        }» · intentos ${attempts.length} · fila en la sección ${stillQueued} · KPI recalls ${kpiBefore} → ${kpiAfter} · modal abierto ${closeCheck} · toast «${toast}»`
        entry.request = requestOf(accepted)
        entry.detail = {
          prefill,
          pickedProfessional,
          pickedChair,
          pickedType,
          attempts,
          stillQueued,
          kpiBefore,
          kpiAfter,
          toast,
          afterSections: after.sections.map(section => ({
            key: section.key,
            rows: section.rows.length,
            empty: section.empty
          }))
        }
        entry.verdict = !stillQueued && kpiBefore !== kpiAfter && Boolean(toast) ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : `el recall no sale de la sección, el KPI no se mueve o el aviso no aparece (fila ${stillQueued}, KPI ${kpiBefore} → ${kpiAfter})`

        return entry
      }
    )

    await step(
      'conflict',
      'Agendar la solicitud sobre el hueco que ya ocupa la cita de fixtures.conflict',
      'La API responde 422, el mensaje cae en su campo del formulario y la solicitud sigue pendiente en la cola (S5)',
      async entry => {
        if (!requestId || !conflict) {
          entry.obtained = `sin solicitud (${requestId}) o sin hueco de conflicto (${Boolean(conflict)})`
          entry.verdict = 'NO PROBADO'
          entry.notes = 'la sesión no pudo sembrar la solicitud o el hueco ocupado'

          return entry
        }

        const conflictProfessional = activeUsers.find(row => row.id === conflict.userId) ?? null
        const conflictChair = activeChairs.find(row => row.id === conflict.chairId) ?? null
        const slot = wallClock(conflict.scheduledAt)
        const instant = instantOf(conflict.scheduledAt)
        const past = instant ? instant.getTime() <= Date.now() + 60000 : null

        // The previous door can leave its dialog open after a refusal; a row
        // action behind it is not clickable and would measure nothing.
        await closeDialog(page).catch(() => {})
        await dismissToasts(page).catch(() => {})

        await rowLocator(page, 'requests', token)
          .locator('[data-continuity-schedule="request"]')
          .click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        const isOpen = await dialog
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        if (!isOpen) {
          entry.obtained = 'el modal de agenda de la solicitud no se abrió'
          entry.verdict = 'DEFECTO'
          entry.notes = 'la acción «Agendar» de la solicitud no pinta el diálogo'

          return entry
        }

        // The modal loads its lookups when it opens; picking before they arrive
        // measures a disabled trigger instead of the door.
        await page
          .waitForFunction(
            () => {
              const button = document.querySelector('[data-sc-user] button')
              return Boolean(button) && !button.disabled
            },
            null,
            { timeout: 20000 }
          )
          .catch(() => {})
        await settle(page)

        const pickedProfessional = conflictProfessional
          ? await pickOption(page, '[data-sc-user] button', conflictProfessional.name)
          : professional
            ? await pickOption(page, '[data-sc-user] button', professional.name)
            : {
                ok: false,
                reason: `the professional ${conflict.userId} is not in /api/users/active`
              }
        const pickedChair = conflictChair
          ? await pickOption(page, '[data-sc-chair] button', conflictChair.name)
          : { ok: false, reason: `no active chair ${conflict.chairId} was published` }

        await page
          .locator('[data-sc-scheduled-at] input')
          .fill(slot)
          .catch(() => {})
        await settle(page)

        const prefill = await prefilled(page)

        // A request may leave the type empty, and the door then falls back to the
        // one the professional left; the form still needs one to submit.
        if (/seleccionar/i.test(String(prefill.type ?? '')) && typePool[0]) {
          await pickOption(page, '[data-sc-type] button', typePool[0].name)
        }

        const sent = await respondTo(
          page,
          { method: 'POST', path: `/api/continuity-requests/${requestId}/schedule` },
          () => page.locator('[data-schedule-submit]').click({ timeout: 8000 }),
          20000
        )

        const errors = await scheduleErrors(page)
        evidence.captures.push(await shot(page, 'shots/walk/continuity-conflict.png'))

        await closeDialog(page)
        const refreshed = await waitForQueue(page, () =>
          page.locator('[data-continuity-refresh]').click({ timeout: 8000 })
        )
        await settle(page)

        const view = await panel(page)
        const stillQueued = Boolean(rowWith(view, 'requests', token))
        const live = await api(session, 'GET', QUEUE)
        const liveRows = listOf(live.body?.data?.requests)
        const liveRow = liveRows.find(row => row.id === requestId) ?? null

        const fieldOf = errors.filter(row => row.text).map(row => row.field)
        const conflictLanded = errors.some(
          row => row.field === 'scheduled_at' && /conflicto de horario/i.test(String(row.text))
        )

        entry.obtained = `hueco ${JSON.stringify(conflict.scheduledAt)} → «${slot}» (profesional ${
          conflict.userId
        } / sillón ${conflict.chairId}) ${past ? 'YA PASADO al medir' : ''} · POST ${sent.status ?? 'sin petición'} · campos con mensaje ${JSON.stringify(
          errors
        )} · solicitud en la sección ${stillQueued} · GET ${live.status} estado «${
          liveRow?.status ?? 'ausente'
        }» · refresco ${refreshed.status ?? 'sin petición'}`
        entry.request = requestOf(sent)
        entry.detail = {
          slot,
          past,
          conflict,
          pickedProfessional,
          pickedChair,
          prefill,
          status: sent.status,
          body: sent.body ?? null,
          errors,
          fieldOf,
          conflictLanded,
          stillQueued,
          liveStatus: live.status,
          liveRow: liveRow ? { id: liveRow.id, status: liveRow.status } : null
        }
        entry.verdict =
          sent.status === 422 && conflictLanded && stillQueued && liveRow?.status === 'pending'
            ? 'OK'
            : sent.status === 422 && stillQueued
              ? 'CONFUSO'
              : 'DEFECTO'
        entry.notes =
          sent.status === 422 && stillQueued
            ? conflictLanded
              ? ''
              : `el 422 no nombra el conflicto en el campo de la fecha: ${JSON.stringify(errors)}`
            : 'el hueco ocupado no se rechaza o la solicitud deja la cola'

        return entry
      }
    )

    await step(
      'dismiss-blank',
      'Abrir el descarte con el motivo vacío y forzar el envío',
      'El botón empieza deshabilitado, forzar el envío reporta «El motivo es obligatorio.» en su campo y no sale ninguna petición',
      async entry => {
        if (!requestId) {
          entry.obtained = 'la sesión no tiene solicitud de continuidad pendiente'
          entry.verdict = 'NO PROBADO'
          entry.notes = 'sin solicitud no hay modal de descarte que medir'

          return entry
        }

        const postsBefore = requests.filter(
          row =>
            row.method === 'POST' && row.path === `/api/continuity-requests/${requestId}/dismiss`
        ).length

        await rowLocator(page, 'requests', token)
          .locator('[data-continuity-dismiss]')
          .click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        const isOpen = await dialog
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        if (!isOpen) {
          entry.obtained = 'el modal de descarte no se abrió'
          entry.verdict = 'DEFECTO'
          entry.notes = 'la acción «Descartar» no pinta el diálogo'

          return entry
        }

        const initial = await dismissState(page)

        // Enter inside a textarea inserts a newline instead of submitting, so the
        // keypress is measured and the submit is then forced the way the browser
        // does it (`requestSubmit`).
        await page
          .locator('[data-dismiss-reason]')
          .click({ timeout: 5000 })
          .catch(() => {})
        await page.keyboard.press('Enter').catch(() => {})
        await page.waitForTimeout(250)
        const afterEnter = await dismissState(page)

        const forced = await page.evaluate(() => {
          const form = document.querySelector('[data-dismiss-form]')
          if (!form) return { ok: false, reason: 'no form' }
          form.requestSubmit()

          return { ok: true }
        })
        await page.waitForTimeout(400)
        const afterForce = await dismissState(page)

        evidence.captures.push(await shot(page, 'shots/walk/continuity-dismiss-blank.png'))

        const postsAfter = requests.filter(
          row =>
            row.method === 'POST' && row.path === `/api/continuity-requests/${requestId}/dismiss`
        ).length

        await closeDialog(page)
        const view = await panel(page)
        const stillQueued = Boolean(rowWith(view, 'requests', token))

        entry.obtained = `submit deshabilitado ${initial.disabled} · tras Enter en el textarea: error «${
          afterEnter.error ?? '—'
        }», motivo «${afterEnter.reason ?? ''}» · forzado ${forced.ok} → error «${
          afterForce.error ?? '—'
        }» · POST ${postsBefore} → ${postsAfter} · solicitud en la cola ${stillQueued}`
        entry.request = null
        entry.detail = {
          initial,
          afterEnter,
          forced,
          afterForce,
          postsBefore,
          postsAfter,
          stillQueued
        }
        entry.verdict =
          initial.disabled === true &&
          afterForce.error === REASON_REQUIRED &&
          postsAfter === postsBefore &&
          stillQueued
            ? 'OK'
            : 'DEFECTO'

        if (entry.verdict !== 'OK') {
          entry.notes =
            'el descarte en blanco no reporta el motivo, dispara una petición o retira la fila'
        } else if (!afterEnter.error) {
          entry.notes =
            'Enter dentro del textarea no envía el formulario (inserta un salto de línea): la regla solo se ve al forzar el submit'
        }

        return entry
      }
    )

    await step(
      'dismiss',
      'Descartar la solicitud con su motivo',
      'El POST responde 200, la solicitud sale de la cola y el KPI de solicitudes baja',
      async entry => {
        if (!requestId) {
          entry.obtained = 'la sesión no tiene solicitud de continuidad pendiente'
          entry.verdict = 'NO PROBADO'
          entry.notes = 'sin solicitud no hay descarte que medir'

          return entry
        }

        const before = await panel(page)
        const reason = `AUDIT-U10-${token} descarte del recorrido de la capa 4`

        await rowLocator(page, 'requests', token)
          .locator('[data-continuity-dismiss]')
          .click({ timeout: 8000 })

        const dialog = page.locator(DIALOG).last()
        const isOpen = await dialog
          .waitFor({ state: 'visible', timeout: 12000 })
          .then(() => true)
          .catch(() => false)

        if (!isOpen) {
          entry.obtained = 'el modal de descarte no se abrió'
          entry.verdict = 'DEFECTO'
          entry.notes = 'la acción «Descartar» no pinta el diálogo'

          return entry
        }

        await page
          .locator('[data-dismiss-reason]')
          .fill(reason)
          .catch(() => {})
        await settle(page)

        const enabled = await page
          .locator('[data-dismiss-submit]')
          .isEnabled()
          .catch(() => false)

        const sent = await respondTo(
          page,
          { method: 'POST', path: `/api/continuity-requests/${requestId}/dismiss` },
          () => page.locator('[data-dismiss-submit]').click({ timeout: 8000 }),
          20000
        )

        await page
          .waitForFunction(
            value =>
              !(
                document.querySelector('[data-continuity-section="requests"]')?.innerText ?? ''
              ).includes(value),
            token,
            { timeout: 10000 }
          )
          .catch(() => {})
        await settle(page)

        const after = await panel(page)
        const stillQueued = Boolean(rowWith(after, 'requests', token))
        const kpiBefore = kpiOf(before, 'requests')?.value ?? null
        const kpiAfter = kpiOf(after, 'requests')?.value ?? null

        evidence.created.dismissed = sent.status === 200

        evidence.captures.push(await shot(page, 'shots/walk/continuity-dismissed.png'))

        entry.obtained = `submit habilitado con motivo ${enabled} · POST ${sent.status ?? 'sin petición'} «${
          sent.body?.meta?.message ?? sent.body?.message ?? '—'
        }» · estado «${sent.body?.data?.status ?? '—'}» · motivo guardado «${
          sent.body?.data?.resolution_notes ?? '—'
        }» · fila en la sección ${stillQueued} · KPI solicitudes ${kpiBefore} → ${kpiAfter}`
        entry.request = requestOf(sent)
        entry.detail = {
          reason,
          enabled,
          status: sent.status,
          body: sent.body ?? null,
          stillQueued,
          kpiBefore,
          kpiAfter
        }
        entry.verdict =
          sent.status === 200 && !stillQueued && kpiBefore !== kpiAfter ? 'OK' : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : 'el descarte no responde 200, la fila sigue en la cola o el KPI no se mueve'

        return entry
      }
    )

    await step(
      'empty',
      'Refrescar la cola con las filas de la sesión ya resueltas',
      'Las dos secciones pintan su vacío declarado con título y explicación',
      async entry => {
        const refreshed = await waitForQueue(page, () =>
          page.locator('[data-continuity-refresh]').click({ timeout: 8000 })
        )
        await settle(page)

        const view = await panel(page)
        const recalls = view.sections.find(section => section.key === 'recalls') ?? null
        const requests = view.sections.find(section => section.key === 'requests') ?? null
        const others = {
          recalls: recalls?.rows.length ?? 0,
          requests: requests?.rows.length ?? 0
        }
        const painted = Boolean(recalls?.empty) && Boolean(requests?.empty)

        evidence.captures.push(await shot(page, 'shots/walk/continuity-empty.png'))

        entry.obtained = `GET ${refreshed.status ?? 'sin petición'} · recalls: ${
          recalls?.empty
            ? `«${recalls.empty.title}» — «${recalls.empty.description}»`
            : `${others.recalls} fila(s)`
        } · solicitudes: ${
          requests?.empty
            ? `«${requests.empty.title}» — «${requests.empty.description}»`
            : `${others.requests} fila(s)`
        }`
        entry.request = refreshed.status
          ? { method: 'GET', path: QUEUE, status: refreshed.status }
          : null
        entry.detail = { others, recalls, requests, painted }
        entry.verdict =
          painted && others.recalls === 0 && others.requests === 0 ? 'OK' : 'NO PROBADO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : others.recalls + others.requests > 0
              ? `otra ejecución mantiene ${others.recalls} recall(s) y ${others.requests} solicitud(es) en la cola: el vacío no se puede forzar sin romper la población`
              : 'las secciones no pintan su vacío declarado'

        return entry
      }
    )

    await step(
      'read-only',
      'Abrir /continuity como odontólogo y probar la puerta de escritura',
      'La cola se pinta para el rol clínico, ninguna acción de escritura aparece y el POST de agenda responde 403 con su token',
      async entry => {
        await primeSession(page, readOnly)
        const visited = await gotoSurface(page, SURFACE)
        await settle(page)

        const view = await panel(page)
        const scheduleCount = await page.locator('[data-continuity-schedule]').count()
        const dismissCount = await page.locator('[data-continuity-dismiss]').count()

        const door = requestId
          ? await api(readOnly, 'POST', `/api/continuity-requests/${requestId}/schedule`, {})
          : null

        evidence.captures.push(await shot(page, 'shots/walk/continuity-read-only.png'))

        const painted = view.sections.length === 2 || view.kpis.length === 4

        entry.obtained = `HTTP ${visited.status} · secciones ${view.sections.length} · KPI ${view.kpis.length} · acciones de agenda ${scheduleCount} · descartar ${dismissCount} · POST agenda ${
          door ? door.status : 'sin solicitud que probar'
        } ${door?.body?.message ? `«${door.body.message}»` : ''}`
        entry.request = door
          ? {
              method: 'POST',
              path: `/api/continuity-requests/${requestId}/schedule`,
              status: door.status
            }
          : null
        entry.detail = {
          visited,
          sections: view.sections.map(section => ({ key: section.key, rows: section.rows.length })),
          kpis: view.kpis.map(card => `${card.key}=${card.value}`),
          scheduleCount,
          dismissCount,
          door: door ? { status: door.status, message: door.body?.message ?? null } : null,
          painted
        }
        entry.verdict =
          painted && scheduleCount === 0 && dismissCount === 0 && door?.status === 403
            ? 'OK'
            : !door
              ? 'NO PROBADO'
              : 'DEFECTO'
        entry.notes =
          entry.verdict === 'OK'
            ? ''
            : !door
              ? 'la sesión no dejó una solicitud con la que probar la puerta'
              : `la cola no se pinta (${painted}) o el rol clínico alcanza la escritura (${scheduleCount}/${dismissCount} acciones, POST ${door.status})`

        return entry
      }
    )
  } finally {
    await dismissToasts(page).catch(() => {})
    await closeDialog(page).catch(() => {})

    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.consoleErrors = diagnostics.consoleErrors
    // The conflict step asks the door for a slot the agenda already holds, and
    // the browser logs that 422 as a console error: it is the measurement, not a
    // page defect.
    evidence.consoleClassified = classifyConsole(diagnostics.consoleErrors, ROLE, [
      ...declared,
      {
        pattern: /Failed to load resource: the server responded with a status of 422/,
        reason: 'el recorrido provoca el 422 del conflicto de agenda'
      }
    ])
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, ROLE)

    markExpected(evidence.failedRequests, declaredFailure.predicate, declaredFailure.reason)

    evidence.traffic = {
      api: requests.length,
      queueReads: requests.filter(row => row.path === QUEUE).length,
      writes: requests
        .filter(row => row.method === 'POST')
        .map(row => `${row.method} ${row.path} → ${row.status ?? 'sin respuesta'}`),
      withoutStatus: requests.filter(row => row.status === null).length
    }
    evidence.counts = walkCounts(evidence.steps)

    audit('walk-continuity', evidence)
  }

  expect(evidence.pageErrors, 'page errors del recorrido de continuidad').toEqual([])
  expect(
    evidence.steps.map(entry => entry.id),
    'pasos declarados del recorrido de continuidad'
  ).toEqual([
    '01-queue',
    '02-contract-schedule',
    '03-contract-dismiss',
    '04-schedule-recall',
    '05-conflict',
    '06-dismiss-blank',
    '07-dismiss',
    '08-empty',
    '09-read-only'
  ])
  expect(
    evidence.steps.every(entry => Boolean(entry.shot)),
    'cada paso del recorrido de continuidad deja su captura'
  ).toBe(true)

  return evidence
}

test('@U10 · capa 4 — Continuidad: la cola, el conflicto y el descarte', async ({ page }) => {
  test.setTimeout(300000)

  const fixtures = await ensureFixtures()

  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  await walkThroughContinuity({ page, fixtures })
})
