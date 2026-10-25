// Router, navigation and revision sources for the manifest.

import { existsSync, readFileSync, statSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'

import {
  ROOT,
  findBalanced,
  literalText,
  parseObjectEntries,
  parseStringLiterals,
  readRepoText,
  splitTopLevelObjects,
  stripComments
} from './text.mjs'

import { resolveSpecifier, resolveStaticImport } from './sources.mjs'

import { affinityVocabulary, routeAffinity, segmentsOf } from './api-routes.mjs'

export const ROLES = [
  'administrador',
  'recepcionista',
  'odontologo',
  'implantologo',
  'tecnico_dental',
  'asistente',
  'finanzas'
]

// Detail surfaces whose list page never spells the collection endpoint in a
// literal the derivation can read. Applied only when derivation finds nothing.
const LIST_ENDPOINT_OVERRIDES = {
  '/professionals': '/api/users',
  '/environments': '/api/dental-chairs',
  '/settings/branches': '/api/branches'
}

// --- app.js routes ----------------------------------------------------------

const normalizeRoutePath = path => {
  if (path.includes(':pathMatch')) return path
  return path.replace(/:([A-Za-z_]\w*)\([^)]*\)/g, ':$1')
}

export const readAppRoutes = () => {
  const source = stripComments(readRepoText('resources/js/app.js') ?? '')
  const start = source.indexOf('const routes = [')
  if (start === -1) return []

  const open = source.indexOf('[', start)
  const close = findBalanced(source, open, '[', ']')
  const body = source.slice(open + 1, close)

  const routes = []
  for (const objectText of splitTopLevelObjects(body)) {
    const entries = parseObjectEntries(objectText)
    const rawPath = entries.get('path')
    if (!rawPath) continue

    const path = literalText(rawPath)
    if (!path) continue

    const name = literalText(entries.get('name') ?? '') ?? null
    const componentValue = entries.get('component')
    let page = null

    if (componentValue) {
      const dynamic = /import\(\s*['"]([^'"]+)['"]\s*\)/.exec(componentValue)
      if (dynamic) {
        page = resolveSpecifier(dynamic[1], 'resources/js/app.js')
      } else {
        const identifier = componentValue.trim().match(/^[A-Za-z_$][\w$]*$/)
        if (identifier) page = resolveStaticImport(identifier[0], source)
      }
    }

    routes.push({
      path: normalizeRoutePath(path),
      rawPath: path,
      name,
      page,
      redirect: entries.has('redirect')
    })
  }

  return routes
}

export const readNavigation = () => {
  const source = stripComments(readRepoText('resources/js/components/layout/AppLayout.vue') ?? '')
  const start = source.indexOf('const allItems = [')
  if (start === -1) return []

  const open = source.indexOf('[', start)
  const close = findBalanced(source, open, '[', ']')
  const body = source.slice(open + 1, close)

  const items = []
  for (const objectText of splitTopLevelObjects(body)) {
    const entries = parseObjectEntries(objectText)
    const to = literalText(entries.get('to') ?? '')
    if (!to) continue

    const roleValues = parseStringLiterals(entries.get('roles') ?? '[]')
    const roles = roleValues.includes('all') ? [...ROLES] : roleValues

    items.push({
      name: literalText(entries.get('name') ?? '') ?? null,
      to,
      roles,
      permissions: parseStringLiterals(entries.get('permissions') ?? '')
    })
  }

  return items
}

// The SPA resolves a navigation `permissions` key through this map; the manifest
// publishes both sides so a declared gate that no longer resolves stays visible.
const PERMISSION_MAP_SOURCE = 'resources/js/composables/usePermissions.js'

export const readPermissionMap = () => {
  const source = stripComments(readRepoText(PERMISSION_MAP_SOURCE) ?? '')
  const start = source.indexOf('const PERMISSION_BY_KEY = {')
  if (start === -1) return new Map()

  const open = source.indexOf('{', start)
  const close = findBalanced(source, open, '{', '}')
  if (close === -1) return new Map()

  const map = new Map()
  for (const [key, value] of parseObjectEntries(source.slice(open + 1, close))) {
    const permission = literalText(value)
    if (permission) map.set(key, permission)
  }

  return map
}

export const buildNavigation = (items, permissionMap) =>
  items.map(item => ({
    name: item.name,
    to: item.to,
    roles: [...item.roles],
    permissions: [...item.permissions],
    permissionStrings: item.permissions
      .map(key => permissionMap.get(key))
      .filter(permission => Boolean(permission)),
    unresolvedPermissions: item.permissions.filter(key => !permissionMap.has(key))
  }))

// --- sample for detail routes ----------------------------------------------

export const isDetailRoute = path => {
  const segments = segmentsOf(path)
  const last = segments[segments.length - 1]
  return Boolean(last && last.startsWith(':'))
}

export const buildSample = (surface, surfacesByRoute) => {
  if (!isDetailRoute(surface.route)) return null

  const listRoute = `/${segmentsOf(surface.route).slice(0, -1).join('/')}`
  const list = surfacesByRoute.get(listRoute)
  const expectedSegments = segmentsOf(listRoute).length + 1

  let endpoint = null

  if (list) {
    const words = affinityVocabulary([listRoute])
    const candidates = list.endpoints
      .filter(item => !item.includes('{param}'))
      .filter(item => segmentsOf(item).length === expectedSegments)
      .sort((a, b) => routeAffinity(words, b) - routeAffinity(words, a) || a.length - b.length)
    endpoint = candidates[0] ?? null
  }

  if (!endpoint) endpoint = LIST_ENDPOINT_OVERRIDES[listRoute] ?? null
  if (!endpoint) return null

  return { endpoint, idField: 'id', route: surface.route }
}

export const resolveRoles = (route, navigation, surfacesByRoute) => {
  const navItem = navigation.find(item => item.to === route.path)
  if (navItem) return { roles: navItem.roles, rolesSource: 'navigation' }

  if (route.path === '/login' || route.path === '/reset-password') {
    return { roles: ['anonymous'], rolesSource: 'route' }
  }

  if (route.path.includes(':pathMatch')) return { roles: [...ROLES], rolesSource: 'route' }

  const segments = segmentsOf(route.path)
  if (isDetailRoute(route.path)) {
    const listRoute = `/${segments.slice(0, -1).join('/')}`
    const list = surfacesByRoute.get(listRoute)
    if (list) return { roles: [...list.roles], rolesSource: 'list-route' }
  }

  // Module sibling: the longest navigation prefix that owns this path.
  let best = null
  for (const item of navigation) {
    const itemSegments = segmentsOf(item.to)
    if (!itemSegments.length || itemSegments.length >= segments.length) continue
    const samePrefix = itemSegments.every((segment, index) => segments[index] === segment)
    if (!samePrefix) continue
    if (!best || itemSegments.length > segmentsOf(best.to).length) best = item
  }

  if (best) return { roles: [...best.roles], rolesSource: 'module-sibling' }

  const pageModule = route.page ? dirname(route.page) : null
  const sibling = pageModule
    ? [...surfacesByRoute.values()].find(
        item => item.page && dirname(item.page) === pageModule && item.roles.length > 0
      )
    : null

  if (sibling) return { roles: [...sibling.roles], rolesSource: 'module-sibling' }

  return { roles: [...ROLES], rolesSource: 'fallback' }
}

export const readRevision = () => {
  const gitPath = resolve(ROOT, '.git')
  if (!existsSync(gitPath)) return null

  let gitDir = gitPath
  if (statSync(gitPath).isFile()) {
    const pointer = readRepoText('.git') ?? ''
    const match = /gitdir:\s*(.+)/.exec(pointer)
    if (!match) return null
    gitDir = resolve(ROOT, match[1].trim())
  }

  const headPath = join(gitDir, 'HEAD')
  if (!existsSync(headPath)) return null

  const head = readFileSync(headPath, 'utf8').trim()
  if (!head.startsWith('ref: ')) return head

  const refPath = join(gitDir, head.slice(5).trim())
  if (!existsSync(refPath)) return null

  return readFileSync(refPath, 'utf8').trim()
}
