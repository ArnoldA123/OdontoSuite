// Manifest assembly: surfaces, permissions, samples and the summary.

import { readApiRoutes, resolvePermission, segmentsOf } from './api-routes.mjs'
import {
  ROLES,
  buildNavigation,
  readAppRoutes,
  readNavigation,
  readPermissionMap,
  resolveRoles,
  isDetailRoute,
  buildSample,
  readRevision
} from './routes.mjs'
import {
  collectActions,
  collectDialogs,
  collectEndpoints,
  gatherSources,
  readTabs,
  visibleEndpoints
} from './sources.mjs'

// --- manifest ---------------------------------------------------------------

export const summarize = manifest => {
  const byKind = {}
  let actions = 0
  let dialogs = 0
  let unclassifiedActions = 0
  const endpoints = new Set()
  let unresolvedPermissions = 0

  for (const surface of manifest.surfaces) {
    actions += surface.actions.length
    dialogs += surface.dialogs.length
    if (surface.permission == null) unresolvedPermissions += 1
    for (const endpoint of surface.endpoints) endpoints.add(endpoint)

    for (const action of surface.actions) {
      byKind[action.kind] = (byKind[action.kind] ?? 0) + 1
      if (action.kind === 'unclassified') unclassifiedActions += 1
    }
  }

  return {
    surfaces: manifest.surfaces.length,
    actions,
    unclassifiedActions,
    byKind,
    dialogs,
    endpoints: endpoints.size,
    unresolvedPermissions
  }
}

const LAYOUT_MODULE = 'layout'
const SHELL_ROUTE = '(shell)'
const SHELL_PAGE = 'resources/js/components/layout/AppLayout.vue'
const SHELL_HREF = '/dashboard'

const normalizeModules = value => {
  const values = Array.isArray(value) ? value : [value]
  const names = values
    .flatMap(item => String(item ?? '').split(','))
    .map(name => name.trim().replace(/^\/+|\/+$/g, ''))
    .filter(Boolean)

  return [...new Set(names)]
}

const inModule = (surface, name) =>
  surface.route === `/${name}` ||
  surface.route.startsWith(`/${name}/`) ||
  surface.page.includes(`/modules/${name}/`)

// The catch-all renders the 404 inside the shell, so the `layout` module owns it.
const selectedBy = (surface, modules) =>
  modules.some(
    name =>
      inModule(surface, name) || (name === LAYOUT_MODULE && surface.route.includes(':pathMatch'))
  )

const applyFilter = (surfaces, options) => {
  if (options.route) return surfaces.filter(surface => surface.route === options.route)

  const modules = normalizeModules(options.module)
  if (modules.length === 0) return surfaces

  return surfaces.filter(surface => selectedBy(surface, modules))
}

// The shell is not a router route, so no app-route surface represents it.
const buildShellSurface = () => {
  const sources = gatherSources(SHELL_PAGE)

  return {
    route: SHELL_ROUTE,
    kind: 'shell',
    name: 'app-layout',
    page: SHELL_PAGE,
    href: SHELL_HREF,
    roles: [...ROLES],
    rolesSource: 'shell',
    tabs: [],
    dialogs: collectDialogs(sources),
    actions: collectActions(sources),
    endpoints: visibleEndpoints(collectEndpoints(sources), SHELL_ROUTE),
    permission: null,
    permissions: [],
    permissionSource: 'unresolved',
    sample: null
  }
}

export const buildManifest = (options = {}) => {
  const appRoutes = readAppRoutes()
  const navigation = readNavigation()
  const navigationRows = buildNavigation(navigation, readPermissionMap())
  const apiRoutes = readApiRoutes()

  const excluded = []
  const surfaces = []

  for (const route of appRoutes) {
    if (route.redirect) {
      excluded.push({ route: route.path, kind: 'redirect', reason: 'redirect' })
      continue
    }

    const sources = route.page ? gatherSources(route.page) : []
    const endpoints = visibleEndpoints(collectEndpoints(sources), route.path)

    surfaces.push({
      route: route.path,
      name: route.name,
      page: route.page ?? '',
      roles: [],
      rolesSource: null,
      tabs: readTabs(route.page),
      dialogs: collectDialogs(sources),
      actions: collectActions(sources),
      endpoints,
      permission: null,
      permissions: [],
      permissionSource: 'unresolved',
      sample: null
    })
  }

  const surfacesByRoute = new Map(surfaces.map(surface => [surface.route, surface]))

  for (const surface of surfaces) {
    const roles = resolveRoles(
      { path: surface.route, page: surface.page },
      navigation,
      surfacesByRoute
    )
    surface.roles = roles.roles
    surface.rolesSource = roles.rolesSource
  }

  // List routes first: a detail route reads the API resource its list page reads,
  // so the list's winning pattern names that resource.
  const resolved = new Map()
  const ordered = [
    ...surfaces.filter(surface => !isDetailRoute(surface.route)),
    ...surfaces.filter(surface => isDetailRoute(surface.route))
  ]

  for (const surface of ordered) {
    const affinityPaths = [surface.route]

    if (isDetailRoute(surface.route)) {
      const listRoute = `/${segmentsOf(surface.route).slice(0, -1).join('/')}`
      affinityPaths.push(listRoute)
      const list = resolved.get(listRoute)
      if (list?.pattern) affinityPaths.push(list.pattern)
    }

    resolved.set(surface.route, resolvePermission(affinityPaths, surface.endpoints, apiRoutes))
  }

  for (const surface of surfaces) {
    const permission = resolved.get(surface.route)
    surface.permission = permission.permission
    surface.permissions = permission.permissions
    surface.permissionSource = permission.permissionSource
  }

  for (const surface of surfaces) surface.sample = buildSample(surface, surfacesByRoute)

  const filtered = applyFilter(surfaces, options)
  const withShell = !options.route && normalizeModules(options.module).includes(LAYOUT_MODULE)
  const manifest = {
    revision: readRevision(),
    surfaces: withShell ? [...filtered, buildShellSurface()] : filtered,
    excluded,
    navigation: navigationRows,
    summary: null
  }
  manifest.summary = summarize(manifest)

  return manifest
}
