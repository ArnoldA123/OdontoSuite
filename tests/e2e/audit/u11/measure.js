// Plan #13 (issue #374, U11) — layer 2: the twelve axes as the browser paints
// them, in one `page.evaluate`.
//
// The payload is the measurement, never a judgement: counts of what the surface
// container renders and clipped samples of the strings it publishes. A missing
// hero marker is `0`, an absent table is `0`; nothing is inferred from the
// source here (that is `static.js`).
//
// The container is `<main>` (the shell) or `#app` (the auth screens, which have
// no shell). Dialogs are not measured in this pass: `primitives.js` opens them.

export const MEASURE_LIMITS = Object.freeze({
  currency: 8,
  dates: 8,
  times: 5,
  buttons: 40,
  statusTexts: 8,
  h2: 12,
  repetitionExamples: 3
})

export const measureAxes = page =>
  page.evaluate(() => {
    const container = document.querySelector('main') ?? document.querySelector('#app')
    const root = container ?? document

    const normalize = value =>
      String(value ?? '')
        .replace(/\s+/g, ' ')
        .trim()
    const isVisible = node => {
      if (!node) return false
      const box = node.getBoundingClientRect()
      return box.width > 0 && box.height > 0
    }
    const visible = selector => Array.from(root.querySelectorAll(selector)).filter(isVisible)
    const texts = selector => visible(selector).map(node => normalize(node.innerText))
    const unique = values => [...new Set(values.filter(Boolean))]
    const words = value => normalize(value).split(' ').filter(Boolean).length
    const clip = (values, limit) => values.slice(0, limit)

    const surfaceText = normalize(container?.innerText ?? '')
    const h1Nodes = Array.from(document.querySelectorAll('h1'))
    const topbar = document.querySelector('[data-topbar-title-owner]')
    const topbarHeading = topbar?.querySelector('h1') ?? null

    const currency = unique(surfaceText.match(/-?S\/\s?[\d.,]+/g) ?? [])
    const dates = unique([
      ...(surfaceText.match(/\b\d{1,2}\/\d{1,2}\/\d{2,4}\b/g) ?? []),
      ...(surfaceText.match(/\b\d{4}-\d{2}-\d{2}\b/g) ?? []),
      ...(surfaceText.match(/\b\d{1,2} de [a-záéíóú]+ de \d{4}\b/gi) ?? [])
    ])
    const times = unique(surfaceText.match(/\b\d{2}:\d{2}\b/g) ?? [])

    const searchRegions = visible('[role="search"], .filter-bar')
    const resetLabels = unique(
      searchRegions
        .flatMap(region => Array.from(region.querySelectorAll('button')).filter(isVisible))
        .map(node => normalize(node.innerText))
        .filter(label => /limpiar|restablecer|borrar/i.test(label))
    )
    // Guards use two different words for a filter that *is not* a `FilterBar`:
    // some pages ship selects and a date range straight into the hero band. The
    // axis asks whether filters exist, so the bare controls count too.
    const anyControls = visible('select, input[type="date"], input[type="search"]').length

    // Repetition answers the plan's question — the same datum in a KPI and in
    // the table, or the same warning twice — not "this table repeats a date per
    // row", which is what a list does. Two readings:
    //   a) a KPI value that also appears inside a cell;
    //   b) the same leaf text twice outside tabular data (titles, breadcrumbs,
    //      chips and summaries are the places that duplicate by accident).
    const kpiValues = unique(
      Array.from(root.querySelectorAll('[data-kpi-value]')).map(node => normalize(node.innerText))
    )
    const cellValues = new Set(
      Array.from(root.querySelectorAll('td'))
        .map(node => normalize(node.textContent))
        .filter(Boolean)
    )
    const kpiInTable = kpiValues.filter(value => cellValues.has(value))

    const repeated = new Map()
    let inspected = 0

    for (const node of root.querySelectorAll('*')) {
      inspected += 1
      if (inspected > 8000) break

      if (node.children.length > 0) continue
      if (node.closest('aside, nav, header, thead, td, th, button, [role="status"]')) continue

      const text = normalize(node.textContent)

      if (text.length < 8 || text.length > 48) continue
      if (!/\d/.test(text)) continue

      const key = text.toLowerCase()
      const current = repeated.get(key)

      if (current) current.times += 1
      else repeated.set(key, { text, times: 1 })
    }

    const groups = [...repeated.values()]
      .filter(group => group.times >= 2)
      .sort((left, right) => {
        if (right.times !== left.times) return right.times - left.times
        if (left.text === right.text) return 0
        return left.text < right.text ? -1 : 1
      })
    const examples = [
      ...kpiInTable.map(value => ({ text: value, times: 2, scope: 'kpi-tabla' })),
      ...groups.map(group => ({ ...group, scope: 'fuera-de-tabla' }))
    ].slice(0, 3)

    const heroTitle = unique(
      Array.from(root.querySelectorAll('[data-hero-title]')).map(node => normalize(node.innerText))
    )
    const heroCaption = unique(
      Array.from(root.querySelectorAll('[data-hero-caption]')).map(node =>
        normalize(node.innerText)
      )
    )

    return {
      title: {
        h1Count: h1Nodes.length,
        h1Texts: unique(h1Nodes.map(node => normalize(node.innerText))),
        h2Texts: clip(texts('h2'), 12),
        topbarOwner: topbar?.getAttribute('data-topbar-title-owner') ?? null,
        topbarTitle: topbarHeading ? normalize(topbarHeading.innerText) : null
      },
      list: {
        tables: root.querySelectorAll('table').length,
        dataTableScroll: root.querySelectorAll('[data-table-scroll]').length,
        pagination: root.querySelectorAll('[role="navigation"][aria-label="Paginación"]').length,
        scrollAffordance: root.querySelectorAll('[data-table-scroll-affordance]').length,
        emptyState: root.querySelectorAll('.empty-state').length
      },
      loading: {
        skeleton: root.querySelectorAll('.skeleton-container').length,
        spinner: root.querySelectorAll('.loading-spinner-container').length,
        visibleText: surfaceText.length
      },
      filters: {
        searchRegions: searchRegions.length,
        anyControls,
        controls: searchRegions
          .flatMap(region => Array.from(region.querySelectorAll('input, select, textarea')))
          .filter(isVisible).length,
        resetLabels: clip(resetLabels, 8)
      },
      badges: {
        statusCount: visible('[role="status"]').length,
        statusTexts: clip(
          unique(visible('[role="status"]').map(node => normalize(node.innerText))),
          8
        ),
        badgeClassCount: root.querySelectorAll('[class*="badge"]').length
      },
      format: {
        currency: clip(currency, 8),
        dates: clip(dates, 8),
        times: clip(times, 5)
      },
      buttons: {
        labels: clip(unique(visible('button').map(node => normalize(node.innerText))), 40)
      },
      density: {
        heroWords: words(heroTitle.join(' ')),
        captionWords: words(heroCaption.join(' ')),
        totalWords: words(surfaceText),
        kpiCards: root.querySelectorAll('[data-kpi-card]').length
      },
      repetition: {
        count: kpiInTable.length + groups.length,
        examples
      }
    }
  })
