/**
 * useFieldValidation — live form validation with debounced re-validation.
 *
 * Triggers validation on a field when:
 *   - the field blurs (parent calls `validateField(field)` immediately), OR
 *   - 250ms of typing idle elapses (debounced, via the internal `watch`).
 * Success state is set immediately when a field passes all rules — no
 * debounce on success (spec V2).
 *
 * The composable is a thin Vue wrapper around the pure state machine in
 * `./fieldValidationMath.js`. The math module is Node-loadable, which is
 * what makes the PHPUnit test (`FieldValidationTest`) fast and
 * dependency-free.
 *
 * Design contract (design.md §"useFieldValidation" + spec V1–V7):
 *   - idleMs default 250 (Apple "validate on commit, not on change")
 *   - errors: reactive map (field → error message)
 *   - successes: reactive map (field → boolean)
 *   - validateField(field), validateAll(), clearField(field) — imperative
 */
import { reactive, watch } from 'vue'
import { attachValidator } from './fieldValidationMath.js'

/**
 * @template T extends Record<string, any>
 * @param {T} form          reactive form state (the same ref/reactive used by v-model)
 * @param {Record<keyof T, Array<(value: any, form: T) => string|null>>} rules
 * @param {object} [options]
 * @param {number} [options.idleMs=250]
 * @returns {{
 *   errors: import('vue').Reactive<Record<string, string>>,
 *   successes: import('vue').Reactive<Record<string, boolean>>,
 *   validateField: (field: keyof T) => void,
 *   validateAll: () => boolean,
 *   clearField: (field: keyof T) => void
 * }}
 */
export function useFieldValidation(form, rules, options = {}) {
  const errors = reactive({})
  const successes = reactive({})
  const timers = {}

  const v = attachValidator(errors, successes, timers, form, rules, options)

  // Debounced re-validation on input change. Resets the timer per-field
  // so a single late keystroke doesn't blow away the validation that just
  // fired on a sibling field.
  watch(
    () => Object.fromEntries(Object.keys(rules).map(f => [f, form[f]])),
    () => {
      for (const field of Object.keys(rules)) {
        v.scheduleValidation(field)
      }
    },
    { deep: true }
  )

  return {
    errors,
    successes,
    validateField: v.validateField,
    validateAll: v.validateAll,
    clearField: v.clearField
  }
}

export default useFieldValidation
