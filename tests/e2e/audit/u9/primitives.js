// Plan #13 (issue #372, U9) — layer 3: the dialogs of the administration desk.
//
// The seven dialogs the layer-1 manifest declares (professionals, environments,
// appointment types, specialties, branches, payment methods and the accounting
// lock) plus the confirmations the pages own (delete, archive, deactivate, retire
// a lock, cancel a transfer). Each one opens through its own control and is
// measured with the harness contract: centered or docked, one `h1` on the page,
// focus trapped, scroll locked, an accessible name and Escape closes.
//
// A dialog that cannot be opened is `NO PROBADO` with its reason — never a thrown
// failure. A contract violation stays in the evidence as the measured fact it is.
//
// The manifest is a static read of the page source: several of its dialog actions
// carry no selector (the modal is opened from a row button), so the entry table
// below addresses the page's own hook and records the manifest derivation it
// belongs to.

import { test, expect } from 'playwright/test'

import {
  applyScope,
  assertDialogContract,
  classifyFailures,
  collectDiagnostics,
  dialogTriggers,
  primeSession,
  settleSurface
} from '../harness.js'

import { ensureFixtures, loginCredential } from './fixtures.js'

import {
  DESKTOP,
  DIALOG_SELECTOR,
  THRESHOLD,
  audit,
  classifyConsole,
  clickByLabel,
  closeDialog,
  dialogStack,
  dismissToasts,
  gotoSurface,
  loadSessionManifest,
  login,
  rolePlanFor,
  settle,
  shot,
  stamp
} from './helpers.js'

const SCOPE = process.env.AUDIT_SCOPE || 'all'

const ROUTES = Object.freeze([
  {
    route: '/professionals',
    slug: 'professionals',
    label: 'Profesionales',
    page: 'resources/js/modules/professionals/ProfessionalsPage.vue'
  },
  {
    route: '/professionals/:id',
    slug: 'professionals-detail',
    label: 'Detalle del profesional',
    page: 'resources/js/modules/professionals/ProfessionalDetailPage.vue'
  },
  {
    route: '/environments',
    slug: 'environments',
    label: 'Ambientes',
    page: 'resources/js/modules/environments/EnvironmentsPage.vue'
  },
  {
    route: '/environments/:id',
    slug: 'environments-detail',
    label: 'Detalle del ambiente',
    page: 'resources/js/modules/environments/EnvironmentDetailPage.vue'
  },
  {
    route: '/appointment-types',
    slug: 'appointment-types',
    label: 'Tipos de cita',
    page: 'resources/js/modules/appointment-types/AppointmentTypesPage.vue'
  },
  {
    route: '/appointment-types/:id',
    slug: 'appointment-types-detail',
    label: 'Detalle del tipo de cita',
    page: 'resources/js/modules/appointment-types/AppointmentTypeDetailPage.vue'
  },
  {
    route: '/specialties',
    slug: 'specialties',
    label: 'Especialidades',
    page: 'resources/js/modules/specialties/SpecialtiesPage.vue'
  },
  {
    route: '/settings/branches',
    slug: 'settings-branches',
    label: 'Sucursales',
    page: 'resources/js/modules/settings/branches/BranchesPage.vue'
  },
  {
    route: '/settings/branches/:id',
    slug: 'settings-branches-detail',
    label: 'Detalle de la sucursal',
    page: 'resources/js/modules/settings/branches/BranchDetailPage.vue'
  },
  {
    route: '/settings/payment-methods',
    slug: 'settings-payment-methods',
    label: 'Métodos de pago',
    page: 'resources/js/modules/settings/payment-methods/PaymentMethodsPage.vue'
  },
  {
    route: '/settings/accounting-locks',
    slug: 'settings-accounting-locks',
    label: 'Bloqueo contable',
    page: 'resources/js/modules/accounting-locks/AccountingLocksPage.vue'
  },
  {
    route: '/branch-transfers',
    slug: 'branch-transfers',
    label: 'Traslados de sede',
    page: 'resources/js/modules/branch-transfers/BranchTransfersPage.vue'
  }
])

const rolesFor = route => {
  if (route === '/branch-transfers') {
    return [
      'administrador',
      'recepcionista',
      'odontologo',
      'implantologo',
      'tecnico_dental',
      'asistente',
      'finanzas'
    ]
  }

  return ['administrador']
}

const surfaces = applyScope(
  { surfaces: ROUTES.map(route => ({ ...route, roles: rolesFor(route.route) })) },
  SCOPE
)

const destOf = fixtures => ({
  '/professionals/:id': `/professionals/${fixtures.professionals.plain.id}`,
  '/environments/:id': `/environments/${fixtures.chairs.detail.id}`,
  '/appointment-types/:id': `/appointment-types/${fixtures.appointmentTypes.detail.id}`,
  '/settings/branches/:id': `/settings/branches/${fixtures.branches.target.id}`
})

const hrefFor = (surface, fixtures) => destOf(fixtures)[surface.route] ?? surface.route

const fromLocator = locator => async () => {
  if (!(await locator.count().catch(() => 0))) return { ok: false, reason: 'trigger not rendered' }

  const node = locator.first()

  if (!(await node.isVisible().catch(() => false))) return { ok: false, reason: 'trigger hidden' }
  if (!(await node.isEnabled().catch(() => false))) return { ok: false, reason: 'trigger disabled' }

  await node.click({ timeout: 12000 })
  return { ok: true }
}

const fromLabel = label => async page => {
  const result = await clickByLabel(page, label)
  return result.ok ? { ok: true } : { ok: false, reason: result.reason }
}

// The page-owned controls each surface opens its dialogs with. A confirmation is
// measured on `[role="alertdialog"]`; the row never presses its confirm button.
const ENTRIES = Object.freeze({
  '/professionals': [
    {
      slug: 'professionals-create',
      label: 'Nuevo Profesional',
      kind: 'create',
      manifest: 'Nuevo Profesional',
      open: fromLabel('Nuevo Profesional')
    },
    {
      slug: 'professionals-edit',
      label: 'Editar (fila)',
      kind: 'edit',
      manifest: 'Nuevo Profesional',
      open: fromLabel('Editar')
    },
    {
      slug: 'professionals-delete-confirm',
      label: 'Eliminar (fila) → confirmar',
      kind: 'confirm',
      open: fromLabel('Eliminar'),
      confirm: true
    }
  ],
  '/environments': [
    {
      slug: 'environments-create',
      label: 'Nuevo ambiente',
      kind: 'create',
      manifest: 'Nuevo ambiente',
      open: fromLabel('Nuevo ambiente')
    },
    {
      slug: 'environments-edit',
      label: 'Editar (fila)',
      kind: 'edit',
      manifest: 'Nuevo ambiente',
      open: fromLabel('Editar')
    },
    {
      slug: 'environments-archive-confirm',
      label: 'Archivar (fila) → confirmar',
      kind: 'confirm',
      open: fromLabel('Archivar'),
      confirm: true
    }
  ],
  '/appointment-types': [
    {
      slug: 'appointment-types-create',
      label: 'Nuevo tipo',
      kind: 'create',
      manifest: 'Nuevo Tipo de Cita',
      open: null
    },
    {
      slug: 'appointment-types-edit',
      label: 'Editar tipo (fila)',
      kind: 'edit',
      manifest: 'Nuevo Tipo de Cita',
      open: null
    },
    {
      slug: 'appointment-types-delete-confirm',
      label: 'Eliminar tipo (fila) → confirmar',
      kind: 'confirm',
      open: null,
      confirm: true
    }
  ],
  '/specialties': [
    {
      slug: 'specialties-create',
      label: 'Nueva especialidad',
      kind: 'create',
      manifest: "isEdit ? 'Editar especialidad' : 'Nueva especialidad'",
      open: fromLabel('Nueva especialidad')
    },
    {
      slug: 'specialties-edit',
      label: 'Editar (fila)',
      kind: 'edit',
      manifest: "isEdit ? 'Editar especialidad' : 'Nueva especialidad'",
      open: null
    },
    {
      slug: 'specialties-toggle-confirm',
      label: 'Desactivar / Activar (fila) → confirmar',
      kind: 'confirm',
      open: null,
      confirm: true
    }
  ],
  '/settings/branches': [
    {
      slug: 'branches-create',
      label: 'Nueva sucursal',
      kind: 'create',
      manifest: "isEdit ? 'Editar sucursal' : 'Nueva sucursal'",
      open: null
    },
    {
      slug: 'branches-edit',
      label: 'Editar sucursal (fila)',
      kind: 'edit',
      manifest: "isEdit ? 'Editar sucursal' : 'Nueva sucursal'",
      open: null
    },
    {
      slug: 'branches-toggle-confirm',
      label: 'Desactivar sucursal (fila) → confirmar',
      kind: 'confirm',
      open: null,
      confirm: true
    }
  ],
  '/settings/payment-methods': [
    {
      slug: 'payment-methods-create',
      label: 'Nuevo método',
      kind: 'create',
      manifest: "isEdit ? 'Editar método de pago' : 'Nuevo método de pago'",
      open: fromLabel('Nuevo método')
    },
    {
      slug: 'payment-methods-edit',
      label: 'Editar (fila)',
      kind: 'edit',
      manifest: "isEdit ? 'Editar método de pago' : 'Nuevo método de pago'",
      open: null
    },
    {
      slug: 'payment-methods-delete-confirm',
      label: 'Eliminar (fila) → confirmar',
      kind: 'confirm',
      open: null,
      confirm: true
    }
  ],
  '/settings/accounting-locks': [
    {
      slug: 'accounting-locks-fix',
      label: 'Cambiar / Fijar (fila)',
      kind: 'action',
      manifest: 'lockModalTitle',
      open: null
    },
    {
      slug: 'accounting-locks-clear-confirm',
      label: 'Retirar (fila) → confirmar',
      kind: 'confirm',
      open: null,
      confirm: true
    }
  ],
  '/branch-transfers': [
    {
      slug: 'branch-transfers-cancel-confirm',
      label: 'Cancelar (fila) → confirmar',
      kind: 'confirm',
      open: null,
      confirm: true
    }
  ]
})

const OPENERS = Object.freeze({
  '/appointment-types': {
    create: '[data-hero-action]',
    edit: '[data-row-action="edit"]',
    confirm: '[data-row-action="delete"]'
  },
  '/specialties': {
    edit: '[data-row-action="edit"]',
    confirm: '[data-row-action="toggle"]'
  },
  '/settings/branches': {
    create: '[data-hero-action]',
    edit: '[data-row-action="edit"]',
    confirm: '[data-row-action="toggle"]'
  },
  '/settings/payment-methods': {
    edit: '[data-row-action="edit"]',
    confirm: '[data-row-action="delete"]'
  },
  '/settings/accounting-locks': {
    action: '[data-row-action="lock"]',
    confirm: '[data-row-action="clear"]'
  },
  '/branch-transfers': {
    confirm: '[data-transfer-cancel]'
  }
})

const contractFor = async (page, entry, route) => {
  const row = {
    route,
    slug: entry.slug,
    label: entry.label,
    kind: entry.kind,
    manifestDialog: entry.manifest ?? null,
    expected: 'un diálogo que cumple el contrato',
    opened: false,
    dialog: null,
    checks: [],
    contractFailures: [],
    outcome: 'no-dialog',
    reason: null,
    shot: null
  }

  try {
    const opened = await entry.open(page)

    if (!opened.ok) {
      row.outcome = 'trigger-not-available'
      row.reason = opened.reason ?? 'the trigger is not available'
      return row
    }

    const dialog = page
      .locator(entry.confirm ? '[role="alertdialog"]:visible' : DIALOG_SELECTOR)
      .last()
    const painted = await dialog
      .waitFor({ state: 'visible', timeout: 12000 })
      .then(() => true)
      .catch(() => false)

    if (!painted) {
      row.outcome = 'no-dialog'
      row.reason = 'the click opened no visible dialog'
      return row
    }

    row.opened = true
    row.outcome = 'measured'
    row.dialogsBeforeContract = await dialogStack(page)
    row.shot = await shot(page, `primitives/${entry.slug}.png`)

    const contract = await assertDialogContract(page, { dialog })
    row.dialog = contract.dialog
    row.checks = contract.checks
    row.contractFailures = contract.checks.filter(check => check.ok === false)

    return row
  } catch (thrown) {
    row.outcome = 'probe-error'
    row.reason = String(thrown?.message ?? thrown).slice(0, 300)
    return row
  } finally {
    await closeDialog(page)
    await dismissToasts(page)
  }
}

for (const surface of surfaces) {
  test(`@U9 · capa 3 — ${surface.label}: contrato de diálogos`, async ({ page }) => {
    test.setTimeout(300000)

    const fixtures = await ensureFixtures()

    if (!fixtures.ok) test.skip(true, `fixtures refused: ${fixtures.reason}`)

    const [owner] = rolePlanFor(surface)
    const manifestSurface = loadSessionManifest()?.surfaces?.find(
      entry => entry.route === surface.route
    )
    const entries = ENTRIES[surface.route] ?? []
    const diagnostics = collectDiagnostics(page)
    const rows = []
    const evidence = {
      ...stamp(),
      stage: 'layer-3',
      route: surface.route,
      slug: surface.slug,
      label: surface.label,
      role: owner?.role ?? null,
      declaredDialogs: (manifestSurface?.dialogs ?? []).map(
        dialog => dialog.title ?? dialog.titleExpression
      ),
      manifestTriggers: dialogTriggers(manifestSurface ?? {}, 2).map(action => ({
        label: action.label,
        kind: action.kind,
        selector: action.selector
      })),
      entries: rows,
      opened: 0,
      notProbed: [],
      contractFailures: 0,
      pageErrors: [],
      consoleErrors: diagnostics.consoleErrors
    }

    try {
      // The transfer confirmation belongs to the request owner, so the row signs
      // in as the session's own requester instead of the seeded administration.
      const session =
        surface.route === '/branch-transfers'
          ? await loginCredential(fixtures.requesters.cancel.username)
          : await login(owner?.role ?? 'administrador')

      await page.setViewportSize(DESKTOP)
      await primeSession(page, session)
      await gotoSurface(page, hrefFor(surface, fixtures))
      await settleSurface(page, THRESHOLD, 15000)
      await settle(page)

      for (const entry of entries) {
        const definition = { ...entry }
        const opener = OPENERS[surface.route]?.[entry.kind ?? 'confirm']

        if (!definition.open && opener) definition.open = fromLocator(page.locator(opener))

        if (!definition.open) {
          rows.push({
            route: surface.route,
            slug: entry.slug,
            label: entry.label,
            kind: entry.kind,
            outcome: 'no-opener',
            reason: 'the manifest action carries no selector and the page renders no hook',
            opened: false,
            checks: []
          })
          continue
        }

        rows.push(await contractFor(page, definition, surface.route))
      }
    } finally {
      evidence.opened = rows.filter(row => row.opened).length
      evidence.notProbed = rows
        .filter(row => row.outcome !== 'measured')
        .map(row => ({ slug: row.slug, outcome: row.outcome, reason: row.reason ?? null }))
      evidence.contractFailures = rows.reduce(
        (total, row) => total + (row.contractFailures ?? []).length,
        0
      )
      evidence.dialogs = rows.map(row => ({
        trigger: { label: row.label, kind: row.kind },
        dialog: row.dialog,
        checks: row.checks,
        outcome: row.outcome
      }))
      evidence.pageErrors = diagnostics.pageErrors.map(error => String(error))
      evidence.consoleClassified = classifyConsole(diagnostics.consoleErrors)
      evidence.failedRequests = classifyFailures(diagnostics.failedRequests, 'admin')
      evidence.fixtures = { marker: fixtures.marker, ok: fixtures.ok }

      audit(`primitives/${surface.slug}`, evidence)
    }

    // The instrument asserts that it ran, not that the product is perfect: a
    // contract failure is the session's evidence, not a red suite.
    expect(evidence.pageErrors, `page errors of ${surface.route}`).toEqual([])
    expect(
      rows.every(row => row.shot || row.outcome !== 'measured'),
      `every measured dialog of ${surface.route} has its capture`
    ).toBe(true)
  })
}
