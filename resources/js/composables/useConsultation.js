import { ref, computed } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

const wizardOpen = ref(false)
const contextLoading = ref(false)
const submitting = ref(false)
const context = ref(null)
const currentAppointmentId = ref(null)
const lastError = ref(null)

export function useConsultation() {
  const { get, post } = useApi()
  const toast = useToast()

  const isOpen = computed(() => wizardOpen.value)

  const openForAppointment = async (appointment) => {
    if (!appointment?.id) return
    currentAppointmentId.value = appointment.id
    context.value = null
    lastError.value = null
    wizardOpen.value = true
    await loadContext(appointment.id)
  }

  const close = () => {
    wizardOpen.value = false
    context.value = null
    currentAppointmentId.value = null
    lastError.value = null
  }

  const loadContext = async (appointmentId) => {
    contextLoading.value = true
    try {
      const response = await get(`/api/appointments/${appointmentId}/consultation-context`)
      context.value = response?.data ?? null
    } catch (error) {
      console.error('Error loading consultation context:', error)
      lastError.value = error?.response?.data?.error?.message || error?.response?.data?.message || 'No se pudo cargar el contexto'
      toast.error(lastError.value)
    } finally {
      contextLoading.value = false
    }
  }

  const checkIn = async (appointment) => {
    if (!appointment?.id) return null
    try {
      const response = await post(`/api/appointments/${appointment.id}/check-in`, {})
      toast.success('Paciente en consulta')
      return response?.data ?? null
    } catch (error) {
      const err = error?.response?.data?.error
      const msg = err?.message || error?.response?.data?.message || 'No se pudo iniciar la consulta'
      toast.error(msg)
      throw error
    }
  }

  const submit = async (payload) => {
    if (!currentAppointmentId.value) return null
    submitting.value = true
    lastError.value = null
    try {
      const form = new FormData()

      const appendField = (prefix, value) => {
        if (value === null || value === undefined) return
        if (typeof value === 'string' && value === '') return
        if (Array.isArray(value)) {
          value.forEach((item, idx) => appendField(`${prefix}[${idx}]`, item))
          return
        }
        if (value instanceof File || value instanceof Blob) {
          form.append(prefix, value)
          return
        }
        if (typeof value === 'object') {
          Object.entries(value).forEach(([k, v]) => {
            if (v === null || v === undefined) return
            if (typeof v === 'string' && v === '') return
            appendField(`${prefix}[${k}]`, v)
          })
          return
        }
        if (typeof value === 'boolean') {
          form.append(prefix, value ? '1' : '0')
          return
        }
        form.append(prefix, String(value))
      }

      const sanitizedPayload = { ...payload }
      if (
        sanitizedPayload.next_appointment &&
        (!sanitizedPayload.next_appointment.scheduled_at ||
          sanitizedPayload.next_appointment.scheduled_at === '')
      ) {
        delete sanitizedPayload.next_appointment
      }

      Object.entries(sanitizedPayload).forEach(([key, value]) => appendField(key, value))

      const token = localStorage.getItem('auth_token')
      const baseURL = import.meta.env.VITE_APP_URL || window.location.origin
      const response = await fetch(`${baseURL}/api/appointments/${currentAppointmentId.value}/complete`, {
        method: 'POST',
        headers: {
          'Authorization': token ? `Bearer ${token}` : '',
          'Accept': 'application/json',
        },
        body: form,
      })

      const data = await response.json().catch(() => ({}))

      if (!response.ok) {
        const fieldErrors = data?.errors
        let errMsg = data?.error?.message || data?.message || 'Error al completar la consulta'

        if (fieldErrors && typeof fieldErrors === 'object') {
          const fields = Object.entries(fieldErrors)
            .map(([field, msgs]) => `${field}: ${Array.isArray(msgs) ? msgs.join(', ') : msgs}`)
            .join(' | ')
          errMsg = fields || errMsg
        }

        console.error('Consultation validation failed', {
          status: response.status,
          message: data?.message,
          errors: fieldErrors,
          fullResponse: data,
        })

        lastError.value = errMsg
        toast.error(errMsg, 8000)
        throw { response: { data }, status: response.status }
      }

      toast.success('Consulta completada')
      if (data?.meta?.quotation_generated && data?.quotation) {
        toast.success(`Cotización ${data.quotation.quotation_number} generada automáticamente`)
      }
      return data
    } catch (error) {
      console.error('Error completing consultation:', error)
      throw error
    } finally {
      submitting.value = false
    }
  }

  return {
    isOpen,
    context,
    contextLoading,
    submitting,
    lastError,
    currentAppointmentId,
    openForAppointment,
    close,
    loadContext,
    checkIn,
    submit,
  }
}
