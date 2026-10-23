import { ref } from 'vue'
import { useApi } from './useApi'

// #305 T7 — branch transfer requests are self-service on the asking side and
// group scope on the deciding side. The API already scopes `GET
// /api/branch-transfers` (own rows for a professional, incoming/outgoing rows
// for a sede administrator, everything for gerencia), so this composable only
// transports, buckets nothing, and never swallows an error.
//
// `error` belongs to the list load: the page's state ladder reads it. A failed
// mutation throws so the caller can render it where the action happened (the
// request form or the decision row) instead of replacing the whole surface.
const emptyPagination = () => ({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})

const asList = value => (Array.isArray(value) ? value : [])

const messageFor = (thrown, fallback) =>
  thrown?.response?.data?.message || thrown?.message || fallback

const buildQuery = (filters = {}) => {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      params.append(key, value)
    }
  })
  const query = params.toString()
  return query ? `?${query}` : ''
}

export function useBranchTransfers() {
  const requests = ref([])
  const pagination = ref(emptyPagination())
  const loading = ref(false)
  const error = ref(null)
  const destinations = ref([])
  const submitting = ref(false)

  const fetchRequests = async (filters = {}) => {
    const { get } = useApi()
    loading.value = true
    try {
      const response = await get(`/api/branch-transfers${buildQuery(filters)}`)
      requests.value = asList(response?.data)
      pagination.value = { ...emptyPagination(), ...(response?.meta || {}) }
      error.value = null
      return requests.value
    } catch (thrown) {
      error.value = messageFor(thrown, 'No se pudieron cargar los traslados')
      throw thrown
    } finally {
      loading.value = false
    }
  }

  // The destination catalog is the module's own door: it answers every ACTIVE
  // sede regardless of the actor's affiliation. The branch lookup
  // (`GET /api/branches/active`) is branch-scoped on the server, so a user
  // affiliated to a sede would only ever receive their own and could not ask
  // for a transfer at all.
  const fetchDestinations = async () => {
    const { get } = useApi()
    try {
      const response = await get('/api/branch-transfers/destinations')
      destinations.value = asList(response?.data)
      return destinations.value
    } catch (thrown) {
      error.value = messageFor(thrown, 'No se pudieron cargar las sedes')
      throw thrown
    }
  }

  const submit = async (path, payload) => {
    const { post } = useApi()
    submitting.value = true
    try {
      const response = await post(`/api/branch-transfers${path}`, payload)
      return response?.data ?? null
    } finally {
      submitting.value = false
    }
  }

  // The payload keeps the API's snake_case keys; `camelcase` ignores object
  // properties, so the wire shape stays where the contract defines it.
  const requestTransfer = payload =>
    submit('', { to_branch_id: payload.to_branch_id, reason: payload.reason })

  const cancelRequest = id => submit(`/${id}/cancel`, {})

  const approveRequest = (id, notes) => submit(`/${id}/approve`, { decision_notes: notes ?? '' })

  const rejectRequest = (id, notes) => submit(`/${id}/reject`, { decision_notes: notes ?? '' })

  const resetError = () => {
    error.value = null
  }

  return {
    requests,
    pagination,
    loading,
    error,
    destinations,
    submitting,
    fetchRequests,
    fetchDestinations,
    requestTransfer,
    cancelRequest,
    approveRequest,
    rejectRequest,
    resetError
  }
}
