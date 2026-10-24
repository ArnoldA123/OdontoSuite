// Page sources: the import graph, its tabs, dialogs, actions and endpoints.

import { dirname, join } from 'node:path'

import {
  attrValue,
  escapeRe,
  findBalanced,
  findElementEnd,
  firstAttrMatching,
  isFile,
  labelFromInner,
  literalText,
  parseAttributes,
  parseObjectEntries,
  readRepoText,
  readTag,
  splitTopLevelObjects,
  staticOrLiteral,
  stripHtmlComments,
  toRepoPath,
  vueTemplate
} from './text.mjs'

const UI_EXCLUDED_ROOTS = ['resources/js/components/ui/', 'resources/js/components/layout/']

// Session shell endpoints come from `useAuth`/`useEcho`, which every surface
// reaches through its composables; only the auth page owns the login call.
const SHELL_ENDPOINTS = new Set([
  '/api/auth/login',
  '/api/auth/logout',
  '/api/auth/me',
  '/api/auth/refresh',
  '/api/auth/forgot-password',
  '/api/auth/reset-password',
  '/api/broadcasting/auth'
])

const AUTH_SURFACES = new Set(['/login', '/reset-password'])

const KIND_RULES = [
  ['export', ['exportar', 'descargar', 'pdf', 'csv', 'imprimir', 'export']],
  [
    'delete',
    [
      'eliminar',
      'borrar',
      'anular',
      'desactivar',
      'quitar',
      'rechazar',
      'descartar',
      'delete',
      'destroy',
      'remove',
      'void'
    ]
  ],
  [
    'create',
    [
      'nuevo',
      'nueva',
      'agregar',
      'crear',
      'registrar',
      'alta',
      'anadir',
      'adicionar',
      'new',
      'create',
      'add'
    ]
  ],
  ['edit', ['editar', 'modificar', 'edit', 'rename']],
  [
    'submit',
    [
      'guardar',
      'enviar',
      'confirmar',
      'aprobar',
      'firmar',
      'aceptar',
      'save',
      'submit',
      'confirm',
      'approve',
      'sign'
    ]
  ],
  ['cancel', ['cancelar', 'volver', 'cerrar', 'cancel', 'back', 'close']],
  ['search', ['buscar', 'search', 'find']],
  ['filter', ['filtrar', 'limpiar', 'aplicar', 'filter', 'clear']],
  ['paginate', ['anterior', 'siguiente', 'pagina', 'paginacion', 'page', 'prev', 'next']],
  ['refresh', ['actualizar', 'recargar', 'reintentar', 'refresh', 'reload', 'retry']],
  ['toggle', ['mostrar', 'ocultar', 'expandir', 'contraer', 'colapsar', 'toggle', 'show', 'hide']],
  [
    'open',
    [
      'ver',
      'abrir',
      'detalle',
      'seleccionar',
      'elegir',
      'consultar',
      'open',
      'view',
      'detail',
      'select'
    ]
  ]
]

export const resolveStaticImport = (identifier, source) => {
  const re = new RegExp(`import\\s+${escapeRe(identifier)}\\s+from\\s+['"]([^'"]+)['"]`)
  const match = re.exec(source)
  if (!match) return null
  return resolveSpecifier(match[1], 'resources/js/app.js')
}

export const resolveSpecifier = (specifier, fromPath) => {
  if (!specifier.startsWith('.') && !specifier.startsWith('@/')) return null

  const base = specifier.startsWith('@/')
    ? join('resources/js', specifier.slice(2))
    : join(dirname(fromPath), specifier)

  const candidates = [base, `${base}.vue`, `${base}.js`, `${base}/index.js`, `${base}/index.vue`]
  for (const candidate of candidates) {
    const repoPath = toRepoPath(candidate)
    if (isFile(repoPath)) return repoPath
  }

  return null
}

// --- module sources ---------------------------------------------------------

// The import graph is the host relation: any module under `resources/js` may
// import any other, so only the shell primitives and the app entry are excluded.
const sourceIsAllowed = candidate => {
  if (candidate === 'resources/js/app.js') return false
  if (!candidate.startsWith('resources/js/')) return false
  return !UI_EXCLUDED_ROOTS.some(excluded => candidate.startsWith(excluded))
}

const extractImports = content => {
  const specifiers = []
  const fromRe = /\b(?:import|export)\s+[^'"`;]*?\bfrom\s*['"]([^'"]+)['"]/g
  const bareRe = /\bimport\s*['"]([^'"]+)['"]/g
  let match

  while ((match = fromRe.exec(content))) specifiers.push(match[1])
  while ((match = bareRe.exec(content))) specifiers.push(match[1])

  return specifiers
}

export const gatherSources = pagePath => {
  const seen = new Map()
  const queue = [{ path: pagePath, depth: 0 }]

  while (queue.length) {
    const { path, depth } = queue.shift()
    if (seen.has(path)) continue

    const content = readRepoText(path)
    if (content == null) continue
    seen.set(path, content)

    if (depth >= 4) continue

    for (const specifier of extractImports(content)) {
      const resolved = resolveSpecifier(specifier, path)
      if (!resolved || seen.has(resolved)) continue
      if (!sourceIsAllowed(resolved)) continue
      queue.push({ path: resolved, depth: depth + 1 })
    }
  }

  return [...seen.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([path, content]) => ({ path, content }))
}

// --- tabs, dialogs, actions -------------------------------------------------

export const readTabs = pagePath => {
  if (!pagePath) return []

  const content = readRepoText(pagePath)
  if (!content) return []

  const template = stripHtmlComments(vueTemplate(content))
  const tabs = []
  const re = /<UiTabs(?=[\s>])/g
  let match

  while ((match = re.exec(template))) {
    const tag = readTag(template, match.index)
    if (!tag) break
    const attrs = parseAttributes(tag)
    const binding = firstAttrMatching(attrs, name => name === ':tabs' || name === 'v-bind:tabs')
    if (binding && binding.value != null)
      tabs.push(...resolveTabBinding(binding.value.trim(), content))
    re.lastIndex = tag.end
  }

  return tabs
}

const tabsFromArrayLiteral = arrayText => {
  const tabs = []

  for (const objectText of splitTopLevelObjects(arrayText)) {
    const entries = parseObjectEntries(objectText)
    const label = literalText(entries.get('label') ?? '')
    if (!label) continue
    tabs.push({ id: literalText(entries.get('id') ?? '') ?? null, label })
  }

  return tabs
}

const resolveTabBinding = (binding, content) => {
  if (binding.startsWith('[')) {
    const open = binding.indexOf('[')
    const close = findBalanced(binding, open, '[', ']')
    if (close !== -1) return tabsFromArrayLiteral(binding.slice(open + 1, close))
  }

  if (/^[A-Za-z_$][\w$]*$/.test(binding)) {
    const re = new RegExp(`(?:const|let|var)\\s+${escapeRe(binding)}\\s*=\\s*\\[`)
    const match = re.exec(content)
    if (match) {
      const open = content.indexOf('[', match.index + match[0].length - 1)
      const close = findBalanced(content, open, '[', ']')
      if (close !== -1) return tabsFromArrayLiteral(content.slice(open + 1, close))
    }
  }

  return [{ binding, labels: [], resolved: false }]
}

export const collectDialogs = sources => {
  const dialogs = []
  const seen = new Set()

  for (const source of sources) {
    if (!source.path.endsWith('.vue')) continue
    const template = stripHtmlComments(vueTemplate(source.content))
    const re = /<(UiModal|UiSheet)(?=[\s>])/g
    let match

    while ((match = re.exec(template))) {
      const tag = readTag(template, match.index)
      if (!tag) break
      const attrs = parseAttributes(tag)
      const primitive = match[1] === 'UiModal' ? 'modal' : 'sheet'

      const staticTitle = attrValue(attrs, 'title')
      const boundTitle = attrValue(attrs, ':title') ?? attrValue(attrs, 'v-bind:title')
      const title = staticTitle != null ? staticTitle.trim() : literalText(boundTitle ?? '')
      const titleExpression = staticTitle != null ? null : (boundTitle ?? null)

      const modelBinding = firstAttrMatching(
        attrs,
        name => name === 'v-model' || name === ':model-value' || name === ':show' || name === 'v-if'
      )
      const dataHook = firstAttrMatching(attrs, name => name.startsWith('data-'))

      const key = `${source.path}|${primitive}|${dataHook ? dataHook.name : ''}`
      if (!seen.has(key)) {
        seen.add(key)
        dialogs.push({
          primitive,
          title: title || null,
          titleExpression,
          binding: modelBinding ? modelBinding.value : null,
          dataHook: dataHook ? dataHook.name : null,
          file: source.path
        })
      }

      re.lastIndex = tag.end
    }
  }

  return dialogs
}

const simpleCallTarget = expression => {
  const trimmed = expression.replace(/\s+/g, ' ').trim()
  if (!trimmed) return null

  const call = /^[A-Za-z_$][\w$]*(?:\.[A-Za-z_$][\w$]*)*\s*\(([\s\S]*)\)$/.exec(trimmed)
  if (call && !trimmed.includes('=>')) {
    return trimmed.slice(0, trimmed.indexOf('(')).trim()
  }

  return trimmed
}

const resolveTarget = attrs => {
  const navigation = firstAttrMatching(
    attrs,
    name => name === 'to' || name === ':to' || name === 'href' || name === ':href'
  )
  if (navigation && navigation.value != null) {
    return { target: staticOrLiteral(navigation.value), navigates: true }
  }

  const click = firstAttrMatching(attrs, name => /^(?:@|v-on:)click(?:\.|$)/.test(name))
  if (click && click.value != null) {
    const target = simpleCallTarget(click.value)
    if (target) return { target, navigates: NAVIGATE_HANDLER.test(target) }
  }

  const type = attrValue(attrs, 'type')
  if (type) return { target: type.trim(), navigates: false }

  return { target: '', navigates: false }
}

export const collectActions = sources => {
  const actions = []
  const seen = new Set()

  const push = action => {
    const key = [action.file, action.label, action.target, action.selector].join('|')
    if (seen.has(key)) return
    seen.add(key)
    actions.push(action)
  }

  for (const source of sources) {
    if (!source.path.endsWith('.vue')) continue
    const template = stripHtmlComments(vueTemplate(source.content))

    for (const tagName of ['UiButton', 'button']) {
      const re = new RegExp(`<${escapeRe(tagName)}(?=[\\s/>])`, 'g')
      let match

      while ((match = re.exec(template))) {
        const tag = readTag(template, match.index)
        if (!tag) break

        const attrs = parseAttributes(tag)
        const { end: tagEnd, selfClosing } = tag
        let inner = ''
        let end = tagEnd

        if (!selfClosing) {
          const { inner: elementInner, end: elementEnd } = findElementEnd(
            template,
            tag.end,
            tagName
          )
          inner = elementInner
          end = elementEnd
        }

        const { label: innerText, source: innerSource } = labelFromInner(inner)
        let label = innerText
        let labelSource = innerSource

        if (!label) {
          label =
            staticOrLiteral(attrValue(attrs, 'aria-label') ?? attrValue(attrs, ':aria-label')) ?? ''
          if (label) labelSource = 'aria'
        }
        if (!label) {
          label = staticOrLiteral(attrValue(attrs, 'title') ?? attrValue(attrs, ':title')) ?? ''
          if (label) labelSource = 'title'
        }

        const { target, navigates } = resolveTarget(attrs)
        // Last resort: an icon-only control has no text, aria-label or title. The
        // handler keeps it identifiable (layer 3 is what audits the a11y label).
        if (!label && target) {
          label = target
          labelSource = 'handler'
        }

        const dataHook = firstAttrMatching(attrs, name => name.startsWith('data-'))
        const kind = !label && !target ? 'unclassified' : classify(label, target, navigates)

        push({
          label,
          kind,
          target,
          file: source.path,
          selector: dataHook ? `[${dataHook.name}]` : '',
          labelSource: labelSource ?? null
        })

        re.lastIndex = end
      }
    }
  }

  return actions
}

// --- endpoint extraction ----------------------------------------------------

const normalizeEndpoint = raw => {
  let path = raw.split(/[?#`'"]/)[0]
  path = path.replace(/\/\$\{[^}]*\}/g, '/{param}')
  path = path.split('${')[0]
  path = path.split(')')[0]
  path = path.replace(/\/+$/, '')

  return path || null
}

export const collectEndpoints = sources => {
  const endpoints = new Set()

  for (const source of sources) {
    const re = /(?:'|"|`)(\/api\/[^'"`\s]*)/g
    let match

    while ((match = re.exec(source.content))) {
      const endpoint = normalizeEndpoint(match[1])
      if (endpoint && endpoint.startsWith('/api')) endpoints.add(endpoint)
    }
  }

  return [...endpoints].sort()
}

export const visibleEndpoints = (endpoints, routePath) => {
  if (AUTH_SURFACES.has(routePath)) return endpoints
  return endpoints.filter(endpoint => !SHELL_ENDPOINTS.has(endpoint))
}

// --- kind classification ----------------------------------------------------

const normalizeWords = value =>
  value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .split(/[^a-z0-9]+/)
    .filter(Boolean)

const NAVIGATE_HANDLER = /^(?:(?:router\.)?(?:push|replace)\b|navigate\w*)/

const classify = (label, target, navigates) => {
  const words = normalizeWords(label)

  for (const [kind, keywords] of KIND_RULES) {
    if (words.some(word => keywords.includes(word))) return kind
  }

  if (navigates) return 'navigate'

  if (target) {
    const targetWords = normalizeWords(target)
    for (const [kind, keywords] of KIND_RULES) {
      if (targetWords.some(word => keywords.includes(word))) return kind
    }
  }

  return 'action'
}
