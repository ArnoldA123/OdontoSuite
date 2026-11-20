/**
 * Row-action palette — the only definition of a row action's colour.
 *
 * Five tables (pacientes, profesionales, ambientes, tipos de cita,
 * especialidades) expose the same five actions. When each page carried its own
 * Tailwind classes the palette drifted one page at a time; the four read and
 * edit actions had collapsed into the same green. The token name is part of the
 * contract — `tests/Unit/DesignSystem/RowActionPaletteTest` compares TOKENS, not
 * class strings, so a future edit cannot reintroduce two actions of the same
 * kind that resolve to the same hue.
 *
 * Measured on white (sRGB -> HSL, WCAG contrast, CIEDE2000 against the other
 * actions):
 *
 *   view       systemSteel-600  #3a5a75  hue 207.5  contrast 7.24:1  dE 26.97 vs accent
 *   edit       accent-500       #0f7a5f  hue 164.9  contrast 5.29:1  dE 11.49 vs systemGreen-700
 *   archive    systemYellow-700 #7a5200  hue  40.3  contrast 6.92:1  dE 29.98 vs systemRed-600
 *   reactivate systemGreen-700  #1a6530  hue 137.6  contrast 7.12:1
 *   delete     systemRed-600    #d70015  hue 354.1  contrast 5.38:1
 *
 * Two tokens were rejected on measurement, not taste:
 *
 *   - `systemBlue-600` is a DEPRECATED ALIAS of `accent` (tokens.js): both
 *     resolve to the same ramp, #0a5f49 vs #0f7a5f, 0.4 deg of hue and
 *     dE2000 9.12 apart. "Ver ficha" and "Editar" would read as one green.
 *   - `systemOrange-600` (#c93400) sits 21.4 deg of hue and dE2000 7.17 from
 *     `systemRed-600` (#d70015), under the ~10 threshold where two 14px labels
 *     read as the same family; "Archivar" must not look like "Eliminar".
 *
 * The `!` modifier wins over the primitive's `ghost` colours
 * (`Button.vue` ships `text-theme-secondary hover:text-theme-primary`).
 */

const VIEW_CLASSES = '!text-systemSteel-600 hover:!text-systemSteel-700'
const EDIT_CLASSES = '!text-[color:var(--color-accent)] hover:opacity-80'
const ARCHIVE_CLASSES = '!text-systemYellow-700 hover:opacity-80'
const REACTIVATE_CLASSES = '!text-systemGreen-700 hover:opacity-80'
const DELETE_CLASSES = '!text-systemRed-600 hover:!text-systemRed-700'

export const ROW_ACTION_PALETTE = Object.freeze({
  view: Object.freeze({ token: 'systemSteel-600', classes: VIEW_CLASSES }),
  edit: Object.freeze({ token: 'accent', classes: EDIT_CLASSES }),
  archive: Object.freeze({ token: 'systemYellow-700', classes: ARCHIVE_CLASSES }),
  reactivate: Object.freeze({ token: 'systemGreen-700', classes: REACTIVATE_CLASSES }),
  delete: Object.freeze({ token: 'systemRed-600', classes: DELETE_CLASSES })
})

/**
 * State pills: "Inactivo" is a neutral state, never an error. A retired record
 * is not a failure; painting it red made every read-only row look broken.
 */
export const ROW_STATUS_VARIANT = Object.freeze({
  active: 'success',
  inactive: 'neutral'
})

/** Pill variant for an `is_active` flag: active is success, retired is neutral. */
export const rowStatusVariant = isActive =>
  isActive ? ROW_STATUS_VARIANT.active : ROW_STATUS_VARIANT.inactive

export default ROW_ACTION_PALETTE
