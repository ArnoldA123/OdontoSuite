import { ref } from 'vue'
import { useApi } from './useApi'
import { useToast } from './useToast'

// F4 (#310) — the reception desk of continuity. `GET /api/continuity/queue`
// already scopes the rows to the effective branch, so this composable only
// buckets, writes, and refreshes. State lives at module scope because the desk
// is one screen; unlike the professional's desk there is no realtime channel to
// subscribe to, so the queue refreshes on mount and after every write.
const emptyQueue = () => ({
  recalls: [],
  requests: [],
  counts: { recalls: 0, requests: 0, total: 0 }
})

const queue = ref(emptyQueue())
const metrics = ref(null)
const loading = ref(false)
const error = ref(null)
const acting = ref(null)

const asList = value => (Array.isArray(value) ? value : [])

// The write doors answer 422 with `{ message, errors }`, so the envelope's
// `message` is the string the desk surfaces before its own fallback.
const messageFor = (thrown, fallback) =>
  thrown?.response?.data?.message || thrown?.message || fallback

export function useContinuity() {
  /**
   * Load the queue and the recall metric together. A failed refresh keeps the
   * previous queue and metric mounted and only records `error`, so reception
   * keeps working from the rows it already has.
   */
  const load = async () => {
    const { get } = useApi()
    loading.value = true
    try {
      const [queueResponse, metricsResponse] = await Promise.all([
        get('/api/continuity/queue'),
        get('/api/patient-recalls/metrics')
      ])

      const data = queueResponse?.data || {}
      const recalls = asList(data.recalls)
      const requests = asList(data.requests)
      const counts = data.counts || {}

      queue.value = {
        recalls,
        requests,
        counts: {
          recalls: counts.recalls ?? recalls.length,
          requests: counts.requests ?? requests.length,
          total: counts.total ?? recalls.length + requests.length
        }
      }
      metrics.value = metricsResponse?.data ?? null
      error.value = null
    } catch (thrown) {
      error.value = messageFor(thrown, 'No se pudo cargar la cola de continuidad')
    } finally {
      loading.value = false
    }
  }

  /**
   * Materialise a queue row as an appointment. `kind` selects the door: the
   * request carries the conduct, the recall carries the overdue control. The
   * server validates professional, chair and conflicts, so a 422 is rethrown
   * for the caller to map onto the form's fields.
   */
  const schedule = async (kind, row, payload) => {
    if (!row?.id) return null
    const { post } = useApi()
    const toast = useToast()
    const isRecall = kind === 'recall'
    const path = isRecall
      ? `/api/patient-recalls/${row.id}/schedule`
      : `/api/continuity-requests/${row.id}/schedule`

    acting.value = row.id
    try {
      const response = await post(path, payload ?? {})
      toast.success(isRecall ? 'Cita de control agendada.' : 'Cita de continuidad agendada.')
      await load()
      return response?.data ?? null
    } catch (thrown) {
      toast.error(messageFor(thrown, 'No se pudo agendar la cita de continuidad'))
      throw thrown
    } finally {
      acting.value = null
    }
  }

  // Only a continuity request persists a dismissal reason (`resolution_notes`);
  // a recall leaves the queue by being scheduled.
  const dismissRequest = async (row, reason) => {
    if (!row?.id) return null
    const { post } = useApi()
    const toast = useToast()

    acting.value = row.id
    try {
      const response = await post(`/api/continuity-requests/${row.id}/dismiss`, { reason })
      toast.success('Solicitud descartada.')
      await load()
      return response?.data ?? null
    } catch (thrown) {
      toast.error(messageFor(thrown, 'No se pudo descartar la solicitud'))
      throw thrown
    } finally {
      acting.value = null
    }
  }

  return {
    queue,
    metrics,
    loading,
    error,
    acting,
    load,
    schedule,
    dismissRequest
  }
}
