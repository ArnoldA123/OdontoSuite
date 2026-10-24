// routes/api.php and Permissions.php: the route table and its permission gates.

import { findBalanced, parseStringLiterals, readRepoText, stripComments } from './text.mjs'

// --- PHP: permissions and route table --------------------------------------

const readPermissionConstants = () => {
  const source = stripComments(readRepoText('app/Authorization/Permissions.php') ?? '')
  const constants = new Map()
  const re = /public const ([A-Z0-9_]+)\s*=\s*'([^']*)'\s*;/g
  let match

  while ((match = re.exec(source))) constants.set(match[1], match[2])

  return constants
}

const splitPhpTopLevel = (text, separator) => {
  const parts = []
  let start = 0
  let depth = 0
  let quote = null

  for (let i = 0; i < text.length; i += 1) {
    const ch = text[i]

    if (quote) {
      if (ch === '\\') i += 1
      else if (ch === quote) quote = null
      continue
    }

    if (ch === "'" || ch === '"') quote = ch
    else if (ch === '[' || ch === '(') depth += 1
    else if (ch === ']' || ch === ')') depth -= 1
    else if (depth === 0 && ch === separator) {
      parts.push(text.slice(start, i))
      start = i + 1
    }
  }

  parts.push(text.slice(start))
  return parts
}

const evalPhpScalar = (text, constants) => {
  const trimmed = text.trim()

  const single = /^'((?:\\.|[^'\\])*)'$/.exec(trimmed)
  if (single) return single[1].replace(/\\(['\\])/g, '$1')

  const double = /^"((?:\\.|[^"\\])*)"$/.exec(trimmed)
  if (double) return double[1].replace(/\\(["\\])/g, '$1')

  const reference = /^(?:\\?App\\Authorization\\)?Permissions::([A-Z0-9_]+)$/.exec(trimmed)
  if (reference) return constants.get(reference[1]) ?? null

  return null
}

const evalPhpValue = (text, constants) => {
  const trimmed = text.trim()
  if (!trimmed) return null

  if (trimmed.startsWith('[')) {
    const close = trimmed.lastIndexOf(']')
    if (close === -1) return null
    const values = []
    for (const part of splitPhpTopLevel(trimmed.slice(1, close), ',')) {
      const value = evalPhpValue(part, constants)
      if (Array.isArray(value)) values.push(...value)
      else if (value != null) values.push(value)
    }
    return values
  }

  const concatenated = splitPhpTopLevel(trimmed, '.')
  const values = concatenated.map(part => evalPhpScalar(part, constants))
  if (values.some(value => value == null)) return null

  return values.join('')
}

const extractChainedArgs = (text, callName) => {
  const results = []
  const re = new RegExp(`(?:->|Route::)\\s*${callName}\\s*\\(`, 'g')
  let match

  while ((match = re.exec(text))) {
    const open = text.indexOf('(', match.index)
    const close = findBalanced(text, open, '(', ')')
    if (close === -1) continue
    results.push(text.slice(open + 1, close))
    re.lastIndex = close
  }

  return results
}

const readStatement = (text, start) => {
  let depth = 0
  let quote = null
  let i = start

  while (i < text.length) {
    const ch = text[i]

    if (quote) {
      if (ch === '\\') {
        i += 2
        continue
      }
      if (ch === quote) quote = null
      i += 1
      continue
    }

    if (ch === "'" || ch === '"') {
      quote = ch
      i += 1
      continue
    }

    if (ch === '{' || ch === '(' || ch === '[') depth += 1
    else if (ch === '}' || ch === ')' || ch === ']') depth -= 1
    else if (ch === ';' && depth === 0) return { text: text.slice(start, i + 1), end: i + 1 }

    i += 1
  }

  return { text: text.slice(start), end: text.length }
}

const joinUri = (prefix, uri) => {
  const parts = [prefix, uri].map(value => value.replace(/^\/+|\/+$/g, '')).filter(Boolean)
  return parts.join('/')
}

const permissionsFromMiddleware = middleware => {
  const permissions = []

  for (const entry of middleware) {
    if (typeof entry !== 'string') continue
    if (!entry.startsWith('permission:')) continue
    for (const permission of entry.slice('permission:'.length).split(',')) {
      const trimmed = permission.trim()
      if (trimmed) permissions.push(trimmed)
    }
  }

  return permissions
}

const API_RESOURCE_ACTIONS = {
  index: { methods: ['GET'], item: false },
  store: { methods: ['POST'], item: false },
  show: { methods: ['GET'], item: true },
  update: { methods: ['PUT', 'PATCH'], item: true },
  destroy: { methods: ['DELETE'], item: true }
}

const expandApiResource = (name, statement, inherited) => {
  const cleanName = name.replace(/^\/+|\/+$/g, '')
  const onlyArgs = extractChainedArgs(statement, 'only')
  const actions = new Set(Object.keys(API_RESOURCE_ACTIONS))

  if (onlyArgs.length) {
    actions.clear()
    for (const action of parseStringLiterals(onlyArgs[0])) actions.add(action)
  }

  const routes = []
  for (const action of actions) {
    const spec = API_RESOURCE_ACTIONS[action]
    if (!spec) continue
    routes.push({
      uri: spec.item ? `${cleanName}/{id}` : cleanName,
      methods: spec.methods,
      prefix: inherited.prefix,
      middleware: inherited.middleware,
      permissionOwner: inherited.permissionOwner
    })
  }

  return routes
}

const parseApiScope = (text, inherited, constants, out) => {
  let i = 0

  while (i < text.length) {
    const idx = text.indexOf('Route::', i)
    if (idx === -1) break

    const before = text.slice(0, idx).trimEnd()
    const previous = before.slice(-1)
    if (previous && ![';', '{', '}', ')'].includes(previous)) {
      i = idx + 'Route::'.length
      continue
    }

    const statement = readStatement(text, idx)
    const body = statement.text

    if (body.includes('->group(')) {
      const groupPos = body.indexOf('->group(')
      const chain = body.slice(0, groupPos)
      const braceOpen = body.indexOf('{', body.indexOf('(', groupPos))
      const braceClose = braceOpen === -1 ? -1 : findBalanced(body, braceOpen, '{', '}')

      const middleware = [...inherited.middleware]
      for (const arg of extractChainedArgs(chain, 'middleware')) {
        const value = evalPhpValue(arg, constants)
        if (Array.isArray(value)) middleware.push(...value)
        else if (typeof value === 'string') middleware.push(value)
      }

      const { prefix: inheritedPrefix } = inherited
      let prefix = inheritedPrefix
      for (const arg of extractChainedArgs(chain, 'prefix')) {
        const value = evalPhpValue(arg, constants)
        if (typeof value === 'string') prefix = joinUri(prefix, value)
      }

      if (braceOpen !== -1 && braceClose !== -1) {
        parseApiScope(
          body.slice(braceOpen + 1, braceClose),
          { middleware, prefix, permissionOwner: inherited.permissionOwner },
          constants,
          out
        )
      }

      i = statement.end
      continue
    }

    const methodMatch = /^Route::([A-Za-z]+)\s*\(/.exec(body)
    if (methodMatch) {
      const method = methodMatch[1]
      const open = body.indexOf('(', methodMatch.index)
      const close = findBalanced(body, open, '(', ')')
      const args = close === -1 ? [] : splitPhpTopLevel(body.slice(open + 1, close), ',')

      const middleware = [...inherited.middleware]
      for (const arg of extractChainedArgs(body, 'middleware')) {
        const value = evalPhpValue(arg, constants)
        if (Array.isArray(value)) middleware.push(...value)
        else if (typeof value === 'string') middleware.push(value)
      }

      const base = { prefix: inherited.prefix, middleware, permissionOwner: 'routes/api.php' }

      if (method === 'apiResource' && args.length) {
        const name = evalPhpScalar(args[0], constants)
        if (name) out.push(...expandApiResource(name, body, base))
      } else if (['get', 'post', 'put', 'patch', 'delete', 'options', 'any'].includes(method)) {
        const uri = args.length ? evalPhpScalar(args[0], constants) : null
        if (uri != null) out.push({ uri, methods: [method.toUpperCase()], ...base })
      } else if (method === 'match' && args.length >= 2) {
        const methods = evalPhpValue(args[0], constants)
        const uri = evalPhpScalar(args[1], constants)
        if (uri != null) {
          out.push({
            uri,
            methods: Array.isArray(methods)
              ? methods.map(value => String(value).toUpperCase())
              : [],
            ...base
          })
        }
      }
    }

    i = statement.end
  }
}

export const readApiRoutes = () => {
  const constants = readPermissionConstants()
  const source = stripComments(readRepoText('routes/api.php') ?? '')
  const routes = []

  parseApiScope(source, { prefix: '', middleware: [], permissionOwner: null }, constants, routes)

  return routes.map(route => {
    const uri = joinUri('api', joinUri(route.prefix, route.uri))
    return {
      pattern: `/${uri}`,
      methods: route.methods,
      permissions: permissionsFromMiddleware(route.middleware)
    }
  })
}

export const segmentsOf = uri => uri.split('/').filter(Boolean)

const isParamSegment = segment => /^\{[^}]+\}$/.test(segment)

const matchEndpoint = (endpoint, route) => {
  const endpointSegments = segmentsOf(endpoint)
  const patternSegments = segmentsOf(route.pattern)
  if (endpointSegments.length !== patternSegments.length) return null

  let params = 0
  for (let i = 0; i < patternSegments.length; i += 1) {
    if (patternSegments[i] === endpointSegments[i]) continue
    if (isParamSegment(patternSegments[i])) {
      params += 1
      continue
    }
    return null
  }

  return { params }
}

export const routeAffinity = (affinityWords, pattern) =>
  segmentsOf(pattern).filter(segment => affinityWords.has(segment)).length

export const affinityVocabulary = paths => {
  const words = new Set()
  for (const path of paths) {
    for (const segment of segmentsOf(path)) {
      if (!segment.startsWith(':')) words.add(segment)
    }
  }
  return words
}

// One page calls shared lookups (`/api/users/active`, `/api/branches/active`),
// so specificity alone would attribute a surface to a lookup. Path affinity and
// permission frequency decide across endpoints; parameters, segments and GET
// decide inside one pattern's matches.
export const resolvePermission = (affinityPaths, endpoints, apiRoutes) => {
  const vocabulary = affinityVocabulary(affinityPaths)
  const matches = []
  const coverage = new Map()
  const frequency = new Map()
  const matchedPermissions = new Set()

  for (const endpoint of endpoints) {
    const hits = new Set()

    for (const apiRoute of apiRoutes) {
      const match = matchEndpoint(endpoint, apiRoute)
      if (!match) continue

      hits.add(apiRoute.pattern)
      for (const permission of apiRoute.permissions) matchedPermissions.add(permission)
      if (apiRoute.permissions.length) {
        const first = apiRoute.permissions[0]
        frequency.set(first, (frequency.get(first) ?? 0) + 1)
      }

      matches.push({
        pattern: apiRoute.pattern,
        params: match.params,
        segments: segmentsOf(apiRoute.pattern).length,
        affinity: routeAffinity(vocabulary, apiRoute.pattern),
        get: apiRoute.methods.includes('GET') ? 0 : 1,
        permissions: apiRoute.permissions
      })
    }

    for (const pattern of hits) coverage.set(pattern, (coverage.get(pattern) ?? 0) + 1)
  }

  matches.sort(
    (a, b) =>
      b.affinity - a.affinity ||
      a.params - b.params ||
      (frequency.get(b.permissions[0]) ?? 0) - (frequency.get(a.permissions[0]) ?? 0) ||
      (coverage.get(b.pattern) ?? 0) - (coverage.get(a.pattern) ?? 0) ||
      a.segments - b.segments ||
      a.get - b.get
  )

  const best = matches.find(match => match.get === 0 && match.permissions.length > 0)

  return {
    permission: best ? best.permissions[0] : null,
    permissions: [...matchedPermissions],
    permissionSource: best ? 'routes/api.php' : 'unresolved',
    pattern: best ? best.pattern : null
  }
}
