/**
 * fieldValidationMath — pure state-machine + debounce primitives for the
 * `useFieldValidation` composable.
 *
 * Mirrors the `useSpringMath.js` + `magneticHoverMath.js` separation
 * pattern: the pure logic lives in its own module so the PHPUnit suite
 * (`tests/Unit/Composables/FieldValidationTest.php`) can shell out to
 * `node -e` and exercise the state machine + timer logic without
 * booting a Vue runtime.
 *
 * The composable file (`useFieldValidation.js`) wraps this module with
 * Vue's `reactive` and `watch` primitives for reactivity. The Vue
 * wrapper is a thin shell — every behavioural decision lives here.
 *
 * No imports — keeps the module Node-loadable in a plain ESM context.
 */

/**
 * Attach a field-validation state machine to the provided containers.
 * The containers (`errors`, `successes`, `timers`) are mutated in place
 * so the composable can wrap them with Vue's `reactive()` and have
 * changes propagate to the template automatically.
 *
 * @param {Record<string,string>} errors    mutable error map (field → message)
 * @param {Record<string,boolean>} successes mutable success map (field → boolean)
 * @param {Record<string,*>} timers         mutable timer handles (used internally)
 * @param {Record<string,any>} form         the reactive form state
 * @param {Record<string, Array<(value:any, form:any) => string|null>>} rules
 * @param {{idleMs?: number}} [options]
 * @returns {{
 *   runRules: (field: string) => void,
 *   validateField: (field: string) => void,
 *   validateAll: () => boolean,
 *   clearField: (field: string) => void,
 *   scheduleValidation: (field: string) => void,
 * }}
 */
export function attachValidator(errors, successes, timers, form, rules, options = {}) {
  const { idleMs = 250 } = options

  /**
   * Evaluate every rule for `field` in order. The first rule that
   * returns a non-null string sets `errors[field]` and stops. If all
   * rules return null, `errors[field] = ''` and `successes[field] = true`.
   *
   * @param {string} field
   */
  function runRules(field) {
    const value = form[field]
    const fieldRules = rules[field] || []
    for (const rule of fieldRules) {
      const err = rule(value, form)
      if (err) {
        errors[field] = err
        successes[field] = false
        return
      }
    }
    errors[field] = ''
    successes[field] = true
  }

  /**
   * Cancel any pending debounce timer for `field`, then run rules
   * immediately. Called from the parent on `@blur`.
   *
   * @param {string} field
   */
  function validateField(field) {
    const t = timers[field]
    if (t !== undefined) {
      clearTimeout(t)
      delete timers[field]
    }
    runRules(field)
  }

  /**
   * Reset both states for `field`. Errors become '' and successes
   * become false.
   *
   * @param {string} field
   */
  function clearField(field) {
    errors[field] = ''
    successes[field] = false
  }

  /**
   * Run rules for every field declared in `rules`. Returns true if ALL
   * fields pass (no error), false if ANY field has an error.
   *
   * @returns {boolean}
   */
  function validateAll() {
    let pass = true
    for (const field of Object.keys(rules)) {
      runRules(field)
      if (errors[field]) pass = false
    }
    return pass
  }

  /**
   * Schedule (or reschedule) a debounced validation for `field`. Any
   * pending timer for the field is cancelled first (V4: input changes
   * cancel and reschedule). Called from the parent's `watch`.
   *
   * @param {string} field
   */
  function scheduleValidation(field) {
    const t = timers[field]
    if (t !== undefined) {
      clearTimeout(t)
    }
    timers[field] = setTimeout(() => {
      delete timers[field]
      runRules(field)
    }, idleMs)
  }

  return { runRules, validateField, validateAll, clearField, scheduleValidation }
}

export default attachValidator
