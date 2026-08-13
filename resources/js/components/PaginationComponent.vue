<template>
  <div class="flex items-center justify-between bg-theme-surface-elevated px-4 py-3 sm:px-6">
    <div class="flex flex-1 justify-between sm:hidden">
      <button
        :disabled="!hasPrevPage"
        class="relative inline-flex items-center rounded-md border border-theme bg-theme-surface-elevated px-4 py-2 text-sm font-medium text-theme-primary hover:bg-theme-surface disabled:opacity-50 disabled:cursor-not-allowed"
        @click="$emit('prev-page')"
      >
        Anterior
      </button>
      <button
        :disabled="!hasNextPage"
        class="relative ml-3 inline-flex items-center rounded-md border border-theme bg-theme-surface-elevated px-4 py-2 text-sm font-medium text-theme-primary hover:bg-theme-surface disabled:opacity-50 disabled:cursor-not-allowed"
        @click="$emit('next-page')"
      >
        Siguiente
      </button>
    </div>

    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
      <div>
        <p class="text-sm text-theme-primary">
          Mostrando
          <span class="font-medium">{{ pagination.from }}</span>
          a
          <span class="font-medium">{{ pagination.to }}</span>
          de
          <span class="font-medium">{{ pagination.total }}</span>
          resultados
        </p>
      </div>

      <div class="flex items-center space-x-2">
        <!-- Per page selector -->
        <div class="flex items-center space-x-2">
          <label for="per-page" class="text-sm text-theme-primary">Mostrar:</label>
          <select
            id="per-page"
            :value="pagination.perPage"
            class="rounded-md border-theme text-sm focus:border-primary-500 focus:ring-primary-500 bg-theme-surface-elevated text-theme-primary"
            @change="$emit('change-per-page', parseInt($event.target.value))"
          >
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>

        <!-- Page navigation -->
        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
          <button
            :disabled="!hasPrevPage"
            class="relative inline-flex items-center rounded-l-md px-2 py-2 text-theme-secondary ring-1 ring-inset ring-theme hover:bg-theme-surface focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="$emit('prev-page')"
          >
            <span class="sr-only">Anterior</span>
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"
aria-hidden="true">
              <path
                fill-rule="evenodd"
                d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                clip-rule="evenodd"
              />
            </svg>
          </button>

          <!-- Page numbers -->
          <template v-for="page in visiblePages" :key="page">
            <button
              v-if="page !== '...'"
              class="relative inline-flex items-center px-4 py-2 text-sm font-semibold"
              :class="[
                page === pagination.currentPage
                  ? 'z-10 bg-accent text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent'
                  : 'text-theme-primary ring-1 ring-inset ring-theme hover:bg-theme-surface focus:z-20 focus:outline-offset-0'
              ]"
              @click="$emit('go-to-page', page)"
            >
              {{ page }}
            </button>
            <span
              v-else
              class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-theme-primary ring-1 ring-inset ring-theme focus:outline-offset-0"
            >
              ...
            </span>
          </template>

          <button
            :disabled="!hasNextPage"
            class="relative inline-flex items-center rounded-r-md px-2 py-2 text-theme-secondary ring-1 ring-inset ring-theme hover:bg-theme-surface focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="$emit('next-page')"
          >
            <span class="sr-only">Siguiente</span>
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"
aria-hidden="true">
              <path
                fill-rule="evenodd"
                d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                clip-rule="evenodd"
              />
            </svg>
          </button>
        </nav>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'

export default {
  name: 'PaginationComponent',
  props: {
    pagination: {
      type: Object,
      required: true
    },
    hasNextPage: {
      type: Boolean,
      required: true
    },
    hasPrevPage: {
      type: Boolean,
      required: true
    }
  },
  emits: ['prev-page', 'next-page', 'go-to-page', 'change-per-page'],
  setup(props) {
    const visiblePages = computed(() => {
      const current = props.pagination.currentPage
      const last = props.pagination.lastPage
      const pages = []

      if (last <= 7) {
        // Show all pages if 7 or fewer
        for (let i = 1; i <= last; i++) {
          pages.push(i)
        }
      } else {
        // Always show first page
        pages.push(1)

        if (current <= 4) {
          // Show first 5 pages and ellipsis
          for (let i = 2; i <= 5; i++) {
            pages.push(i)
          }
          pages.push('...')
          pages.push(last)
        } else if (current >= last - 3) {
          // Show ellipsis and last 5 pages
          pages.push('...')
          for (let i = last - 4; i <= last; i++) {
            pages.push(i)
          }
        } else {
          // Show ellipsis, current page and neighbors, ellipsis, last page
          pages.push('...')
          for (let i = current - 1; i <= current + 1; i++) {
            pages.push(i)
          }
          pages.push('...')
          pages.push(last)
        }
      }

      return pages
    })

    return {
      visiblePages
    }
  }
}
</script>
