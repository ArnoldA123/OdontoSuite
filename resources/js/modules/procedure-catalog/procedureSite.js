/**
 * #293 S1/S2 — the single frontend vocabulary of the procedural applicability.
 *
 * Mirrors the backend nomenclator (`App\Support\ProcedureSiteRule` and
 * `procedure_catalog.site_type`): a procedure applies to a tooth, a tooth
 * surface, a quadrant, an arch, or to the patient as a whole. Every surface
 * that asks the user for a site reads these labels instead of inventing them.
 */

export const SITE_TYPES = ['none', 'tooth', 'surface', 'quadrant', 'arch']

export const SITE_TYPE_LABELS = {
  none: 'Sitio independiente',
  tooth: 'Pieza dental',
  surface: 'Superficie dental',
  quadrant: 'Cuadrante',
  arch: 'Arcada'
}

export const SITE_TYPE_OPTIONS = SITE_TYPES.map(value => ({
  value,
  label: SITE_TYPE_LABELS[value]
}))

export const QUADRANTS = [
  { value: 'superior_derecho', label: 'Cuadrante superior derecho' },
  { value: 'superior_izquierdo', label: 'Cuadrante superior izquierdo' },
  { value: 'inferior_derecho', label: 'Cuadrante inferior derecho' },
  { value: 'inferior_izquierdo', label: 'Cuadrante inferior izquierdo' }
]

export const ARCHES = [
  { value: 'superior', label: 'Arcada superior' },
  { value: 'inferior', label: 'Arcada inferior' }
]

/**
 * The applicability a line's declared site names back.
 *
 * A line written after the rule carries exactly the site its procedure demands,
 * so the site itself identifies the type when the payload does not publish it
 * (the treatment-plan resource does not).
 */
export const siteTypeOf = site => {
  if (!site) return 'none'
  if (site.tooth_surface_id) return 'surface'
  if (site.dental_piece_id) return 'tooth'
  if (QUADRANTS.some(option => option.value === site.site_code)) return 'quadrant'
  if (ARCHES.some(option => option.value === site.site_code)) return 'arch'
  return 'none'
}

const SITE_REQUIREMENTS = {
  tooth: { subject: 'una pieza dental', missing: 'la pieza' },
  surface: { subject: 'una superficie dental', missing: 'la superficie' },
  quadrant: { subject: 'un cuadrante', missing: 'el cuadrante' },
  arch: { subject: 'una arcada', missing: 'la arcada' }
}

/**
 * The column a line must carry to satisfy the applicability of its procedure.
 */
export const requiredSiteField = siteType => {
  if (siteType === 'tooth') return 'dental_piece_id'
  if (siteType === 'surface') return 'tooth_surface_id'
  if (siteType === 'quadrant' || siteType === 'arch') return 'site_code'
  return null
}

/**
 * The Spanish message shown when a site-requiring procedure has no site.
 * Returns an empty string when the applicability demands nothing.
 */
export const siteRequirementMessage = (procedureName, siteType) => {
  const requirement = SITE_REQUIREMENTS[siteType]
  if (!requirement) return ''

  return `El procedimiento «${procedureName || 'seleccionado'}» se realiza sobre ${requirement.subject}: selecciona ${requirement.missing}.`
}

const codeLabel = (list, code) => list.find(option => option.value === code)?.label ?? ''

const surfaceOf = (site, piece) => {
  const surfaceId = site?.tooth_surface_id
  if (!surfaceId) return null

  const fromPiece = (piece?.surfaces ?? []).find(
    surface => String(surface.id) === String(surfaceId)
  )
  if (fromPiece) return fromPiece

  return site?.surface_name
    ? { surface_name: site.surface_name, abbreviation: site.abbreviation ?? '' }
    : null
}

/**
 * A short human label for a line's site: "Pieza 16", "Pieza 16 — Oclusal",
 * "Cuadrante superior derecho", "Arcada superior" or '' when there is none.
 */
export const siteSummary = (site, piece = null) => {
  if (!site) return ''

  const surface = surfaceOf(site, piece)
  const pieceLabel = piece?.fdi_number ? `Pieza ${piece.fdi_number}` : ''

  if (surface) {
    const surfaceLabel = surface.surface_name || surface.abbreviation
    return [pieceLabel, surfaceLabel].filter(Boolean).join(' — ')
  }

  if (site.dental_piece_id) return pieceLabel

  const quadrant = codeLabel(QUADRANTS, site.site_code)
  if (quadrant) return quadrant

  return codeLabel(ARCHES, site.site_code)
}
