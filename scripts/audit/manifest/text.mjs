// Repo-relative paths and the JS/Vue text toolkit shared by the manifest layers.

import { existsSync, readFileSync, statSync } from 'node:fs'
import { dirname, relative, resolve, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

export const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..', '..')

// --- file helpers -----------------------------------------------------------

const toPosix = value => value.split(sep).join('/')

export const toRepoPath = absolute => toPosix(relative(ROOT, absolute))

export const isFile = repoPath => {
  if (typeof repoPath !== 'string' || !repoPath) return false
  const absolute = resolve(ROOT, repoPath)
  return existsSync(absolute) && statSync(absolute).isFile()
}

export const readRepoText = repoPath => {
  if (!isFile(repoPath)) return null
  return readFileSync(resolve(ROOT, repoPath), 'utf8').replace(/\r\n/g, '\n')
}

// --- text helpers -----------------------------------------------------------

export const escapeRe = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

const isQuote = ch => ch === "'" || ch === '"' || ch === '`'

// Blanks JS/PHP comments while preserving offsets, so every later index still
// points at the original source.
export const stripComments = text => {
  const chars = text.split('')
  let i = 0
  let quote = null

  while (i < chars.length) {
    const ch = chars[i]

    if (quote) {
      if (ch === '\\') {
        i += 2
        continue
      }
      if (ch === quote) quote = null
      i += 1
      continue
    }

    if (ch === '/' && chars[i + 1] === '/') {
      while (i < chars.length && chars[i] !== '\n') {
        chars[i] = ' '
        i += 1
      }
      continue
    }

    if (ch === '/' && chars[i + 1] === '*') {
      chars[i] = ' '
      chars[i + 1] = ' '
      i += 2
      while (i < chars.length && !(chars[i] === '*' && chars[i + 1] === '/')) {
        if (chars[i] !== '\n') chars[i] = ' '
        i += 1
      }
      if (i < chars.length) {
        chars[i] = ' '
        chars[i + 1] = ' '
        i += 2
      }
      continue
    }

    if (isQuote(ch)) quote = ch
    i += 1
  }

  return chars.join('')
}

export const findBalanced = (text, start, open, close) => {
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

    if (isQuote(ch)) {
      quote = ch
      i += 1
      continue
    }

    if (ch === open) depth += 1
    else if (ch === close) {
      depth -= 1
      if (depth === 0) return i
    }
    i += 1
  }

  return -1
}

const splitTopLevel = (text, separator) => {
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

    if (isQuote(ch)) {
      quote = ch
      continue
    }

    if (ch === '{' || ch === '(' || ch === '[') depth += 1
    else if (ch === '}' || ch === ')' || ch === ']') depth -= 1
    else if (depth === 0 && ch === separator) {
      parts.push(text.slice(start, i))
      start = i + 1
    }
  }

  parts.push(text.slice(start))
  return parts
}

const findTopLevelColon = text => {
  let depth = 0
  let quote = null

  for (let i = 0; i < text.length; i += 1) {
    const ch = text[i]

    if (quote) {
      if (ch === '\\') i += 1
      else if (ch === quote) quote = null
      continue
    }

    if (isQuote(ch)) quote = ch
    else if (ch === '{' || ch === '(' || ch === '[') depth += 1
    else if (ch === '}' || ch === ')' || ch === ']') depth -= 1
    else if (depth === 0 && ch === ':') return i
  }

  return -1
}

export const parseObjectEntries = text => {
  const entries = new Map()

  for (const rawEntry of splitTopLevel(text, ',')) {
    const entry = rawEntry.trim()
    if (!entry) continue
    const colon = findTopLevelColon(entry)
    if (colon === -1) continue
    const key = entry
      .slice(0, colon)
      .trim()
      .replace(/^['"]|['"]$/g, '')
    entries.set(key, entry.slice(colon + 1).trim())
  }

  return entries
}

export const splitTopLevelObjects = arrayBody => {
  const objects = []

  for (let i = 0; i < arrayBody.length; i += 1) {
    if (arrayBody[i] !== '{') continue
    const end = findBalanced(arrayBody, i, '{', '}')
    if (end === -1) break
    objects.push(arrayBody.slice(i + 1, end))
    i = end
  }

  return objects
}

export const parseStringLiterals = text => {
  const values = []
  const re = /'((?:\\.|[^'\\])*)'|"((?:\\.|[^"\\])*)"/g
  let match

  while ((match = re.exec(text))) values.push(match[1] ?? match[2])

  return values
}

export const literalText = value => {
  if (value == null) return null
  const trimmed = value.trim()
  if (!trimmed) return null

  const quoted = /^(['"])([\s\S]*)\1$/.exec(trimmed)
  if (quoted) return quoted[2]

  const template = /^`([\s\S]*)`$/.exec(trimmed)
  if (template) return template[1].replace(/\$\{[\s\S]*?\}/g, '').trim()

  return null
}

export const readTag = (text, start) => {
  let i = start + 1
  let quote = null

  while (i < text.length) {
    const ch = text[i]
    if (quote) {
      if (ch === quote) quote = null
      i += 1
      continue
    }
    if (ch === '"' || ch === "'") {
      quote = ch
      i += 1
      continue
    }
    if (ch === '>') {
      const body = text.slice(start + 1, i)
      const nameMatch = /^\s*\/?\s*([A-Za-z][\w.-]*)/.exec(body)
      return {
        raw: text.slice(start, i + 1),
        body,
        name: nameMatch ? nameMatch[1] : '',
        nameEnd: nameMatch ? nameMatch[0].length : 0,
        end: i + 1,
        selfClosing: /\/\s*$/.test(body)
      }
    }
    i += 1
  }

  return null
}

export const parseAttributes = tag => {
  const attrs = []
  const { body, nameEnd } = tag
  let i = nameEnd

  while (i < body.length) {
    while (i < body.length && /[\s/]/.test(body[i])) i += 1
    if (i >= body.length) break

    const nameStart = i
    while (i < body.length && !/[\s=/>]/.test(body[i])) i += 1
    const name = body.slice(nameStart, i)
    if (!name) {
      i += 1
      continue
    }

    while (i < body.length && /\s/.test(body[i])) i += 1

    let value = null
    if (body[i] === '=') {
      i += 1
      while (i < body.length && /\s/.test(body[i])) i += 1
      const quote = body[i]
      if (quote === '"' || quote === "'") {
        const end = body.indexOf(quote, i + 1)
        value = body.slice(i + 1, end === -1 ? body.length : end)
        i = end === -1 ? body.length : end + 1
      } else {
        const valueStart = i
        while (i < body.length && !/[\s>]/.test(body[i])) i += 1
        value = body.slice(valueStart, i)
      }
    }

    attrs.push({ name, value })
  }

  return attrs
}

export const findElementEnd = (text, from, tagName) => {
  const openRe = new RegExp(`<${escapeRe(tagName)}(?=[\\s/>])`, 'g')
  const closeRe = new RegExp(`</${escapeRe(tagName)}\\s*>`, 'g')
  let depth = 1
  let i = from

  while (i < text.length) {
    openRe.lastIndex = i
    closeRe.lastIndex = i
    const open = openRe.exec(text)
    const close = closeRe.exec(text)

    if (!close) return { inner: text.slice(from), end: text.length }
    if (open && open.index < close.index) {
      depth += 1
      i = open.index + 1
      continue
    }

    depth -= 1
    if (depth === 0)
      return { inner: text.slice(from, close.index), end: close.index + close[0].length }
    i = close.index + 1
  }

  return { inner: text.slice(from), end: text.length }
}

export const vueTemplate = content => {
  const match = /^\s*<template[\s>]/m.exec(content)
  if (!match) return ''

  const start = match.index + match[0].indexOf('<')
  const tag = readTag(content, start)
  if (!tag) return ''
  if (tag.selfClosing) return ''

  return findElementEnd(content, tag.end, 'template').inner
}

export const stripHtmlComments = html => html.replace(/<!--[\s\S]*?-->/g, ' ')

const removeSlotTemplates = html => {
  let out = ''
  let i = 0

  while (i < html.length) {
    const open = html.indexOf('<template', i)
    if (open === -1) {
      out += html.slice(i)
      break
    }

    const tag = readTag(html, open)
    if (!tag || tag.selfClosing) {
      out += html.slice(i, tag ? tag.end : html.length)
      i = tag ? tag.end : html.length
      continue
    }

    const element = findElementEnd(html, tag.end, 'template')
    const isSlot = /(^|\s)(#|v-slot)/.test(tag.body)
    out += html.slice(i, open)
    if (!isSlot) out += element.inner
    i = element.end
  }

  return out
}

export const labelFromInner = inner => {
  const raw = removeSlotTemplates(inner)
  let hasStaticWords = false

  const withExpressions = raw.replace(/\{\{([\s\S]*?)\}\}/g, (_, expression) => {
    const literals = parseStringLiterals(expression)
    if (literals.length) {
      hasStaticWords = true
      return ` ${literals.join(' / ')} `
    }
    // A non-literal binding (v-for option, computed) has no static text; its
    // expression keeps the control identifiable instead of leaving it blank.
    return ` ${expression.replace(/\s+/g, ' ').trim()} `
  })

  const text = withExpressions
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
  if (!text) return { label: null, source: null }

  // Static words outside the mustaches also make the label textual.
  const staticText = raw.replace(/\{\{[\s\S]*?\}\}/g, ' ').replace(/<[^>]*>/g, ' ')
  if (staticText.replace(/\s+/g, ' ').trim()) hasStaticWords = true

  return { label: text, source: hasStaticWords ? 'text' : 'binding' }
}

export const attrValue = (attrs, name) => {
  const found = attrs.find(attr => attr.name === name)
  return found ? found.value : null
}

export const firstAttrMatching = (attrs, predicate) => {
  const found = attrs.find(attr => predicate(attr.name))
  return found || null
}

export const staticOrLiteral = value => {
  if (value == null) return null
  const literal = literalText(value)
  return literal ?? value.trim()
}
