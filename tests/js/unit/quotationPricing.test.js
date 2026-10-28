import { describe, it, expect } from 'vitest'
import {
  FALLBACK_TAX_PERCENTAGE,
  PRICE_SOURCE_LABELS,
  breakDown,
  effectiveTaxRate,
  priceSourceLabel
} from '../../../resources/js/modules/quotations/quotationPricing'

describe('quotationPricing', () => {
  describe('effectiveTaxRate', () => {
    it('keeps the rate the caller declared', () => {
      expect(effectiveTaxRate(18, 10)).toBe(18)
    })

    it('uses the policy rate when nothing was declared', () => {
      expect(effectiveTaxRate(null, 18)).toBe(18)
      expect(effectiveTaxRate(undefined, 18)).toBe(18)
      expect(effectiveTaxRate('', 18)).toBe(18)
    })

    it('reads a declared zero as the exempt mode, not as an empty field', () => {
      expect(effectiveTaxRate(0, 18)).toBe(0)
      expect(effectiveTaxRate('0', 18)).toBe(0)
    })

    it('falls back to the policy when the declared value is not a number', () => {
      expect(effectiveTaxRate('abc', 18)).toBe(18)
    })

    it('falls back to the mirrored billing default when no policy arrived', () => {
      expect(FALLBACK_TAX_PERCENTAGE).toBe(18)
      expect(effectiveTaxRate(null, null)).toBe(18)
    })
  })

  describe('breakDown', () => {
    it('decomposes 100 at 18 % without touching the total', () => {
      expect(breakDown(100, 0, 18)).toEqual({
        subtotal: 84.75,
        taxAmount: 15.25,
        totalAmount: 100
      })
    })

    it('decomposes the 140 of the reported issue', () => {
      expect(breakDown(140, 0, 18)).toEqual({
        subtotal: 118.64,
        taxAmount: 21.36,
        totalAmount: 140
      })
    })

    it('is exempt at a zero rate', () => {
      expect(breakDown(100, 0, 0)).toEqual({
        subtotal: 100,
        taxAmount: 0,
        totalAmount: 100
      })
    })

    it('applies the discount before the rate', () => {
      // 140 gross minus a 40 discount is the 100 the issue decomposes.
      expect(breakDown(140, 40, 18)).toEqual({
        subtotal: 84.75,
        taxAmount: 15.25,
        totalAmount: 100
      })
    })

    it('never stacks the rate on top of the total', () => {
      const { subtotal, taxAmount, totalAmount } = breakDown(118, 0, 18)

      expect(totalAmount).toBe(118)
      expect(subtotal + taxAmount).toBe(totalAmount)
    })

    it('keeps cents when the discounted net is not round', () => {
      expect(breakDown(100, 0.01, 18)).toEqual({
        subtotal: 84.74,
        taxAmount: 15.25,
        totalAmount: 99.99
      })
    })
  })

  describe('priceSourceLabel', () => {
    it('names the four origins of the tariff cascade', () => {
      expect(PRICE_SOURCE_LABELS).toEqual({
        catalog: 'Catálogo',
        branch: 'Sede',
        payer: 'Convenio',
        payer_branch: 'Convenio y sede'
      })

      expect(priceSourceLabel('catalog')).toBe('Catálogo')
      expect(priceSourceLabel('branch')).toBe('Sede')
      expect(priceSourceLabel('payer')).toBe('Convenio')
      expect(priceSourceLabel('payer_branch')).toBe('Convenio y sede')
    })

    it('has no label for a line without tariff evidence', () => {
      expect(priceSourceLabel(null)).toBe('')
      expect(priceSourceLabel(undefined)).toBe('')
      expect(priceSourceLabel('inventado')).toBe('')
    })
  })
})
