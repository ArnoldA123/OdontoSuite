// Plan #13 (issue #371, U8) — layer 4 on `/procedure-stats`.
//
// The usage report is the fourth surface of the module and the one the issue
// uses as the comparison partner of the catalogue list: presets, Top N, the
// period gate, the empty state with its "Ampliar a 90 días" action, the two
// charts and the CSV export.
//
// The CSV is compared row by row against what the screen rendered — the table
// and the specialty distribution — and a disagreement is recorded as the
// measurement it is, with both sides in the evidence. The file is read from the
// blob the page hands to `URL.createObjectURL`, so nothing is written to disk.

import { test, expect } from 'playwright/test'

import {
  classifyFailures,
  collectDiagnostics,
  loginAs,
  primeSession,
  settleSurface
} from '../harness.js'

import { ensureFixtures, fixtureSummary } from './fixtures.js'

import {
  DESKTOP,
  LOAD_TIMEOUT,
  MOBILE,
  STATS_ROUTE,
  THRESHOLD,
  UI,
  addDaysLocal,
  audit,
  call as callApi,
  captureCsvExport,
  classifyConsole,
  closeAllDialogs,
  fillControl,
  localDay,
  openSelect,
  parseCsv,
  readStats,
  recorder,
  settleStats,
  stamp,
  toNumber,
  walkTotals,
  watchRequests
} from './helpers.js'

const statsCalls = entries =>
  entries.filter(entry => entry.method === 'GET' && entry.path === '/api/admin/procedure-stats')

const lastStatsCall = entries => statsCalls(entries).at(-1) ?? null

const waitForStats = (page, matcher, action, timeout = LOAD_TIMEOUT) => {
  const pending = page
    .waitForResponse(
      response => {
        if (response.request().method() !== 'GET') return false
        try {
          const url = new URL(response.url())
          return url.pathname === '/api/admin/procedure-stats' && matcher(url.searchParams)
        } catch {
          return false
        }
      },
      { timeout }
    )
    .catch(() => null)

  return Promise.resolve(action()).then(() => pending)
}

/** "2 usos · S/ 950.00" → the two numbers the line carries. */
const usageOfLine = line => {
  const raw = String(line ?? '').match(/([\d.,]+)\s*usos/i)?.[1] ?? null
  return raw === null ? null : Number(raw.replace(/[.,]/g, ''))
}

const revenueOfLine = line => {
  const raw = String(line ?? '').match(/S\/\s*([\d.,]+)/)?.[1] ?? null
  return raw === null ? null : toNumber(raw)
}

/** Compares the export against the rendered table and the distribution. */
const compareExport = (stats, csvText) => {
  const rows = parseCsv(csvText ?? '')
  const header = rows[0] ?? []
  const body = rows.slice(1)
  const procedureRows = body.filter(row => row[0] === 'procedimiento')
  const specialtyRows = body.filter(row => row[0] === 'especialidad')

  const procedureDiffs = []
  stats.topRows.forEach((row, index) => {
    const csv = procedureRows[index]
    const expected = {
      code: row.code,
      name: row.name,
      usage: toNumber(row.usage),
      quantity: toNumber(row.quantity),
      revenue: toNumber(row.revenue)
    }
    const observed = csv
      ? {
          code: csv[1],
          name: csv[2],
          usage: toNumber(csv[3]),
          quantity: toNumber(csv[4]),
          revenue: toNumber(csv[5])
        }
      : null

    if (!observed) {
      procedureDiffs.push({ index, reason: 'la fila no está en el CSV', expected })
      return
    }

    for (const key of ['code', 'name', 'usage', 'quantity', 'revenue']) {
      if (observed[key] !== expected[key]) {
        procedureDiffs.push({ index, field: key, expected: expected[key], observed: observed[key] })
      }
    }
  })

  if (procedureRows.length !== stats.topRows.length) {
    procedureDiffs.push({
      reason: 'el CSV tiene otra cantidad de filas de procedimiento',
      expected: stats.topRows.length,
      observed: procedureRows.length
    })
  }

  const specialtyDiffs = []
  const totalUsage = stats.specialtyRows.reduce(
    (total, row) => total + (usageOfLine(row.usageLine) ?? 0),
    0
  )

  stats.specialtyRows.forEach((row, index) => {
    const csv = specialtyRows[index]
    const expectedUsage = usageOfLine(row.usageLine)
    const expectedRevenue = revenueOfLine(row.usageLine)
    const expectedShare = totalUsage > 0 ? Math.round((expectedUsage / totalUsage) * 100) : 0
    const observed = csv
      ? {
          code: csv[1],
          name: csv[2],
          usage: toNumber(csv[3]),
          revenue: toNumber(csv[5])
        }
      : null

    if (!observed) {
      specialtyDiffs.push({ index, reason: 'la especialidad no está en el CSV', expectedUsage })
      return
    }

    for (const [key, value] of [
      ['name', row.name],
      ['usage', expectedUsage],
      ['revenue', expectedRevenue]
    ]) {
      if (observed[key] !== value) {
        specialtyDiffs.push({ index, field: key, expected: value, observed: observed[key] })
      }
    }

    if (row.share !== null && row.share !== undefined) {
      const claimed = row.share === '<1%' ? 0 : toNumber(row.share)
      if (claimed !== expectedShare) {
        specialtyDiffs.push({
          index,
          field: 'share',
          expected: `${expectedShare}%`,
          observed: row.share
        })
      }
    }
  })

  if (specialtyRows.length !== stats.specialtyRows.length) {
    specialtyDiffs.push({
      reason: 'el CSV tiene otra cantidad de filas de especialidad',
      expected: stats.specialtyRows.length,
      observed: specialtyRows.length
    })
  }

  return {
    header,
    counts: {
      procedureRows: procedureRows.length,
      specialtyRows: specialtyRows.length,
      renderedProcedures: stats.topRows.length,
      renderedSpecialties: stats.specialtyRows.length
    },
    procedureDiffs,
    specialtyDiffs,
    matches: procedureDiffs.length === 0 && specialtyDiffs.length === 0
  }
}

test('@U8 · capa 4 — las estadísticas del catálogo: presets, Top N, CSV y vacío', async ({
  page,
  request
}) => {
  test.setTimeout(360000)

  const session = await loginAs(request, 'administrador')
  const fixtures = await ensureFixtures(request)
  if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

  const today = localDay()
  const diagnostics = collectDiagnostics(page)
  const requests = watchRequests(page)
  const state = { pageErrors: diagnostics.pageErrors, currentStep: null }
  const evidence = {
    ...stamp(),
    route: STATS_ROUTE,
    role: 'administrador',
    shotDir: 'walk/stats',
    steps: [],
    findings: [],
    fixtures: fixtureSummary(fixtures),
    pageErrors: diagnostics.pageErrors,
    consoleErrors: diagnostics.consoleErrors,
    failedRequests: { expected: [], unexpected: [] }
  }
  const step = recorder(page, evidence, state)

  try {
    await page.setViewportSize(DESKTOP)
    page.setDefaultTimeout(7000)
    await primeSession(page, session)
    await page.goto(STATS_ROUTE)
    await settleStats(page)
    await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)

    await step(
      'stats-default',
      'Abrir /procedure-stats con su período por omisión',
      'La tira de KPIs, el top de procedimientos, la distribución y los dos gráficos se pintan',
      async entry => {
        const data = await readStats(page)
        const call = lastStatsCall(requests)
        const chartsDrawn = [data.charts.usage, data.charts.specialty].every(
          chart => chart && chart.width > 0 && chart.height > 0
        )
        entry.obtained = `período «${data.periodApplied}» · top ${data.topCount} · especialidades ${data.specialtyCount} · KPIs ${JSON.stringify(data.kpis)} · lienzos ${JSON.stringify(data.charts)}`
        entry.requestStatus = call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.detail = { periodApplied: data.periodApplied, kpis: data.kpis, charts: data.charts }
        entry.verdict =
          data.topCount > 0 && data.specialtyCount > 0 && chartsDrawn && call?.status === 200
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'preset-7d',
      'Aplicar el preset «7 días»',
      'El preset pide los últimos siete días y la superficie vuelve a pintar sus tres bloques',
      async entry => {
        const expectedFrom = addDaysLocal(today, -6)
        const response = await waitForStats(
          page,
          params => params.get('from') === expectedFrom && params.get('to') === today,
          () => page.locator(UI.stats.preset('7d')).click({ timeout: 8000 })
        )
        await settleStats(page)
        const data = await readStats(page)
        const call = lastStatsCall(requests)
        entry.obtained = `«${data.periodApplied}» · desde ${call?.query?.from} hasta ${call?.query?.to} · top ${data.topCount} · especialidades ${data.specialtyCount} · preset activo ${data.selectedPreset}`
        entry.requestStatus = response?.status() ?? call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.detail = { expectedFrom, kpis: data.kpis, topCount: data.topCount }
        entry.verdict =
          call?.query?.from === expectedFrom &&
          call?.query?.to === today &&
          data.selectedPreset === '7d'
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'preset-90d',
      'Aplicar el preset «90 días»',
      'El preset pide noventa días y el período aplicado lo declara',
      async entry => {
        const expectedFrom = addDaysLocal(today, -89)
        const response = await waitForStats(
          page,
          params => params.get('from') === expectedFrom,
          () => page.locator(UI.stats.preset('90d')).click({ timeout: 8000 })
        )
        await settleStats(page)
        const data = await readStats(page)
        const call = lastStatsCall(requests)
        entry.obtained = `«${data.periodApplied}» · desde ${call?.query?.from} · preset ${data.selectedPreset}`
        entry.requestStatus = response?.status() ?? call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.verdict =
          call?.query?.from === expectedFrom && data.selectedPreset === '90d' ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'topn-5',
      'Pedir el Top 5',
      'La tabla del top respeta el límite pedido y el encabezado lo declara',
      async entry => {
        const response = await waitForStats(
          page,
          params => params.get('limit') === '5',
          async () => {
            await openSelect(page, `${UI.stats.limit} button`, '5')
            await page.locator(UI.stats.apply).click({ timeout: 8000 })
          }
        )
        await settleStats(page)
        const data = await readStats(page)
        const call = lastStatsCall(requests)
        entry.obtained = `Top ${data.topCount} · encabezado «${data.topHeading}» · limit=${call?.query?.limit}`
        entry.requestStatus = response?.status() ?? call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.detail = { rows: data.topRows.map(row => row.rank) }
        // The heading names the rows the period actually has ("Top 3" with three
        // procedures), not the limit asked for: that is the honest label.
        entry.verdict =
          call?.query?.limit === '5' &&
          data.topCount <= 5 &&
          data.topHeading === `Top ${data.topCount} procedimientos más usados`
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'topn-50',
      'Pedir el Top 50',
      'El límite vuelve al máximo y la tabla publica todas las filas del período',
      async entry => {
        const response = await waitForStats(
          page,
          params => params.get('limit') === '50',
          async () => {
            await openSelect(page, `${UI.stats.limit} button`, '50')
            await page.locator(UI.stats.apply).click({ timeout: 8000 })
          }
        )
        await settleStats(page)
        const data = await readStats(page)
        const call = lastStatsCall(requests)
        const api = await callApi(request, session, 'GET', '/api/admin/procedure-stats?limit=50')
        const apiTop = (api.body?.data?.top_procedures ?? []).length
        entry.obtained = `Top ${data.topCount} · limit=${call?.query?.limit} · la API publica ${apiTop} fila(s)`
        entry.requestStatus = response?.status() ?? call?.status ?? null
        entry.request = call ? { method: call.method, path: call.path, query: call.query } : null
        entry.detail = { apiTop, headings: data.topHeading }
        entry.verdict = call?.query?.limit === '50' && data.topCount === apiTop ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'range-invalid',
      'Escribir un rango con la fecha final anterior a la inicial',
      'La superficie marca el rango en línea, deshabilita el envío y no consulta',
      async entry => {
        await fillControl(page, UI.stats.from, addDaysLocal(today, -1))
        await fillControl(page, UI.stats.to, addDaysLocal(today, -10))
        await page.waitForTimeout(400)
        const before = statsCalls(requests).length
        const data = await readStats(page)
        await page
          .locator(UI.stats.apply)
          .click({ timeout: 6000 })
          .catch(() => {})
        await page.waitForTimeout(600)
        const after = statsCalls(requests).length

        entry.obtained = `mensaje «${data.rangeError}» · aplicar deshabilitado ${data.applyDisabled} · consultas ${before} → ${after}`
        entry.requestStatus = null
        entry.detail = {
          rangeError: data.rangeError,
          applyDisabled: data.applyDisabled,
          before,
          after
        }
        entry.verdict =
          Boolean(data.rangeError) && data.applyDisabled === true && after === before
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )

    await step(
      'empty-period',
      'Consultar un período sin uso registrado',
      'La superficie muestra su estado vacío con la acción de ampliar el período',
      async entry => {
        await fillControl(page, UI.stats.from, '2020-01-01')
        await fillControl(page, UI.stats.to, '2020-01-02')
        const response = await waitForStats(
          page,
          params => params.get('from') === '2020-01-01',
          () => page.locator(UI.stats.apply).click({ timeout: 8000 })
        )
        await settleStats(page)
        const data = await readStats(page)
        entry.obtained = `vacío ${JSON.stringify(data.empty?.text ?? null)} · acciones ${JSON.stringify(data.emptyActions)} · exportar deshabilitado ${data.exportDisabled}`
        entry.requestStatus = response?.status() ?? null
        entry.detail = { empty: data.empty, emptyActions: data.emptyActions }
        entry.verdict =
          data.empty?.present && data.emptyActions.includes('Ampliar a 90 días') ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'widen-90',
      'Pulsar «Ampliar a 90 días» en el estado vacío',
      'La acción aplica el preset de 90 días y el período vuelve a tener datos',
      async entry => {
        const expectedFrom = addDaysLocal(today, -89)
        const response = await waitForStats(
          page,
          params => params.get('from') === expectedFrom,
          () => page.locator(UI.stats.widen).click({ timeout: 8000 })
        )
        await settleStats(page)
        const data = await readStats(page)
        entry.obtained = `«${data.periodApplied}» · top ${data.topCount} · vacío ${Boolean(data.empty?.present)}`
        entry.requestStatus = response?.status() ?? null
        entry.detail = { kpis: data.kpis, topCount: data.topCount }
        entry.verdict = data.topCount > 0 && !data.empty?.present ? 'OK' : 'DEFECTO'
        return entry
      }
    )

    await step(
      'csv-vs-screen',
      'Descargar el CSV y compararlo con la tabla y la distribución',
      'Cada fila del CSV coincide con lo que la pantalla muestra, procedimiento y especialidad',
      async entry => {
        const data = await readStats(page)
        const exported = await captureCsvExport(page, UI.stats.exportCsv)
        const comparison = exported.ok ? compareExport(data, exported.text) : null

        entry.obtained = exported.ok
          ? `CSV «${exported.filename}» con ${comparison.counts.procedureRows} procedimiento(s) y ${comparison.counts.specialtyRows} especialidad(es) · pantalla ${comparison.counts.renderedProcedures}/${comparison.counts.renderedSpecialties} · diferencias ${JSON.stringify([...comparison.procedureDiffs, ...comparison.specialtyDiffs].slice(0, 6))}`
          : `no se pudo leer el CSV: ${exported.reason}`
        entry.requestStatus = null
        entry.detail = {
          filename: exported.filename ?? null,
          header: comparison?.header ?? null,
          counts: comparison?.counts ?? null,
          procedureDiffs: comparison?.procedureDiffs ?? [],
          specialtyDiffs: comparison?.specialtyDiffs ?? [],
          bytes: exported.text?.length ?? 0
        }
        entry.verdict = !exported.ok ? 'NO PROBADO' : comparison.matches ? 'OK' : 'DEFECTO'
        entry.reason = !exported.ok
          ? exported.reason
          : comparison.matches
            ? null
            : 'el CSV y la pantalla no publican la misma tabla'
        return entry
      }
    )

    await step(
      'mobile-390',
      'Medir /procedure-stats a 390 px',
      'El documento no desborda y los gráficos siguen dibujados',
      async entry => {
        await page.setViewportSize(MOBILE)
        await page.reload()
        await settleStats(page)
        await settleSurface(page, THRESHOLD, LOAD_TIMEOUT)
        const measure = await page.evaluate(() => {
          const canvas = Array.from(document.querySelectorAll('[data-chart]')).map(node => ({
            key: node.getAttribute('data-chart'),
            width: node.width,
            height: node.height
          }))
          return {
            documentOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
            scrollWidth: document.documentElement.scrollWidth,
            innerWidth: window.innerWidth,
            canvas
          }
        })
        entry.obtained = `desborde ${measure.documentOverflow} (${measure.scrollWidth}/${measure.innerWidth}) · lienzos ${JSON.stringify(measure.canvas)}`
        entry.detail = measure
        entry.verdict =
          measure.documentOverflow === false &&
          measure.canvas.length === 2 &&
          measure.canvas.every(chart => chart.width > 0)
            ? 'OK'
            : 'DEFECTO'
        return entry
      }
    )
  } finally {
    evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
    evidence.consoleErrors = diagnostics.consoleErrors
    evidence.consoleClassified = classifyConsole(diagnostics.consoleErrors)
    evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
    evidence.totals = walkTotals(evidence)
    audit('walk-stats.json', evidence)

    await closeAllDialogs(page).catch(() => {})
    await page.setViewportSize(DESKTOP).catch(() => {})
  }

  expect(evidence.steps.length, 'statistics walkthrough steps').toBe(10)
  expect(evidence.pageErrors, 'page errors during the statistics walkthrough').toEqual([])
  expect(evidence.totals.notProbed, 'steps that could not be probed').toBe(0)
})
