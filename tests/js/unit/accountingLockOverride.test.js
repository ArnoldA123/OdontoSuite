import { describe, expect, it } from 'vitest'
import {
  ACCOUNTING_LOCK_CODE,
  ACCOUNTING_LOCK_REASON_MIN_LENGTH,
  isAccountingLockBlocked,
  useAccountingLock
} from '../../../resources/js/composables/useAccountingLock.js'

const BLOCKED_MESSAGE =
  'El módulo [cash_transactions] está bloqueado contablemente hasta el 2026-09-30: ' +
  'la operación [update] sobre un registro de esa fecha requiere autorización registrada.'

const blockedError = (message = BLOCKED_MESSAGE) => ({
  response: { data: { code: ACCOUNTING_LOCK_CODE, message } },
  status: 403
})

describe('useAccountingLock recognises the lock by its machine code (#304, S4)', () => {
  it('detects the accounting lock body', () => {
    expect(ACCOUNTING_LOCK_CODE).toBe('accounting_lock_blocked')
    expect(isAccountingLockBlocked(blockedError())).toBe(true)
  })

  it('never reads a permission 403 as an accounting lock', () => {
    const forbidden = {
      response: {
        data: { message: 'No tienes permisos para acceder a este recurso.' },
        status: 403
      }
    }

    expect(isAccountingLockBlocked(forbidden)).toBe(false)
    expect(isAccountingLockBlocked({ status: 403 })).toBe(false)
    expect(isAccountingLockBlocked(null)).toBe(false)
  })

  it('keeps the API message and leaves generic failures untouched', () => {
    const lock = useAccountingLock()

    expect(lock.detect({ response: { data: { message: 'Error interno' } }, status: 500 })).toBe(
      false
    )
    expect(lock.blocked.value).toBe(false)
    expect(lock.message.value).toBe('')

    expect(lock.detect(blockedError())).toBe(true)
    expect(lock.blocked.value).toBe(true)
    expect(lock.message.value).toBe(BLOCKED_MESSAGE)
  })
})

describe('useAccountingLock owns the override reason (#304, S8)', () => {
  it(`stops a reason shorter than ${ACCOUNTING_LOCK_REASON_MIN_LENGTH} characters locally`, () => {
    expect(ACCOUNTING_LOCK_REASON_MIN_LENGTH).toBe(10)

    const lock = useAccountingLock()
    lock.reason.value = 'corto'

    expect(lock.reasonValid.value).toBe(false)
    expect(lock.authorize()).toBeNull()
    expect(lock.reasonError.value).toContain('10')
  })

  it('builds the retry payload from the trimmed reason', () => {
    const lock = useAccountingLock()
    lock.reason.value = '  Gerencia autorizo la correccion  '

    expect(lock.reasonValid.value).toBe(true)
    expect(lock.authorize()).toEqual({
      lock_override_reason: 'Gerencia autorizo la correccion'
    })
  })

  it('surfaces the server 422 of lock_override_reason on the same notice', () => {
    const lock = useAccountingLock()
    const serverMessage =
      'Autorizar la operación [update] sobre el módulo [quotations] bloqueado hasta el ' +
      '2026-09-30 requiere un motivo de al menos 10 caracteres.'

    const applied = lock.applyValidationErrors({ lock_override_reason: [serverMessage] })

    expect(applied).toBe(true)
    expect(lock.reasonError.value).toBe(serverMessage)
    expect(lock.applyValidationErrors({ notes: ['Otra cosa'] })).toBe(false)
    expect(lock.reasonError.value).toBe(serverMessage)
  })

  it('clears the notice state on reset', () => {
    const lock = useAccountingLock()
    lock.detect(blockedError())
    lock.reason.value = 'Gerencia autorizo la correccion'
    lock.authorize()

    lock.reset()

    expect(lock.blocked.value).toBe(false)
    expect(lock.message.value).toBe('')
    expect(lock.reason.value).toBe('')
    expect(lock.reasonError.value).toBe('')
    expect(lock.reasonLength.value).toBe(0)
  })
})
