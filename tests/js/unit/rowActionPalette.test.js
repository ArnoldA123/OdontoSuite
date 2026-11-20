import { describe, it, expect } from 'vitest'
import {
  ROW_ACTION_PALETTE,
  ROW_STATUS_VARIANT,
  rowStatusVariant
} from '../../../resources/js/design-system/rowActionPalette.js'

describe('rowActionPalette', () => {
  it('declares the five row actions with distinct colour tokens', () => {
    const tokens = Object.values(ROW_ACTION_PALETTE).map(action => action.token)

    expect(Object.keys(ROW_ACTION_PALETTE)).toEqual([
      'view',
      'edit',
      'archive',
      'reactivate',
      'delete'
    ])
    expect(new Set(tokens).size).toBe(tokens.length)
  })

  it('keeps the read action off the brand accent and the deprecated blue alias', () => {
    expect(ROW_ACTION_PALETTE.view.token).toBe('systemSteel-600')
    expect(ROW_ACTION_PALETTE.view.token).not.toBe(ROW_ACTION_PALETTE.edit.token)
    expect(ROW_ACTION_PALETTE.view.classes).not.toMatch(/systemBlue|color-accent/)
    expect(ROW_ACTION_PALETTE.edit.classes).toContain('--color-accent')
  })

  it('paints destruction red and reversible state changes amber', () => {
    expect(ROW_ACTION_PALETTE.delete.token).toMatch(/^systemRed-/)
    expect(ROW_ACTION_PALETTE.archive.token).toMatch(/^system(Orange|Yellow)-/)
    expect(ROW_ACTION_PALETTE.reactivate.token).toMatch(/^systemGreen-/)
  })

  it('wins over the ghost primitive with the important modifier', () => {
    for (const action of Object.values(ROW_ACTION_PALETTE)) {
      expect(action.classes).toMatch(/(^|\s)!text-/)
    }
  })

  it('maps a retired record to the neutral pill, never to an error', () => {
    expect(rowStatusVariant(true)).toBe(ROW_STATUS_VARIANT.active)
    expect(rowStatusVariant(false)).toBe('neutral')
    expect(Object.values(ROW_STATUS_VARIANT)).not.toContain('error')
  })
})
