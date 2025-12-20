import { ref, computed, reactive } from 'vue'

export function useValidation() {
  const errors = ref({})
  const touched = ref({})
  const isValidating = ref(false)

  // Validation rules
  const rules = {
    required: (value) => {
      if (typeof value === 'string') {
        return value.trim().length > 0 ? null : 'Este campo es obligatorio'
      }
      return value != null && value !== '' ? null : 'Este campo es obligatorio'
    },

    email: (value) => {
      if (!value) return null
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      return emailRegex.test(value) ? null : 'Ingresa un email válido'
    },

    phone: (value) => {
      if (!value) return null
      const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/
      return phoneRegex.test(value.replace(/\s/g, '')) ? null : 'Ingresa un teléfono válido'
    },

    minLength: (min) => (value) => {
      if (!value) return null
      return value.length >= min ? null : `Mínimo ${min} caracteres`
    },

    maxLength: (max) => (value) => {
      if (!value) return null
      return value.length <= max ? null : `Máximo ${max} caracteres`
    },

    min: (min) => (value) => {
      if (value == null || value === '') return null
      const num = Number(value)
      return !isNaN(num) && num >= min ? null : `El valor mínimo es ${min}`
    },

    max: (max) => (value) => {
      if (value == null || value === '') return null
      const num = Number(value)
      return !isNaN(num) && num <= max ? null : `El valor máximo es ${max}`
    },

    numeric: (value) => {
      if (!value) return null
      return !isNaN(Number(value)) ? null : 'Debe ser un número válido'
    },

    integer: (value) => {
      if (!value) return null
      return Number.isInteger(Number(value)) ? null : 'Debe ser un número entero'
    },

    positive: (value) => {
      if (value == null || value === '') return null
      const num = Number(value)
      return !isNaN(num) && num > 0 ? null : 'Debe ser un número positivo'
    },

    date: (value) => {
      if (!value) return null
      const date = new Date(value)
      return !isNaN(date.getTime()) ? null : 'Ingresa una fecha válida'
    },

    futureDate: (value) => {
      if (!value) return null
      const date = new Date(value)
      const today = new Date()
      today.setHours(0, 0, 0, 0)
      return date >= today ? null : 'La fecha debe ser futura'
    },

    pastDate: (value) => {
      if (!value) return null
      const date = new Date(value)
      const today = new Date()
      today.setHours(23, 59, 59, 999)
      return date <= today ? null : 'La fecha debe ser pasada'
    },

    time: (value) => {
      if (!value) return null
      const timeRegex = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/
      return timeRegex.test(value) ? null : 'Ingresa una hora válida (HH:MM)'
    },

    url: (value) => {
      if (!value) return null
      try {
        new URL(value)
        return null
      } catch {
        return 'Ingresa una URL válida'
      }
    },

    regex: (pattern, message) => (value) => {
      if (!value) return null
      return pattern.test(value) ? null : (message || 'Formato inválido')
    },

    custom: (validator, message) => (value) => {
      try {
        const result = validator(value)
        return result === true ? null : (result || message || 'Valor inválido')
      } catch (error) {
        return message || 'Error de validación'
      }
    }
  }

  // Validate a single field
  const validateField = (fieldName, value, fieldRules) => {
    if (!fieldRules || fieldRules.length === 0) {
      return null
    }

    for (const rule of fieldRules) {
      let ruleFunction
      let ruleMessage

      if (typeof rule === 'string') {
        ruleFunction = rules[rule]
        ruleMessage = null
      } else if (typeof rule === 'function') {
        ruleFunction = rule
        ruleMessage = null
      } else if (Array.isArray(rule)) {
        ruleFunction = rule[0]
        ruleMessage = rule[1]
      } else if (rule.type) {
        ruleFunction = rules[rule.type]
        ruleMessage = rule.message
      }

      if (ruleFunction) {
        const error = ruleFunction(value)
        if (error) {
          return ruleMessage || error
        }
      }
    }

    return null
  }

  // Validate all fields
  const validate = (formData, validationRules) => {
    isValidating.value = true
    const newErrors = {}

    for (const [fieldName, fieldRules] of Object.entries(validationRules)) {
      const value = formData[fieldName]
      const error = validateField(fieldName, value, fieldRules)

      if (error) {
        newErrors[fieldName] = error
      }
    }

    errors.value = newErrors
    isValidating.value = false

    return Object.keys(newErrors).length === 0
  }

  // Validate a single field and update errors
  const validateSingle = (fieldName, value, fieldRules) => {
    const error = validateField(fieldName, value, fieldRules)

    if (error) {
      errors.value[fieldName] = error
    } else {
      delete errors.value[fieldName]
    }

    return !error
  }

  // Mark field as touched
  const touch = (fieldName) => {
    touched.value[fieldName] = true
  }

  // Mark field as untouched
  const untouch = (fieldName) => {
    delete touched.value[fieldName]
  }

  // Clear all errors
  const clearErrors = () => {
    errors.value = {}
  }

  // Clear specific field error
  const clearFieldError = (fieldName) => {
    delete errors.value[fieldName]
  }

  // Check if field has error
  const hasError = (fieldName) => {
    return !!errors.value[fieldName]
  }

  // Get field error
  const getError = (fieldName) => {
    return errors.value[fieldName] || null
  }

  // Check if field is touched
  const isTouched = (fieldName) => {
    return !!touched.value[fieldName]
  }

  // Check if field should show error (touched and has error)
  const shouldShowError = (fieldName) => {
    return isTouched(fieldName) && hasError(fieldName)
  }

  // Check if form is valid
  const isValid = computed(() => {
    return Object.keys(errors.value).length === 0
  })

  // Check if form has been touched
  const isTouchedForm = computed(() => {
    return Object.keys(touched.value).length > 0
  })

  // Get all errors as array
  const allErrors = computed(() => {
    return Object.values(errors.value)
  })

  // Create validation schema
  const createSchema = (schema) => {
    return reactive(schema)
  }

  // Common validation schemas
  const schemas = {
    patient: {
      first_name: ['required', ['minLength', 2], ['maxLength', 50]],
      last_name: ['required', ['minLength', 2], ['maxLength', 50]],
      email: ['email'],
      phone: ['phone'],
      birth_date: ['date', 'pastDate'],
      gender: ['required'],
      address: [['maxLength', 255]],
      emergency_contact_name: [['maxLength', 100]],
      emergency_contact_phone: ['phone']
    },

    appointment: {
      patient_id: ['required'],
      user_id: ['required'],
      dental_chair_id: ['required'],
      appointment_type_id: ['required'],
      scheduled_at: ['required', 'date', 'futureDate'],
      duration_minutes: ['required', 'integer', 'positive', ['min', 15], ['max', 480]],
      notes: [['maxLength', 1000]]
    },

    user: {
      name: ['required', ['minLength', 2], ['maxLength', 100]],
      email: ['required', 'email'],
      username: ['required', ['minLength', 3], ['maxLength', 50]],
      role: ['required'],
      specialty: [['maxLength', 100]],
      phone: ['phone']
    },

    appointmentType: {
      name: ['required', ['minLength', 2], ['maxLength', 100]],
      description: [['maxLength', 500]],
      default_duration_minutes: ['required', 'integer', 'positive', ['min', 15], ['max', 480]],
      price: ['numeric', 'positive'],
      color: ['required', ['regex', /^#[0-9A-F]{6}$/i, 'Debe ser un color hexadecimal válido']]
    },

    dentalChair: {
      name: ['required', ['minLength', 2], ['maxLength', 100]],
      description: [['maxLength', 500]],
      equipment: [['maxLength', 1000]],
      status: ['required']
    }
  }

  return {
    // State
    errors,
    touched,
    isValidating,

    // Computed
    isValid,
    isTouchedForm,
    allErrors,

    // Methods
    validate,
    validateSingle,
    touch,
    untouch,
    clearErrors,
    clearFieldError,
    hasError,
    getError,
    isTouched,
    shouldShowError,
    createSchema,

    // Rules and schemas
    rules,
    schemas
  }
}
