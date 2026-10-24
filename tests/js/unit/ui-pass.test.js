import { describe, it, expect } from 'vitest'

import { aggregate, buildGallery, classifySurface, gate } from '../../../scripts/audit/ui-pass.mjs'

// Plan #13 (issue #363, U0) · layer 4 contract.
//
// Fixtures only: these assertions pin how evidence rows become a verdict and how
// the gallery renders that verdict. No browser, no app, no evidence on disk.

const CLEAN_SHOTS = [
  'shots/patients-administrador-desktop.png',
  'shots/patients-administrador-mobile.png'
]

const cleanRow = (overrides = {}) => ({
  route: '/patients',
  slug: 'patients',
  role: 'administrador',
  expectation: 'owner',
  rendered: true,
  httpStatus: 200,
  contentLength: 420,
  horizontalOverflow: false,
  pageErrors: [],
  consoleErrors: [],
  failedRequests: { expected: [], unexpected: [] },
  viewports: { desktop: { width: 1440, height: 900 }, mobile: { width: 390, height: 844 } },
  shots: CLEAN_SHOTS,
  ...overrides
})

const surface = (route, roles = ['administrador', 'recepcionista']) => ({ route, roles })

const manifestOf = (routes, unclassifiedActions = 0) => ({
  revision: 'abc1234',
  surfaces: routes.map(route => surface(route)),
  summary: { unclassifiedActions }
})

const asText = gate => [...gate.reasons, ...gate.findings].join(' · ')

describe('ui pass · classifySurface', () => {
  it('renders a surface whose every evidence row is clean', () => {
    const row = classifySurface(surface('/patients'), [cleanRow()])

    expect(row).toMatchObject({ route: '/patients', slug: 'patients', status: 'renders' })
    expect(row.reason).toBeNull()
    expect(row.shots).toEqual(CLEAN_SHOTS)
    expect(row.pageErrors).toEqual([])
    expect(row.unexpectedRequests).toEqual([])
  })

  it('classifies a pageerror as not-renders and names the error', () => {
    const row = classifySurface(surface('/patients'), [cleanRow({ pageErrors: ['boom'] })])

    expect(row.status).toBe('not-renders')
    expect(row.reason).toContain('pageerror: boom')
    expect(row.pageErrors).toEqual(['boom'])
  })

  it('classifies an HTTP 500 as not-renders', () => {
    const row = classifySurface(surface('/patients'), [cleanRow({ httpStatus: 500 })])

    expect(row.status).toBe('not-renders')
    expect(row.reason).toContain('http 500')
  })

  it('classifies horizontal overflow at 390px as not-renders and reports its width', () => {
    const row = classifySurface(surface('/patients'), [
      cleanRow({
        horizontalOverflow: true,
        viewports: { mobile: { innerWidth: 390, scrollWidth: 390, width: 390, height: 844 } }
      })
    ])

    expect(row.status).toBe('not-renders')
    expect(row.reason).toContain('overflow 390px')
  })

  it('classifies an unexpected request on a negative role as not-renders', () => {
    const row = classifySurface(surface('/patients'), [
      cleanRow({
        role: 'finanzas',
        expectation: 'negative',
        failedRequests: {
          expected: [{ status: 403, url: '/api/patients' }],
          unexpected: [{ status: 500, url: '/api/patients' }]
        }
      })
    ])

    expect(row.status).toBe('not-renders')
    expect(row.reason).toContain('solicitud 500')
    expect(row.unexpectedRequests).toEqual([{ status: 500, url: '/api/patients' }])
  })

  it('excludes a surface whose every row carries a reason', () => {
    const row = classifySurface(surface('/patients/:id'), [
      cleanRow({ rendered: false, httpStatus: null, reason: 'no-sample-record' })
    ])

    expect(row.status).toBe('excluded')
    expect(row.reason).toBe('no-sample-record')
    expect(row.pageErrors).toEqual([])
  })

  it('reports a surface without evidence as missing', () => {
    const row = classifySurface(surface('/patients'), [])

    expect(row.status).toBe('missing')
    expect(row.reason).toBe('sin evidencia')
    expect(row.shots).toEqual([])
  })

  it('tolerates malformed rows and rows without diagnostics', () => {
    const row = classifySurface(surface('/patients'), [{}, null])

    expect(row.status).toBe('not-renders')
    expect(row.pageErrors).toEqual([])
    expect(row.shots).toEqual([])
  })
})

describe('ui pass · aggregate and gate', () => {
  it('passes the gate when excluded surfaces carry their motive', () => {
    const manifest = manifestOf(['/patients', '/patients/:id'])
    const { counts, gate } = aggregate(
      manifest,
      [
        cleanRow(),
        cleanRow({
          route: '/patients/:id',
          rendered: false,
          httpStatus: null,
          reason: 'no-sample-record'
        })
      ],
      []
    )

    expect(counts).toMatchObject({ surfaces: 2, rendering: 1, excluded: 1, missing: 0 })
    expect(gate.ok).toBe(true)
    expect(gate.reasons).toEqual([])
  })

  it('breaks the gate on a pageerror and names it', () => {
    const { counts, gate } = aggregate(
      manifestOf(['/patients']),
      [cleanRow({ pageErrors: ['boom'] })],
      []
    )

    expect(counts.pageErrors).toBe(1)
    expect(gate.ok).toBe(false)
    expect(asText(gate)).toMatch(/pageerror/)
  })

  it('breaks the gate on a page error raised during the dialog probe', () => {
    const { counts, gate } = aggregate(
      manifestOf(['/patients']),
      [cleanRow()],
      [{ route: '/patients', pageErrors: ['probe boom'], dialogs: [] }]
    )

    expect(counts.primitivePageErrors).toBe(1)
    expect(counts.pageErrors).toBe(1)
    expect(gate.ok).toBe(false)
    expect(asText(gate)).toMatch(/pageerror: \/patients · probe boom/)
  })

  it('classifies a not-renders surface as a finding without failing the instrument', () => {
    const { counts, gate } = aggregate(
      manifestOf(['/patients', '/dashboard']),
      [cleanRow({ rendered: false, contentLength: 40 }), cleanRow({ route: '/dashboard' })],
      []
    )

    expect(counts.notRendering).toBe(1)
    expect(gate.ok).toBe(true)
    expect(asText(gate)).toMatch(/no renderiza: \/patients/)
    expect(gate.findings).toHaveLength(1)
  })

  it('fails the gate as a platform defect when more than half the scope does not render', () => {
    const { counts, gate } = aggregate(
      manifestOf(['/patients', '/dashboard']),
      [
        cleanRow({ rendered: false, contentLength: 40 }),
        cleanRow({ route: '/dashboard', rendered: false, contentLength: 30 })
      ],
      []
    )

    expect(counts.notRendering).toBe(2)
    expect(gate.ok).toBe(false)
    expect(asText(gate)).toMatch(/defecto de plataforma: 2\/2/)
  })

  it('breaks the gate on a surface without evidence and names it', () => {
    const { counts, gate } = aggregate(manifestOf(['/patients']), [], [])

    expect(counts.missing).toBe(1)
    expect(gate.ok).toBe(false)
    expect(asText(gate)).toMatch(/sin evidencia: \/patients/)
  })

  it('breaks the gate on an unclassified action and names it', () => {
    const { counts, gate } = aggregate(manifestOf(['/patients'], 2), [cleanRow()], [])

    expect(counts.unclassifiedActions).toBe(2)
    expect(gate.ok).toBe(false)
    expect(asText(gate)).toMatch(/acciones sin clasificar: 2/)
  })

  it('ignores evidence for a route outside the manifest', () => {
    const unknown = cleanRow({ route: '/ghost', pageErrors: ['boom'] })
    const { counts, gate } = aggregate(manifestOf(['/patients']), [cleanRow(), unknown], [])

    expect(counts.surfaces).toBe(1)
    expect(counts.pageErrors).toBe(0)
    expect(gate.ok).toBe(true)
  })

  it('exposes the same verdict aggregate returns', () => {
    const rows = [classifySurface(surface('/patients'), [cleanRow()])]

    expect(gate(rows, { unclassifiedActions: 0 })).toEqual({ ok: true, reasons: [], findings: [] })
  })

  it('counts failed dialog checks without breaking the gate', () => {
    const primitiveRows = [
      {
        route: '/patients',
        dialogs: [
          {
            trigger: { label: 'Nuevo paciente' },
            dialog: { name: 'Paciente' },
            checks: [
              { id: 'centered', ok: true, observed: 0, expected: '<8px off center' },
              { id: 'escape-closes', ok: false, observed: 'still open', expected: 'closes' },
              { id: 'single-h1', ok: false, observed: 2, expected: 1 }
            ]
          }
        ]
      }
    ]

    const { counts, gate } = aggregate(manifestOf(['/patients']), [cleanRow()], primitiveRows)

    expect(counts.dialogsProbed).toBe(1)
    expect(counts.contractFailures).toBe(2)
    expect(gate.ok).toBe(true)
  })
})

describe('ui pass · buildGallery', () => {
  const manifest = {
    revision: 'abc1234',
    surfaces: [
      surface('/patients', ['administrador']),
      surface('/calendar', ['administrador', 'recepcionista']),
      surface('/odd"><script>alert(1)</script>', ['"><script>'])
    ]
  }

  const renderRows = [
    cleanRow({
      route: '/patients',
      expectation: 'owner',
      role: 'recepcionista',
      shots: ['shots/patients-recepcionista-desktop.png', 'shots/patients-recepcionista-mobile.png']
    }),
    cleanRow({
      route: '/calendar',
      expectation: 'admin',
      role: 'administrador',
      shots: ['shots/calendar-administrador-desktop.png', 'shots/calendar-administrador-mobile.png']
    }),
    cleanRow({
      route: '/calendar',
      expectation: 'owner',
      role: 'recepcionista',
      shots: ['shots/calendar-recepcionista-desktop.png', 'shots/calendar-recepcionista-mobile.png']
    })
  ]

  const report = {
    revision: 'abc1234',
    base: 'http://localhost:8000',
    scope: 'all',
    generatedAt: '2026-10-23T00:00:00.000Z',
    rows: [
      { ...classifySurface(manifest.surfaces[0], [renderRows[0]]) },
      { ...classifySurface(manifest.surfaces[1], [renderRows[1], renderRows[2]]) },
      { ...classifySurface(manifest.surfaces[2], []) }
    ],
    renderRows,
    primitiveRows: [
      {
        route: '/patients',
        dialogs: [
          {
            trigger: { label: 'Nuevo paciente' },
            dialog: { name: 'Paciente' },
            checks: [{ id: 'escape-closes', ok: false, observed: 'still open', expected: 'closes' }]
          }
        ]
      }
    ]
  }

  const html = buildGallery(manifest, report)

  it('renders one section per surface in manifest order', () => {
    expect(html.match(/<section /g)).toHaveLength(manifest.surfaces.length)

    const first = html.indexOf('data-route="/patients"')
    const second = html.indexOf('data-route="/calendar"')

    expect(first).toBeGreaterThan(-1)
    expect(second).toBeGreaterThan(first)
  })

  it('includes every shot path, with role and viewport captions', () => {
    for (const row of renderRows) {
      for (const shot of row.shots) expect(html).toContain(shot)
    }

    expect(html).toContain('<figcaption>recepcionista · desktop</figcaption>')
    expect(html).toContain('<figcaption>recepcionista · mobile</figcaption>')
    expect(html).toContain('<figcaption>administrador · desktop</figcaption>')
    expect(html.indexOf('recepcionista · desktop')).toBeLessThan(
      html.indexOf('administrador · desktop')
    )
  })

  it('shows the classified status of each row', () => {
    expect(html).toContain('class="status renders"')
    expect(html).toContain('class="status missing"')
    expect(html).toContain('class="badge renders"')
  })

  it('escapes route and role values instead of emitting raw script tags', () => {
    expect(html).not.toContain('<script')
    expect(html).toContain('&lt;script&gt;')
  })

  it('renders an empty report without throwing', () => {
    expect(buildGallery({ surfaces: [] }, {})).toContain('<h1>Auditoría de superficies — U0</h1>')
  })
})
