import { describe, it, expect } from 'vitest'
import { buildManifest, summarize } from '../../../scripts/audit/ui-manifest.mjs'

// Plan #13 (issue #363, U0) · layer 1 contract.
//
// The manifest is the checklist every later audit session reads instead of
// inventing one. These assertions pin the three things the exit criterion names:
// the 36 screen routes are classified, nothing is a redirect, and no button is
// left unclassified. They also pin the sources of truth the derivation claims to
// use, so a silent parser drift fails here instead of in a session.
const ALL_ROLES = [
  'administrador',
  'recepcionista',
  'odontologo',
  'implantologo',
  'tecnico_dental',
  'asistente',
  'finanzas'
]

const KINDS = new Set([
  'create',
  'edit',
  'delete',
  'submit',
  'cancel',
  'open',
  'close',
  'export',
  'filter',
  'search',
  'paginate',
  'sort',
  'refresh',
  'toggle',
  'navigate',
  'action',
  'unclassified'
])

const manifest = buildManifest()

const surface = route => manifest.surfaces.find(item => item.route === route)

describe('ui manifest · surfaces', () => {
  it('classifies 36 screen routes and excludes the two redirects with a motive', () => {
    expect(manifest.surfaces).toHaveLength(36)
    expect(manifest.excluded).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ route: '/', kind: 'redirect' }),
        expect.objectContaining({ route: '/appointments/new', kind: 'redirect' })
      ])
    )
    expect(manifest.excluded).toHaveLength(2)
  })

  it('declares the documented shape for every surface', () => {
    for (const item of manifest.surfaces) {
      expect(Object.keys(item)).toEqual(
        expect.arrayContaining([
          'route',
          'name',
          'page',
          'roles',
          'tabs',
          'dialogs',
          'actions',
          'endpoints',
          'permission'
        ])
      )
      expect(item.page).toMatch(/^resources\/js\/.+\.vue$/)
      expect(item.roles.length).toBeGreaterThan(0)
      expect(Array.isArray(item.tabs)).toBe(true)
      expect(Array.isArray(item.dialogs)).toBe(true)
      expect(Array.isArray(item.actions)).toBe(true)
      expect(Array.isArray(item.endpoints)).toBe(true)
    }
  })

  it('leaves no button unclassified and no action without a target', () => {
    expect(summarize(manifest).unclassifiedActions).toBe(0)

    for (const item of manifest.surfaces) {
      for (const action of item.actions) {
        expect(KINDS.has(action.kind)).toBe(true)
        expect(action.label.length).toBeGreaterThan(0)
        expect(action.target.length).toBeGreaterThan(0)
        expect(action.file).toMatch(/^resources\/js\/.+\.vue$/)
      }
    }
  })
})

describe('ui manifest · roles from AppLayout.navigation', () => {
  it('offers the dashboard to every role and keeps finance out of the agenda', () => {
    expect(surface('/dashboard').roles).toEqual(ALL_ROLES)
    expect(surface('/calendar').roles).not.toContain('finanzas')
    expect(surface('/calendar').roles).toContain('recepcionista')
  })

  it('inherits the sibling module roles for a route without a navigation entry', () => {
    expect(surface('/cash-register/ready-to-bill').roles).toEqual(surface('/cash-register').roles)
    expect(surface('/cash-register/ready-to-bill').rolesSource).toBe('module-sibling')
  })

  it('inherits the list roles for detail routes', () => {
    for (const [detail, list] of [
      ['/patients/:id', '/patients'],
      ['/professionals/:id', '/professionals'],
      ['/environments/:id', '/environments'],
      ['/appointment-types/:id', '/appointment-types'],
      ['/procedure-catalog/:id', '/procedure-catalog'],
      ['/settings/branches/:id', '/settings/branches'],
      ['/medical-records/:id', '/medical-records']
    ]) {
      expect(surface(detail).roles).toEqual(surface(list).roles)
      expect(surface(detail).rolesSource).toBe('list-route')
    }
  })
})

describe('ui manifest · API surface and permission', () => {
  it('derives consumed endpoints from the page and its module', () => {
    expect(surface('/patients').endpoints).toContain('/api/patients')
    expect(surface('/ai-analysis').endpoints).toContain('/api/ai-analysis')
    expect(surface('/cash-register').endpoints).toContain('/api/cash-register/current')
  })

  it('resolves the route permission from routes/api.php, not from a role list', () => {
    expect(surface('/dashboard').permission).toBe('view.dashboard')
    expect(surface('/patients').permission).toBe('view.patient')
    expect(surface('/procedure-catalog').permission).toBe('view.procedure_catalog')
    expect(surface('/cash-register').permission).toBe('view.cash_register')
  })
})

describe('ui manifest · tabs, dialogs and actions', () => {
  it('resolves a literal tab list', () => {
    const labels = surface('/medical-records').tabs.map(tab => tab.label)
    expect(labels).toEqual(['Historia general', 'Evoluciones', 'Adjuntos', 'Estadísticas'])
  })

  it('attributes a dialog to the surface that imports it, not to its module', () => {
    // #448: the page owns the create/edit form too, not only the detail dialog.
    const labDialogs = surface('/lab-cases').dialogs
    expect(labDialogs.map(dialog => dialog.file)).toEqual([
      'resources/js/modules/lab-cases/LabCaseDetailModal.vue',
      'resources/js/modules/lab-cases/LabCaseFormModal.vue'
    ])
    expect(labDialogs.every(dialog => dialog.primitive === 'modal')).toBe(true)

    expect(surface('/patients/:id').dialogs.map(dialog => dialog.file)).toEqual(
      expect.arrayContaining([
        'resources/js/modules/patients/PatientFormModal.vue',
        'resources/js/modules/lab-cases/LabCaseFormModal.vue'
      ])
    )
  })

  it('classifies actions by intent and records the handler they call', () => {
    const byLabel = label => surface('/lab-cases').actions.find(action => action.label === label)

    expect(byLabel('Actualizar')).toMatchObject({ kind: 'refresh', target: 'load' })
    expect(byLabel('Solo vencidos')).toBeUndefined()
  })
})

describe('ui manifest · navigation and shell', () => {
  it('publishes the sidebar contract with the permission keys resolved through the SPA map', () => {
    // AppLayout.allItems declares 25 sidebar entries.
    expect(manifest.navigation).toHaveLength(25)

    for (const item of manifest.navigation) {
      expect(Object.keys(item)).toEqual(
        expect.arrayContaining([
          'name',
          'to',
          'roles',
          'permissions',
          'permissionStrings',
          'unresolvedPermissions'
        ])
      )
      expect(item.to).toMatch(/^\//)
      expect(item.roles.length).toBeGreaterThan(0)
      expect(item.unresolvedPermissions).toEqual([])
    }

    expect(manifest.navigation.find(item => item.name === 'Bloqueo Contable')).toEqual({
      name: 'Bloqueo Contable',
      to: '/settings/accounting-locks',
      roles: ['administrador'],
      permissions: ['manageAccountingLock'],
      permissionStrings: ['manage.accounting_lock'],
      unresolvedPermissions: []
    })

    expect(manifest.navigation.find(item => item.to === '/dashboard').permissionStrings).toEqual([])
  })

  it('keeps the shell out of the all-routes manifest', () => {
    expect(manifest.surfaces).toHaveLength(36)
    expect(manifest.surfaces.some(item => item.route === '(shell)')).toBe(false)
    expect(manifest.surfaces.some(item => item.kind === 'shell')).toBe(false)
  })

  it('selects the union of a comma-separated module list in app.js route order', () => {
    const expected = ['/login', '/reset-password', '/:pathMatch(.*)*', '(shell)']

    expect(buildManifest({ module: 'auth,layout' }).surfaces.map(item => item.route)).toEqual(
      expected
    )
    expect(buildManifest({ module: ['auth', 'layout'] }).surfaces.map(item => item.route)).toEqual(
      expected
    )
    expect(buildManifest({ module: 'layout,auth' }).surfaces.map(item => item.route)).toEqual(
      expected
    )
  })

  it('selects only the catch-all and the shell for the layout module', () => {
    const selected = buildManifest({ module: 'layout' })

    expect(selected.surfaces.map(item => item.route)).toEqual(['/:pathMatch(.*)*', '(shell)'])
    expect(selected.surfaces.filter(item => item.kind === 'shell')).toHaveLength(1)
  })

  it('declares the shell surface with its own roles, page and href', () => {
    const shell = buildManifest({ module: 'layout' }).surfaces.find(item => item.kind === 'shell')

    expect(shell).toMatchObject({
      route: '(shell)',
      kind: 'shell',
      name: 'app-layout',
      page: 'resources/js/components/layout/AppLayout.vue',
      href: '/dashboard',
      rolesSource: 'shell',
      permission: null,
      permissions: [],
      permissionSource: 'unresolved',
      sample: null,
      tabs: []
    })
    expect(shell.roles).toEqual(ALL_ROLES)
    expect(shell.dialogs.map(dialog => dialog.primitive)).toEqual(['sheet'])
    expect(shell.actions.length).toBeGreaterThan(0)
    expect(shell.actions.every(action => action.kind !== 'unclassified')).toBe(true)
  })

  it('keeps navigation on every selection and drops repeated module names', () => {
    const scoped = buildManifest({ module: 'auth,auth' })

    expect(scoped.surfaces.map(item => item.route)).toEqual(['/login', '/reset-password'])
    expect(scoped.navigation).toHaveLength(manifest.navigation.length)
  })
})
