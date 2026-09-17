module.exports = {
  root: true,
  env: {
    node: true,
    browser: true,
    es2021: true
  },
  extends: [
    'eslint:recommended',
    'plugin:vue/vue3-essential',
    'plugin:vue/vue3-strongly-recommended',
    'plugin:vue/vue3-recommended',
    // MUST stay last. ESLint resolves `extends` last-wins, and this is the
    // config that switches the formatting rules OFF. Placed before the Vue
    // presets it did nothing, because those presets re-enabled every rule it
    // had just disabled — which is why `pnpm lint:check` reported 1071
    // vue/html-indent errors and the CI gate could never pass. Prettier owns
    // formatting (see .prettierrc); ESLint owns correctness.
    '@vue/eslint-config-prettier'
  ],
  parserOptions: {
    ecmaVersion: 2021,
    sourceType: 'module'
  },
  plugins: [
    'vue'
  ],
  rules: {
    // Vue specific rules — correctness only. Formatting rules that Prettier
    // already enforces (html-indent, max-attributes-per-line,
    // html-self-closing, script-indent) are deliberately absent.
    'vue/multi-word-component-names': 'off',
    'vue/no-unused-vars': 'error',
    'vue/no-multiple-template-root': 'off',
    'vue/component-definition-name-casing': ['error', 'PascalCase'],
    'vue/component-name-in-template-casing': ['error', 'PascalCase'],
    'vue/custom-event-name-casing': ['error', 'camelCase'],
    'vue/define-macros-order': ['error', {
      'order': ['defineProps', 'defineEmits']
    }],
    'vue/no-unused-refs': 'error',
    'vue/no-useless-v-bind': 'error',
    'vue/prefer-separate-static-class': 'error',
    'vue/prefer-true-attribute-shorthand': 'error',

    // General JavaScript rules — correctness and naming only. The whitespace,
    // punctuation and quoting family (semi, quotes, comma-*, *-spacing,
    // indent, brace-style, operator-linebreak, ...) is intentionally gone:
    // it duplicated Prettier and disagreed with it.
    'no-console': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
    'no-debugger': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
    'no-unused-vars': 'error',
    'no-undef': 'error',
    'prefer-const': 'error',
    'no-var': 'error',
    'object-shorthand': 'error',
    'prefer-template': 'error',
    'camelcase': ['error', { 'properties': 'never' }],
    'new-cap': ['error', { 'newIsCap': true, 'capIsNew': false }],
    'no-array-constructor': 'error',
    'no-new-object': 'error',
    'no-new-wrappers': 'error',
    'no-unneeded-ternary': 'error',
    'one-var': ['error', 'never'],
    'operator-assignment': ['error', 'always'],
    'prefer-arrow-callback': 'error',
    'prefer-destructuring': ['error', {
      'array': false,
      'object': true
    }],
    'prefer-rest-params': 'error',
    'prefer-spread': 'error',
    'symbol-description': 'error'
  },
  globals: {
    defineProps: 'readonly',
    defineEmits: 'readonly',
    defineExpose: 'readonly',
    withDefaults: 'readonly'
  }
}
