<template>
  <form class="form-responsive" @submit.prevent="handleSubmit">
    <slot
      :errors="errors"
      :touched="touched"
      :is-validating="isValidating"
      :validate-field="validateField"
      :touch-field="touchField"
      :has-error="hasError"
      :get-error="getError"
      :should-show-error="shouldShowError"
    />
  </form>
</template>

<script>
import { useValidation } from '../composables/useValidation.js'

export default {
  name: 'ValidatedForm',
  props: {
    validationRules: {
      type: Object,
      required: true
    },
    initialData: {
      type: Object,
      default: () => ({})
    }
  },
  emits: ['submit', 'valid', 'invalid'],
  setup(props, { emit }) {
    const {
      errors,
      touched,
      isValidating,
      isValid,
      validate,
      validateSingle,
      touch,
      hasError,
      getError,
      shouldShowError,
      clearErrors
    } = useValidation()

    const formData = ref({ ...props.initialData })

    const validateField = (fieldName, value) => {
      const fieldRules = props.validationRules[fieldName]
      if (fieldRules) {
        return validateSingle(fieldName, value, fieldRules)
      }
      return true
    }

    const touchField = fieldName => {
      touch(fieldName)
    }

    const handleSubmit = () => {
      // Touch all fields
      Object.keys(props.validationRules).forEach(fieldName => {
        touch(fieldName)
      })

      // Validate form
      const isFormValid = validate(formData.value, props.validationRules)

      if (isFormValid) {
        emit('valid', formData.value)
        emit('submit', formData.value)
      } else {
        emit('invalid', errors.value)
      }
    }

    const updateFormData = newData => {
      formData.value = { ...formData.value, ...newData }
    }

    const resetForm = () => {
      formData.value = { ...props.initialData }
      clearErrors()
    }

    // Watch for changes in initialData
    watch(
      () => props.initialData,
      newData => {
        formData.value = { ...newData }
      },
      { deep: true }
    )

    return {
      formData,
      errors,
      touched,
      isValidating,
      isValid,
      validateField,
      touchField,
      hasError,
      getError,
      shouldShowError,
      handleSubmit,
      updateFormData,
      resetForm
    }
  }
}
</script>
