import { ref, computed } from 'vue'

export function usePagination(initialPerPage = 20) {
  const currentPage = ref(1)
  const perPage = ref(initialPerPage)
  const total = ref(0)
  const lastPage = ref(1)
  const loading = ref(false)

  const pagination = computed(() => ({
    currentPage: currentPage.value,
    perPage: perPage.value,
    total: total.value,
    lastPage: lastPage.value,
    from: (currentPage.value - 1) * perPage.value + 1,
    to: Math.min(currentPage.value * perPage.value, total.value)
  }))

  const hasNextPage = computed(() => currentPage.value < lastPage.value)
  const hasPrevPage = computed(() => currentPage.value > 1)

  const updatePagination = (meta) => {
    currentPage.value = meta.current_page || 1
    perPage.value = meta.per_page || initialPerPage
    total.value = meta.total || 0
    lastPage.value = meta.last_page || 1
  }

  const nextPage = () => {
    if (hasNextPage.value) {
      currentPage.value++
    }
  }

  const prevPage = () => {
    if (hasPrevPage.value) {
      currentPage.value--
    }
  }

  const goToPage = (page) => {
    if (page >= 1 && page <= lastPage.value) {
      currentPage.value = page
    }
  }

  const changePerPage = (newPerPage) => {
    perPage.value = newPerPage
    currentPage.value = 1 // Reset to first page when changing per page
  }

  const reset = () => {
    currentPage.value = 1
    perPage.value = initialPerPage
    total.value = 0
    lastPage.value = 1
  }

  return {
    currentPage,
    perPage,
    total,
    lastPage,
    loading,
    pagination,
    hasNextPage,
    hasPrevPage,
    updatePagination,
    nextPage,
    prevPage,
    goToPage,
    changePerPage,
    reset
  }
}
