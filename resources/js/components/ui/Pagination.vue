<template>
  <div class="pagination" role="navigation" aria-label="Paginación">
    <div class="pagination-mobile">
      <button
        @click="goToPage(currentPage - 1)"
        :disabled="currentPage <= 1"
        class="btn btn-outline"
        aria-label="Página anterior"
      >
        Anterior
      </button>
      <button
        @click="goToPage(currentPage + 1)"
        :disabled="currentPage >= totalPages"
        class="btn btn-outline"
        aria-label="Página siguiente"
      >
        Siguiente
      </button>
    </div>
    <div class="pagination-desktop">
      <div class="pagination-info">
        <p class="pagination-text">
          Mostrando
          <span class="font-medium">{{ (currentPage - 1) * perPage + 1 }}</span>
          a
          <span class="font-medium">{{ Math.min(currentPage * perPage, total) }}</span>
          de
          <span class="font-medium">{{ total }}</span>
          resultados
        </p>
      </div>
      <div class="pagination-nav">
        <nav class="pagination-nav-container" aria-label="Paginación de resultados">
          <button
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage <= 1"
            class="pagination-nav-button pagination-nav-button--prev"
            :aria-label="`Ir a la página ${currentPage - 1}`"
          >
            <span class="sr-only">Anterior</span>
            <svg class="pagination-nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
            </svg>
          </button>

          <template v-for="page in visiblePages" :key="page">
            <button
              v-if="page !== '...'"
              @click="goToPage(page)"
              :class="[
                'pagination-page-button',
                page === currentPage ? 'pagination-page-button--active' : 'pagination-page-button--inactive'
              ]"
              :aria-label="`Ir a la página ${page}`"
              :aria-current="page === currentPage ? 'page' : undefined"
            >
              {{ page }}
            </button>
            <span
              v-else
              class="pagination-ellipsis"
              aria-hidden="true"
            >
              ...
            </span>
          </template>

          <button
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage >= totalPages"
            class="pagination-nav-button pagination-nav-button--next"
            :aria-label="`Ir a la página ${currentPage + 1}`"
          >
            <span class="sr-only">Siguiente</span>
            <svg class="pagination-nav-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
            </svg>
          </button>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage: {
    type: Number,
    required: true
  },
  totalPages: {
    type: Number,
    required: true
  },
  total: {
    type: Number,
    required: true
  },
  perPage: {
    type: Number,
    default: 10
  }
})

const emit = defineEmits(['page-change'])

const visiblePages = computed(() => {
  const pages = []
  const current = props.currentPage
  const total = props.totalPages

  if (total <= 7) {
    // Si hay 7 páginas o menos, mostrar todas
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Lógica para mostrar páginas con elipsis
    if (current <= 4) {
      // Mostrar primeras páginas
      for (let i = 1; i <= 5; i++) {
        pages.push(i)
      }
      pages.push('...')
      pages.push(total)
    } else if (current >= total - 3) {
      // Mostrar últimas páginas
      pages.push(1)
      pages.push('...')
      for (let i = total - 4; i <= total; i++) {
        pages.push(i)
      }
    } else {
      // Mostrar páginas del medio
      pages.push(1)
      pages.push('...')
      for (let i = current - 1; i <= current + 1; i++) {
        pages.push(i)
      }
      pages.push('...')
      pages.push(total)
    }
  }

  return pages
})

const goToPage = (page) => {
  if (page >= 1 && page <= props.totalPages && page !== props.currentPage) {
    emit('page-change', page)
  }
}
</script>

<style scoped>
.pagination {
  @apply flex items-center justify-between border-t border-theme bg-theme-surface-elevated px-4 py-3 sm:px-6;
}

/* Mobile pagination */
.pagination-mobile {
  @apply flex flex-1 justify-between sm:hidden space-x-2;
}

/* Desktop pagination */
.pagination-desktop {
  @apply hidden sm:flex sm:flex-1 sm:items-center sm:justify-between;
}

.pagination-info {
  @apply flex-1;
}

.pagination-text {
  @apply text-sm text-theme-primary;
}

/* Navigation */
.pagination-nav {
  @apply flex-1;
}

.pagination-nav-container {
  @apply isolate inline-flex -space-x-px rounded-md shadow-sm;
}

.pagination-nav-button {
  @apply relative inline-flex items-center px-2 py-2 text-theme-secondary ring-1 ring-inset ring-theme hover:bg-theme-surface focus:z-20 focus:outline-offset-0 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200;
}

.pagination-nav-button--prev {
  @apply rounded-l-md;
}

.pagination-nav-button--next {
  @apply rounded-r-md;
}

.pagination-nav-icon {
  @apply h-5 w-5;
}

/* Page buttons */
.pagination-page-button {
  @apply relative inline-flex items-center px-4 py-2 text-sm font-semibold focus:z-20 focus:outline-offset-0 transition-all duration-200;
}

.pagination-page-button--active {
  @apply z-10 bg-accent text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2;
  outline-color: var(--color-accent);
}

.pagination-page-button--inactive {
  @apply text-theme-primary ring-1 ring-inset ring-theme hover:bg-theme-surface;
}

/* Ellipsis */
.pagination-ellipsis {
  @apply relative inline-flex items-center px-4 py-2 text-sm font-semibold text-theme-primary ring-1 ring-inset ring-theme focus:outline-offset-0;
}

/* Button styles */
.btn {
  @apply inline-flex items-center justify-center px-3 py-2 border border-transparent text-sm font-medium rounded-md transition-colors;
}

.btn-outline {
  @apply border-theme text-theme-primary bg-theme-surface-elevated hover:bg-theme-surface;
}
</style>
