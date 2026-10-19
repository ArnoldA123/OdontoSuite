import { describe, it, expect } from 'vitest'
import {
  GATEWAY_TYPE_OPTIONS,
  STATUS_FILTER_OPTIONS,
  STATUS_FORM_OPTIONS,
  getGatewayLabel,
  hasGateway,
  getScopeLabel,
  getScopeVariant,
  getStatusLabel,
  getStatusVariant,
  canDelete,
  getDeleteBlockedReason,
  formatCommission
} from '../../../resources/js/modules/settings/payment-methods/paymentMethodMeta.js'

const statusBadgeVariants = ['success', 'warning', 'error', 'info', 'neutral']

describe('payment methods vocabulary', () => {
  it('maps every gateway type accepted by the API to a human label', () => {
    expect(GATEWAY_TYPE_OPTIONS.map(option => option.value)).toEqual(['manual', 'mercadopago'])
    expect(getGatewayLabel('mercadopago')).toBe('Mercado Pago')
    expect(getGatewayLabel('manual')).toBe('Manual')
    expect(getGatewayLabel(null)).toBe('Manual')
  })

  it('treats only non-manual gateway types as gateways', () => {
    expect(hasGateway('mercadopago')).toBe(true)
    expect(hasGateway('manual')).toBe(false)
    expect(hasGateway(null)).toBe(false)
  })

  it('keeps the status filter contract of the API', () => {
    expect(STATUS_FILTER_OPTIONS.map(option => option.value)).toEqual(['', 'true', 'false'])
  })

  it('keeps form status options UiSelect-safe (string values)', () => {
    STATUS_FORM_OPTIONS.forEach(option => {
      expect(typeof option.value).toBe('string')
    })
    expect(STATUS_FORM_OPTIONS.map(option => option.value)).toEqual(['active', 'inactive'])
  })

  it('labels the system/custom scope', () => {
    expect(getScopeLabel(true)).toBe('Sistema')
    expect(getScopeLabel(false)).toBe('Personalizado')
    expect(getScopeVariant(true)).toBe('warning')
    expect(getScopeVariant(false)).toBe('neutral')
    expect(statusBadgeVariants).toContain(getScopeVariant(true))
    expect(statusBadgeVariants).toContain(getScopeVariant(false))
  })

  it('labels the active/inactive state with valid badge variants', () => {
    expect(getStatusLabel(true)).toBe('Activo')
    expect(getStatusLabel(false)).toBe('Inactivo')
    expect(getStatusVariant(true)).toBe('success')
    expect(getStatusVariant(false)).toBe('neutral')
    expect(statusBadgeVariants).toContain(getStatusVariant(true))
    expect(statusBadgeVariants).toContain(getStatusVariant(false))
  })

  it('blocks deletion for system methods and methods with transactions', () => {
    expect(canDelete({ is_system: false, transactions_count: 0 })).toBe(true)
    expect(canDelete({ is_system: false, transactions_count: '0' })).toBe(true)
    expect(canDelete({ is_system: true, transactions_count: 0 })).toBe(false)
    expect(canDelete({ is_system: false, transactions_count: 2 })).toBe(false)
    expect(canDelete({ is_system: false, transactions_count: '2' })).toBe(false)
    expect(canDelete(null)).toBe(false)
  })

  it('explains why deletion is blocked', () => {
    expect(getDeleteBlockedReason({ is_system: false, transactions_count: 0 })).toBeNull()
    expect(getDeleteBlockedReason({ is_system: true, transactions_count: 0 })).toBe(
      'Los métodos del sistema no se pueden eliminar.'
    )
    expect(getDeleteBlockedReason({ is_system: false, transactions_count: 1 })).toBe(
      'Tiene 1 transacción registrada.'
    )
    expect(getDeleteBlockedReason({ is_system: false, transactions_count: 3 })).toBe(
      'Tiene 3 transacciones registradas.'
    )
  })

  it('formats the commission as a percentage without trailing decimals', () => {
    expect(formatCommission(0)).toBe('0%')
    expect(formatCommission('0.00')).toBe('0%')
    expect(formatCommission(3.5)).toBe('3.50%')
    expect(formatCommission('2.00')).toBe('2%')
    expect(formatCommission(null)).toBe('0%')
    expect(formatCommission('abc')).toBe('0%')
  })
})
