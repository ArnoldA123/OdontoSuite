<template>
  <AppLayout>
    <header
      data-locks-hero
      class="mb-6 border border-hairline p-6 md:p-8"
      style="
        background: var(--color-canvas);
        border-radius: var(--radius-panel);
        box-shadow: var(--elevation-2);
      "
    >
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="min-w-0">
          <p
            class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
            data-hero-eyebrow
          >
            Configuración
          </p>
          <h2 class="mt-2 text-4xl font-semibold text-label" data-hero-title>Bloqueo Contable</h2>
          <p class="mt-2 text-sm text-theme-secondary" data-hero-caption>
            Fecha de cierre de cada módulo: nada anterior se edita sin autorización registrada
          </p>
        </div>
        <div class="flex flex-shrink-0 items-center gap-3">
          <UiButton variant="secondary" data-hero-back @click="goBack">
            <template #icon-left>
              <ArrowLeftIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Volver
          </UiButton>
          <UiButton variant="secondary" data-hero-refresh @click="loadLocks">
            <template #icon-left>
              <ArrowPathIcon class="h-4 w-4" aria-hidden="true" />
            </template>
            Actualizar
          </UiButton>
        </div>
      </div>
    </header>

    <section
      aria-label="Resumen del bloqueo contable"
      class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3"
    >
      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="modules"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Módulos</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="modules"
        >
          {{ kpiModules }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Bajo control contable</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="locked"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Con bloqueo</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="locked"
        >
          {{ kpiLocked }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Cierre vigente</p>
      </UiCard>

      <UiCard
        variant="glass"
        padding="sm"
        data-kpi-card="open"
        :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
      >
        <p class="text-xs font-medium uppercase text-theme-secondary">Sin bloqueo</p>
        <p
          class="mt-2 text-2xl font-bold text-label tabular-nums leading-none"
          data-kpi-value="open"
        >
          {{ kpiOpen }}
        </p>
        <p class="mt-1 text-xs text-theme-secondary">Sin cierre declarado</p>
      </UiCard>
    </section>

    <UiCard variant="glass" class="mb-6">
      <div v-if="hasGroupScope" class="flex flex-col gap-4 lg:flex-row lg:items-center">
        <UiSelect
          v-model="scopeBranch"
          :options="scopeOptions"
          label="Alcance"
          class="w-full lg:w-80"
          data-scope-select
          @change="loadLocks"
        />
        <p class="text-xs text-theme-secondary" data-scope-hint>
          «Toda la organización» fija la fila global; el cierre propio de una sede gana sobre ella.
        </p>
      </div>
      <div v-else class="flex flex-col gap-1" data-scope-fixed>
        <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Alcance</p>
        <p class="text-sm text-theme-primary" data-scope-name>{{ scopeName }}</p>
      </div>
    </UiCard>

    <section aria-label="Bloqueo contable por módulo" class="space-y-4">
      <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-3">
        <h2 class="text-2xl font-semibold text-label">Módulos contables</h2>
        <p class="text-sm text-theme-secondary">Fija o retira el cierre de cada módulo</p>
      </div>

      <div data-locks-list>
        <div v-if="loading" data-locks-loading :aria-busy="true">
          <UiSkeleton
            variant="table"
            animation="wave"
            aria-label="Cargando bloqueos contables..."
          />
        </div>

        <div
          v-else-if="loadError"
          data-locks-error
          role="alert"
          class="flex flex-wrap items-center justify-between gap-4 rounded-[var(--radius-panel)] border border-hairline bg-systemRed-50 p-5"
        >
          <div class="flex min-w-0 items-center gap-3">
            <ExclamationTriangleIcon
              class="h-6 w-6 flex-shrink-0 text-systemRed-600"
              aria-hidden="true"
            />
            <div class="min-w-0">
              <p class="text-sm font-medium text-theme-primary">
                No pudimos cargar el bloqueo contable
              </p>
              <p class="text-sm text-theme-secondary">Revisa tu conexión y vuelve a intentarlo.</p>
            </div>
          </div>
          <UiButton variant="primary" size="sm" data-locks-retry @click="loadLocks">
            Reintentar
          </UiButton>
        </div>

        <div v-else-if="locks.length === 0" data-locks-empty>
          <UiEmptyState
            title="No hay módulos para mostrar"
            description="El servicio no devolvió módulos de bloqueo contable para este alcance."
            :icon="LockClosedIcon"
          />
        </div>

        <UiDataTable
          v-else
          :data="locks"
          :columns="columns"
          row-key="module"
          :searchable="false"
          :show-header="false"
          :pagination="false"
          data-locks-table
        >
          <template #cell-module="{ row }">
            <div class="min-w-0">
              <p class="text-sm font-medium text-theme-primary" data-row-module>
                {{ getModuleLabel(row.module) }}
              </p>
              <p class="font-mono text-xs text-theme-secondary">{{ row.module }}</p>
            </div>
          </template>

          <template #cell-locked_through="{ row }">
            <span
              v-if="hasLock(row)"
              class="text-sm text-theme-primary tabular-nums"
              data-row-locked-through
            >
              {{ formatLockDate(row.locked_through) }}
            </span>
            <span v-else class="text-sm text-theme-secondary" data-row-locked-through>
              Sin bloqueo
            </span>
          </template>

          <template #cell-source="{ row }">
            <UiStatusBadge
              data-row-source
              :variant="getSourceVariant(row.source)"
              :label="getSourceLabel(row.source)"
              size="sm"
            />
          </template>

          <template #cell-set_by="{ row }">
            <span class="text-sm text-theme-primary" data-row-set-by>
              {{ row.set_by ? `Usuario #${row.set_by}` : '—' }}
            </span>
          </template>

          <template #cell-set_at="{ row }">
            <span class="text-sm text-theme-secondary tabular-nums" data-row-set-at>
              {{ formatLockMoment(row.set_at) || '—' }}
            </span>
          </template>

          <template #actions="{ row }">
            <template v-if="canManage">
              <UiButton
                variant="ghost"
                size="sm"
                class="text-systemBlue-600 hover:text-systemBlue-700"
                data-row-action="lock"
                @click="openLock(row)"
              >
                {{ row.locked_through ? 'Cambiar' : 'Fijar' }}
              </UiButton>
              <UiButton
                v-if="hasLock(row)"
                variant="ghost"
                size="sm"
                class="text-systemRed-700"
                data-row-action="clear"
                @click="clearLock(row)"
              >
                Retirar
              </UiButton>
            </template>
          </template>
        </UiDataTable>
      </div>
    </section>

    <UiModal
      v-model="showLockModal"
      data-lock-modal
      :title="lockModalTitle"
      size="sm"
      @close="closeLockModal"
    >
      <div class="space-y-4">
        <p class="text-sm text-theme-secondary" data-lock-modal-caption>
          Cierre para {{ scopeName }}. Los registros de este módulo con fecha anterior o igual al
          cierre no se podrán editar ni anular sin autorización registrada.
        </p>
        <UiInput
          v-model="lockDate"
          data-lock-date
          type="date"
          label="Fecha de cierre contable"
          :error="dateError"
          :disabled="saving"
          required
        />
      </div>
      <template #footer>
        <UiButton
          type="button"
          variant="secondary"
          :disabled="saving"
          data-lock-cancel
          @click="closeLockModal"
        >
          Cancelar
        </UiButton>
        <UiButton type="button" :loading="saving" data-lock-submit @click="submitLock">
          Fijar bloqueo
        </UiButton>
      </template>
    </UiModal>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowLeftIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon,
  LockClosedIcon
} from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useAuth } from '../../composables/useAuth'
import { useConfirm } from '../../composables/useConfirm'
import { useErrorHandler } from '../../composables/useErrorHandler'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import {
  buildLockDeletePath,
  buildLockModulePath,
  buildLockPayload,
  buildLocksPath,
  formatLockDate,
  formatLockMoment,
  getModuleLabel,
  getSourceLabel,
  getSourceVariant,
  hasLock
} from './accountingLockMeta'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiEmptyState from '../../components/ui/EmptyState.vue'
import UiInput from '../../components/ui/Input.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'

const router = useRouter()
const { get, put, delete: remove } = useApi()
const { user } = useAuth()
const { can } = usePermissions()
const { confirm } = useConfirm()
const toast = useToast()
const { handleError } = useErrorHandler()

const loading = ref(true)
const loadError = ref(false)
const locks = ref([])
const branches = ref([])

// '' is the group row ("toda la organización"); a numeric string picks one sede.
const scopeBranch = ref('')

const showLockModal = ref(false)
const saving = ref(false)
const lockTarget = ref(null)
const lockDate = ref('')
const dateError = ref('')

const columns = [
  { key: 'module', label: 'Módulo' },
  { key: 'locked_through', label: 'Fecha efectiva' },
  { key: 'source', label: 'Origen' },
  { key: 'set_by', label: 'Actualizado por' },
  { key: 'set_at', label: 'Actualizado el' }
]

// Sin sede asignada el usuario tiene alcance de grupo (ADR-0010); con sede sólo
// opera la suya y el selector de alcance no existe para él.
const hasGroupScope = computed(() => !user.value?.branch_id)

const canManage = computed(() => can.manageAccountingLock?.value === true)

// The value the API receives. A sede administrator sends nothing: the API
// resolves the actor's own sede (ADR-0010), so a stale `branch_id` in the
// stored session can neither widen nor break their scope.
const sentBranchId = computed(() => {
  if (!hasGroupScope.value) return null

  return scopeBranch.value === '' ? null : Number(scopeBranch.value)
})

// The sede the surface is showing, only for copy.
const displayedBranchId = computed(() =>
  hasGroupScope.value ? sentBranchId.value : (user.value?.branch_id ?? null)
)

const scopeOptions = computed(() => [
  { value: '', label: 'Toda la organización' },
  ...branches.value.map(branch => ({ value: String(branch.id), label: branch.name }))
])

const branchName = id => branches.value.find(branch => Number(branch.id) === Number(id))?.name || ''

const scopeName = computed(() => {
  if (!hasGroupScope.value) return branchName(displayedBranchId.value) || 'Tu sede'

  if (sentBranchId.value === null) return 'Toda la organización'

  return branchName(sentBranchId.value) || `Sede #${sentBranchId.value}`
})

const kpisUnavailable = computed(() => loading.value || loadError.value)

const kpiModules = computed(() => (kpisUnavailable.value ? 'N/D' : String(locks.value.length)))

const kpiLocked = computed(() =>
  kpisUnavailable.value ? 'N/D' : String(locks.value.filter(row => hasLock(row)).length)
)

const kpiOpen = computed(() =>
  kpisUnavailable.value ? 'N/D' : String(locks.value.filter(row => !hasLock(row)).length)
)

const lockTargetLabel = computed(() =>
  lockTarget.value ? getModuleLabel(lockTarget.value.module) : ''
)

const lockModalTitle = computed(() =>
  lockTarget.value?.locked_through
    ? `Cambiar cierre de «${lockTargetLabel.value}»`
    : `Fijar cierre de «${lockTargetLabel.value}»`
)

const loadBranches = async () => {
  try {
    const response = await get('/api/branches/active')
    branches.value = Array.isArray(response?.data) ? response.data : []
  } catch {
    branches.value = []
  }
}

// A scope change can overtake an in-flight read; only the newest answer may
// land, or the table would show another sede's lock.
let loadToken = 0

const loadLocks = async () => {
  const token = ++loadToken
  loading.value = true
  loadError.value = false

  try {
    const response = await get(buildLocksPath(sentBranchId.value))
    if (token !== loadToken) return

    locks.value = Array.isArray(response?.data) ? response.data : []
  } catch {
    if (token !== loadToken) return

    loadError.value = true
    locks.value = []
  } finally {
    if (token === loadToken) loading.value = false
  }
}

const openLock = row => {
  lockTarget.value = row
  lockDate.value = row?.locked_through || ''
  dateError.value = ''
  showLockModal.value = true
}

const closeLockModal = () => {
  showLockModal.value = false
  lockTarget.value = null
  lockDate.value = ''
  dateError.value = ''
}

const submitLock = async () => {
  if (!lockTarget.value) return

  const date = String(lockDate.value ?? '').trim()

  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
    dateError.value = 'Indica la fecha de cierre contable.'
    return
  }

  dateError.value = ''
  saving.value = true

  const { module } = lockTarget.value

  try {
    await put(buildLockModulePath(module), buildLockPayload(date, sentBranchId.value))
    closeLockModal()
    toast.success(`Bloqueo de «${getModuleLabel(module)}» fijado hasta el ${formatLockDate(date)}`)
    await loadLocks()
  } catch (error) {
    handleError(error, 'No pudimos fijar el bloqueo contable')
  } finally {
    saving.value = false
  }
}

const clearLock = async row => {
  const ok = await confirm({
    title: 'Retirar bloqueo contable',
    message: `¿Retirar el cierre de «${getModuleLabel(row.module)}» para ${scopeName.value}? Los registros volverán a poder editarse sin autorización.`,
    confirmText: 'Retirar',
    variant: 'danger'
  })
  if (!ok) return

  try {
    const response = await remove(buildLockDeletePath(row.module, sentBranchId.value))
    toast.success(response?.meta?.message || `Bloqueo de «${getModuleLabel(row.module)}» retirado`)
    await loadLocks()
  } catch (error) {
    handleError(error, 'No pudimos retirar el bloqueo contable')
  }
}

const goBack = () => {
  router.back()
}

onMounted(() => {
  loadBranches()
  loadLocks()
})
</script>
