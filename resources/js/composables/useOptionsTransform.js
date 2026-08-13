/**
 * Composable para transformar datos de API a formato de opciones para componentes Select
 */

/**
 * Transforma un array de objetos de API a formato {value, label} para UiSelect
 * @param {Array} items - Array de objetos de API
 * @param {Object} config - Configuración de transformación
 * @param {string} config.valueKey - Clave del objeto que será el valor (default: 'id')
 * @param {string|Function} config.labelKey - Clave del objeto que será el label o función para generar el label
 * @param {string} config.descriptionKey - Clave opcional para descripción adicional
 * @returns {Array} Array de opciones en formato {value, label, description?}
 */
export function transformToOptions(items, config = {}) {
  if (!Array.isArray(items)) {
    return []
  }

  const { valueKey = 'id', labelKey = 'name', descriptionKey = null } = config

  return items.map(item => {
    const value = item[valueKey]

    // Si labelKey es una función, usarla para generar el label
    const label = typeof labelKey === 'function' ? labelKey(item) : item[labelKey] || ''

    const option = {
      value,
      label: String(label)
    }

    // Agregar descripción si se especifica
    if (descriptionKey && item[descriptionKey]) {
      option.description = String(item[descriptionKey])
    }

    return option
  })
}

/**
 * Transforma profesionales de API a opciones para select
 */
export function transformProfessionals(professionals) {
  return transformToOptions(professionals, {
    valueKey: 'id',
    labelKey: item => {
      const name = item.name || ''
      const specialty = item.specialty || item.role || ''
      return specialty ? `${name} - ${specialty}` : name
    }
  })
}

/**
 * Transforma pacientes de API a opciones para select
 */
export function transformPatients(patients) {
  return transformToOptions(patients, {
    valueKey: 'id',
    labelKey: item => {
      const firstName = item.first_name || ''
      const lastName = item.last_name || ''
      return `${firstName} ${lastName}`.trim()
    },
    descriptionKey: 'email'
  })
}

/**
 * Transforma sillas dentales/ambientes de API a opciones para select
 */
export function transformDentalChairs(chairs) {
  return transformToOptions(chairs, {
    valueKey: 'id',
    labelKey: item => {
      const name = item.name || ''
      const code = item.code || ''
      return code ? `${name} (${code})` : name
    },
    descriptionKey: 'description'
  })
}

/**
 * Transforma tipos de cita de API a opciones para select
 */
export function transformAppointmentTypes(types) {
  return transformToOptions(types, {
    valueKey: 'id',
    labelKey: item => {
      const name = item.name || ''
      const duration = item.default_duration_minutes || ''
      return duration ? `${name} (${duration} min)` : name
    }
  })
}

/**
 * Transforma métodos de pago de API a opciones para select
 */
export function transformPaymentMethods(methods) {
  return transformToOptions(methods, {
    valueKey: 'id',
    labelKey: 'name'
  })
}

/**
 * Transforma sucursales de API a opciones para select
 */
export function transformBranches(branches) {
  return transformToOptions(branches, {
    valueKey: 'id',
    labelKey: 'name',
    descriptionKey: 'address'
  })
}

/**
 * Transforma roles de API a opciones para select
 */
export function transformRoles(roles) {
  return transformToOptions(roles, {
    valueKey: 'id',
    labelKey: 'name'
  })
}

/**
 * Hook composable que exporta todas las funciones de transformación
 */
export function useOptionsTransform() {
  return {
    transformToOptions,
    transformProfessionals,
    transformPatients,
    transformDentalChairs,
    transformAppointmentTypes,
    transformPaymentMethods,
    transformBranches,
    transformRoles
  }
}
