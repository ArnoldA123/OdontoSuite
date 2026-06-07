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
      Object.entries(payload).forEach(([key, value]) => {
        if (value === null || value === undefined) return
        if (key === 'attachments' && Array.isArray(value)) {
          value.forEach((att, idx) => {
            if (!att || !att.file) return
            form.append(`attachments[${idx}][file]`, att.file)
            form.append(`attachments[${idx}][category]`, att.category || 'general')
            if (att.description) form.append(`attachments[${idx}][description]`, att.description)
            if (att.is_private) form.append(`attachments[${idx}][is_private]`, '1')
          })
        } else if (typeof value === 'object') {
          form.append(key, JSON.stringify(value))
        } else {
          form.append(key, String(value))
        }
      })

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
        const errMsg = data?.error?.message || data?.message || 'Error al completar la consulta'
        lastError.value = errMsg
        toast.error(errMsg)
        throw { response: { data }, status: response.status }
      }

      toast.success('Consulta completada')
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
