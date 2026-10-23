import { ref } from 'vue'
import { useApi } from './useApi'
import { useEcho } from './useEcho'
import { useToast } from './useToast'

// F5 (#316) — the prosthetic laboratory desk. The API scopes the list to the
// actor's branch, so this composable only builds the query, pages it and moves
// the phase through its single door. State lives at module scope because the
// desk and its realtime subscription are one singleton.
const emptyMeta = () => ({ current_page: 1, last_page: 1, per_page: 15, total: 0 })
const emptyFilters = () => ({ status: '', technician_id: '', overdue: false, page: 1 })

const cases = ref([])
const meta = ref(emptyMeta())
const filters = ref(emptyFilters())
const loading = ref(false)
const error = ref(null)
const acting = ref(null)

let channel = null

const asList = value => (Array.isArray(value) ? value : [])

/**
 * The 422 of the phase machine carries the service message under `phase`, and
 * that is the sentence the user needs: it names the current phase and the one
 * that was asked for.
 */
export const labCaseMessageFor = (thrown, fallback) =>
  thrown?.response?.data?.errors?.phase?.[0] ||
  thrown?.response?.data?.error?.message ||
  thrown?.response?.data?.message ||
  thrown?.message ||
  fallback

const buildQuery = current => {
  const params = new URLSearchParams()

  if (current.status) params.set('status', current.status)
  if (current.technician_id) params.set('technician_id', String(current.technician_id))
  if (current.professional_id) params.set('professional_id', String(current.professional_id))
  if (current.patient_id) params.set('patient_id', String(current.patient_id))
  if (current.overdue) params.set('overdue', '1')
  if (current.per_page) params.set('per_page', String(current.per_page))
  params.set('page', String(current.page || 1))

  return params.toString()
}

export function useLabCases() {
  /**
   * Replace the page and its meta. A failed refresh keeps the previous list
   * mounted and only records `error`: the desk must not blank itself because
   * one request dropped.
   */
  const load = async () => {
    const { get } = useApi()
    loading.value = true
    try {
      const response = await get(`/api/lab-cases?${buildQuery(filters.value)}`)
      cases.value = asList(response?.data)
      meta.value = { ...emptyMeta(), ...(response?.meta || {}) }
      error.value = null
    } catch (thrown) {
      error.value = labCaseMessageFor(thrown, 'No se pudieron cargar los casos de laboratorio')
    } finally {
      loading.value = false
    }
  }

  // Any filter change starts a new result set, so the page resets with it.
  const setFilter = patch => {
    filters.value = { ...filters.value, ...patch, page: 1 }
    return load()
  }

  const goToPage = page => {
    filters.value = { ...filters.value, page }
    return load()
  }

  const loadOne = async id => {
    const { get } = useApi()
    const response = await get(`/api/lab-cases/${id}`)
    return response?.data ?? null
  }

  const transition = async (row, phase) => {
    if (!row?.id || !phase) return null

    const { post } = useApi()
    const toast = useToast()
    acting.value = row.id

    try {
      const response = await post(`/api/lab-cases/${row.id}/phase`, { phase })
      toast.success(response?.meta?.message || 'Fase del caso actualizada.')
      await load()
      return response?.data ?? null
    } catch (thrown) {
      toast.error(labCaseMessageFor(thrown, 'No se pudo cambiar la fase del caso'))
      throw thrown
    } finally {
      acting.value = null
    }
  }

  const handleCaseReady = event => {
    const work = event?.case?.work
    useToast().info(work ? `Caso de laboratorio listo: ${work}` : 'Caso de laboratorio listo')
    load()
  }

  const subscribe = () => {
    if (channel) return

    try {
      const { privateChannel } = useEcho()
      const nextChannel = privateChannel('lab-cases')
      if (!nextChannel) return
      channel = nextChannel
      channel.listen('.caso_laboratorio_listo', handleCaseReady)
    } catch (thrown) {
      // Reverb is often down in local dev: the desk stays usable with refresh.
      channel = null
    }
  }

  // Echo registers private channels with the `private-` prefix on the wire, so
  // leaving `lab-cases` would be inert: teardown names the wire channel.
  const unsubscribe = () => {
    try {
      const { echo } = useEcho()
      if (echo) echo.leave('private-lab-cases')
    } catch (thrown) {
      // teardown is best-effort
    }
    channel = null
  }

  return {
    cases,
    meta,
    filters,
    loading,
    error,
    acting,
    load,
    setFilter,
    goToPage,
    loadOne,
    transition,
    subscribe,
    unsubscribe
  }
}
