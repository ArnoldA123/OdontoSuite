import { ref } from 'vue'
import { useApi } from './useApi'
import { useEcho } from './useEcho'
import { usePermissions } from './usePermissions'
import { useToast } from './useToast'

// F1 (#281) — the professional's desk. `GET /api/my-day` already scopes the
// queue to the authenticated professional, so this composable never filters by
// user: it only buckets, acts, and refreshes. State lives at module scope
// because the desk and its realtime subscription are one singleton.
const emptyQueue = () => ({ waiting: [], attending: [], upcoming: [], ready_lab_cases: [] })
const emptyCounts = () => ({ waiting: 0, attending: 0, upcoming: 0, ready_lab_cases: 0 })

const queue = ref(emptyQueue())
const counts = ref(emptyCounts())
// #387 - the desk's first frame is the skeleton. Starting `false` painted the
// three sections with empty buckets before the queue resolved.
const loading = ref(true)
// #387 - separates "not answered yet" from "answered with empties": the
// counters read `—` until the first response lands, then the real counts.
const hasLoaded = ref(false)
const error = ref(null)
const acting = ref(null)

let channel = null
let appointmentChannel = null
let subscribedUserId = null

// T2 — la cola de Mi día se deriva del estado de la cita, así que la misma
// difusión que mueve la agenda la vuelve a leer. El payload es operativo
// (`id`, `user_id`, `status`, `scheduled_at`, `updated_at`): no trae paciente y
// esta pantalla no lo pide. Refrescar no toca: el aviso de la cita lo dio su
// escritorio y repetirlo aquí sería el doble toast que esta ronda retira.
const APPOINTMENT_EVENTS = [
  '.appointment.created',
  '.appointment.updated',
  '.appointment.checked_in',
  '.appointment.completed',
  '.appointment.deleted'
]

const asList = value => (Array.isArray(value) ? value : [])

/**
 * #389 - only the API envelope may be echoed: the domain `error.message`
 * first (409 `arrival_not_allowed` / `attention_not_allowed`), then the
 * generic message, then the caller's fallback. A transport failure
 * (`TypeError('Failed to fetch')`) carries no `response` and its `message` is
 * the browser's English text, so it always falls back to the Spanish copy.
 */
const messageFor = (thrown, fallback) =>
  thrown?.response?.data?.error?.message || thrown?.response?.data?.message || fallback

export function useMyDay() {
  /**
   * Replace the queue buckets and their counts. A failed refresh keeps the
   * previous queue mounted and only records `error`, so a dropped connection
   * never blanks the desk the professional is working from.
   */
  const load = async () => {
    const { get } = useApi()
    const { can } = usePermissions()

    // El endpoint exige `view.my_day`: sin él responde 403 y pintarlo como
    // error convierte un escritorio ajeno en una avería. La página decide qué
    // mostrar; el composable, simplemente, no pide la cola.
    if (can.viewMyDay?.value !== true) {
      loading.value = false
      hasLoaded.value = false
      error.value = null
      return
    }

    loading.value = true
    try {
      const response = await get('/api/my-day')
      const next = {
        waiting: asList(response?.data?.waiting),
        attending: asList(response?.data?.attending),
        upcoming: asList(response?.data?.upcoming),
        ready_lab_cases: asList(response?.data?.ready_lab_cases)
      }
      const meta = response?.meta?.counts || {}
      queue.value = next
      counts.value = {
        waiting: meta.waiting ?? next.waiting.length,
        attending: meta.attending ?? next.attending.length,
        upcoming: meta.upcoming ?? next.upcoming.length,
        ready_lab_cases: meta.ready_lab_cases ?? next.ready_lab_cases.length
      }
      hasLoaded.value = true
      error.value = null
    } catch (thrown) {
      // A failed first load leaves the counters as "unknown" (`—`) instead of
      // painting a zero over a queue the desk never received.
      error.value = messageFor(thrown, 'No se pudo cargar tu día')
    } finally {
      loading.value = false
    }
  }

  const custodyAction = async (appointment, path, successMessage, fallbackMessage) => {
    if (!appointment?.id) return null
    const { post } = useApi()
    const toast = useToast()
    acting.value = appointment.id
    try {
      const response = await post(`/api/appointments/${appointment.id}${path}`, {})
      toast.success(successMessage)
      await load()
      return response?.data ?? null
    } catch (thrown) {
      toast.error(messageFor(thrown, fallbackMessage))
      throw thrown
    } finally {
      acting.value = null
    }
  }

  // S4 fallback: the professional marks the arrival when reception is not at
  // the desk. The backend records the door from the actor's role.
  const markArrival = appointment =>
    custodyAction(
      appointment,
      '/check-in',
      'Llegada registrada.',
      'No se pudo registrar la llegada'
    )

  // S5: attention starts from the queue; no calendar search, no status edit.
  const startAttention = appointment =>
    custodyAction(
      appointment,
      '/start-attention',
      'Atención iniciada.',
      'No se pudo iniciar la atención'
    )

  const handlePatientReady = event => {
    const toast = useToast()
    const patientName = event?.patient?.full_name
    toast.info(patientName ? `Paciente listo: ${patientName}` : 'Paciente listo')
    load()
  }

  // F5 (#285): the prosthetic case came back from the workshop. The API
  // publishes the ready cases of this professional under `ready_lab_cases`, so
  // announcing it and refreshing is enough for the desk to show the work.
  const handleLabCaseReady = event => {
    const toast = useToast()
    const work = event?.case?.work
    toast.info(work ? `Caso de laboratorio listo: ${work}` : 'Caso de laboratorio listo')
    load()
  }

  // T2: una cita en movimiento re-lee la cola con su carga de siempre.
  const refreshQueue = () => load()

  // Echo registers private channels with the `private-` prefix on the wire, so
  // leaving `user.{id}` is inert: the teardown must name the wire channel.
  const unsubscribe = userId => {
    const target = userId ?? subscribedUserId
    try {
      const { echo } = useEcho()
      if (echo && target) echo.leave(`private-user.${target}`)
      if (echo) echo.leave('private-appointments')
    } catch (thrown) {
      // teardown is best-effort
    }
    channel = null
    appointmentChannel = null
    subscribedUserId = null
  }

  /**
   * `PatientReady` and `LabCaseReady` travel on the professional's private
   * channel `user.{id}`. Reverb is often down in local dev, so an unavailable
   * transport leaves the desk usable (manual refresh) instead of throwing
   * during mount.
   *
   * T2 adds the `appointments` channel: the desk is a view of the agenda, so a
   * cita que se crea, se mueve, llega o se cierra en otra mesa también se ve
   * aquí sin recargar.
   */
  const subscribe = userId => {
    if (!userId || (subscribedUserId === userId && channel)) return
    if (subscribedUserId && subscribedUserId !== userId) unsubscribe()
    try {
      const { privateChannel } = useEcho()
      const nextChannel = privateChannel(`user.${userId}`)
      if (nextChannel) {
        channel = nextChannel
        channel.listen('.paciente_listo', handlePatientReady)
        channel.listen('.caso_laboratorio_listo', handleLabCaseReady)
        subscribedUserId = userId
      }

      const nextAppointments = privateChannel('appointments')
      if (nextAppointments) {
        appointmentChannel = nextAppointments
        APPOINTMENT_EVENTS.forEach(eventName => {
          appointmentChannel.listen(eventName, refreshQueue)
        })
      }
    } catch (thrown) {
      channel = null
      appointmentChannel = null
      subscribedUserId = null
    }
  }

  return {
    queue,
    counts,
    loading,
    hasLoaded,
    error,
    acting,
    load,
    markArrival,
    startAttention,
    subscribe,
    unsubscribe
  }
}
