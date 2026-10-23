import { computed, ref } from 'vue'

/**
 * #304 (S4/S8) — the accounting lock speaks a machine code, the notice speaks
 * the API message.
 *
 * The 403 of `App\Exceptions\AccountingLockBlockedException` carries
 * `code: "accounting_lock_blocked"`, so the money modals recognise the lock
 * without matching the copy. The reason is validated locally to save a round
 * trip, but the server stays the authority: its 422 lands on this same notice.
 */
export const ACCOUNTING_LOCK_CODE = 'accounting_lock_blocked'

export const ACCOUNTING_LOCK_REASON_MIN_LENGTH = 10

const firstMessage = value => {
  if (Array.isArray(value)) return value[0] || ''
  return value || ''
}

export function isAccountingLockBlocked(error) {
  return error?.response?.data?.code === ACCOUNTING_LOCK_CODE
}

export function useAccountingLock() {
  const blocked = ref(false)
  const message = ref('')
  const reason = ref('')
  const reasonError = ref('')

  const reasonLength = computed(() => reason.value.trim().length)

  const reasonValid = computed(() => reasonLength.value >= ACCOUNTING_LOCK_REASON_MIN_LENGTH)

  const reset = () => {
    blocked.value = false
    message.value = ''
    reason.value = ''
    reasonError.value = ''
  }

  // Returns true when the failure was the lock, so the caller stops treating
  // it as a generic error.
  const detect = error => {
    if (!isAccountingLockBlocked(error)) return false

    blocked.value = true
    message.value = error.response?.data?.message || 'La operación está bloqueada contablemente.'
    reasonError.value = ''

    return true
  }

  const applyValidationErrors = errors => {
    const reasonMessage = firstMessage(errors?.lock_override_reason)
    if (!reasonMessage) return false

    reasonError.value = String(reasonMessage)

    return true
  }

  const authorize = () => {
    if (!reasonValid.value) {
      reasonError.value = `Escribe un motivo de al menos ${ACCOUNTING_LOCK_REASON_MIN_LENGTH} caracteres.`
      return null
    }

    reasonError.value = ''

    return { lock_override_reason: reason.value.trim() }
  }

  return {
    blocked,
    message,
    reason,
    reasonError,
    reasonLength,
    reasonValid,
    reset,
    detect,
    applyValidationErrors,
    authorize
  }
}
