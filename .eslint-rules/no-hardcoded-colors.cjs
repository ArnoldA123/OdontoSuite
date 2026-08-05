/**
 * @fileoverview ESLint rule: no-hardcoded-colors
 *
 * Forbids hex color literals (e.g. `#1A73E8`) in `.vue`/`.js` source files.
 * Allowed in:
 *   - resources/js/design-system/tokens.js (single source of truth)
 *   - resources/css/themes.css (CSS variables, not JS)
 *   - Inline string literals named `transparent`, `currentColor`, or `inherit`
 *
 * Rationale: bugfix-2026-08 / slice 06 enforces a single design-token surface.
 * Components MUST consume `bg-success-500`, `text-error-700`, etc. instead of
 * raw hex values. This rule is the lint guard.
 *
 * Pattern matches: `'#abc'`, `"#abcdef"`, `'#abcdef12'` (3/6/8-digit hex).
 */
'use strict'

const HEX_RE = /#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b/

// Files allowed to ship hex literals (design-token surface).
const ALLOW_FILES = [
  'resources/js/design-system/tokens.js',
  'resources/css/themes.css'
]

module.exports = {
  meta: {
    type: 'problem',
    docs: {
      description:
        'Disallow hardcoded hex color literals outside design-system/tokens.js. Use semantic Tailwind tokens (bg-success-500, text-error-700, etc.) instead.'
    },
    schema: [],
    messages: {
      hardcodedHex:
        "Hardcoded hex color '{{value}}' is not allowed. Use a semantic Tailwind token from resources/js/design-system/tokens.js (e.g. bg-{{state}}-{{step}})."
    }
  },

  create(context) {
    const filename = context.getFilename().replace(/\\/g, '/')
    if (ALLOW_FILES.some(allow => filename.endsWith(allow))) {
      return {}
    }

    function checkLiteral(node) {
      if (typeof node.value !== 'string') return
      const match = node.value.match(HEX_RE)
      if (!match) return
      context.report({
        node,
        messageId: 'hardcodedHex',
        data: { value: match[0] }
      })
    }

    return {
      Literal: checkLiteral,
      TemplateElement(node) {
        if (!node.value || typeof node.value.raw !== 'string') return
        const match = node.value.raw.match(HEX_RE)
        if (!match) return
        context.report({
          node,
          messageId: 'hardcodedHex',
          data: { value: match[0] }
        })
      }
    }
  }
}
