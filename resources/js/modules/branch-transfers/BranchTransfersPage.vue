<template>
  <AppLayout>
    <div class="space-y-8" data-branch-transfers>
      <!--
        Hero band in the dashboard's surface vocabulary: the page owns its
        visible title (h2, not h1), so AppLayout keeps its heading for the
        document outline only.
      -->
      <header
        data-branch-transfers-hero
        class="p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
      >
        <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">Sedes</p>
        <h2 class="mt-2 text-4xl font-semibold text-label" data-branch-transfers-title>
          Traslados de Sede
        </h2>
        <p class="mt-2 text-sm text-theme-secondary">
          Pide tu cambio de sede y sigue la decisión de gerencia.
        </p>
      </header>

      <UiCard data-branch-current variant="flat" padding="md">
        <p class="text-xs font-medium uppercase text-theme-secondary">Mi sede actual</p>
        <p class="mt-1 text-lg font-semibold text-label" data-branch-current-name>
          {{ currentBranchLabel }}
        </p>
        <p v-if="!currentBranch" class="mt-1 text-sm text-theme-secondary">
          Operas sobre todas las sedes del grupo.
        </p>
      </UiCard>

      <!-- ---------------------------------------------------------------- -->
      <!-- Self-service request                                             -->
      <!-- ---------------------------------------------------------------- -->
      <section
        v-if="canRequest"
        aria-label="Nueva solicitud de traslado"
        class="space-y-3"
        data-transfer-request-section
      >
        <div class="flex items-baseline gap-3">
          <h3 class="text-xl font-semibold text-label">Nueva solicitud</h3>
          <p class="text-sm text-theme-secondary">Una sola solicitud abierta a la vez</p>
        </div>

        <UiCard variant="flat" padding="md">
          <form class="space-y-4" data-transfer-form @submit.prevent="submitRequest">
            <div role="group" aria-labelledby="transfer-branch-label">
              <p
                id="transfer-branch-label"
                class="block mb-1 text-sm font-medium text-theme-primary"
              >
                Sede de destino
              </p>
              <UiSelect
                v-model="form.to_branch_id"
                :options="branchOptions"
                :disabled="submitting || branchOptions.length === 0"
                label="Sede de destino"
                placeholder="Elige la sede"
                data-transfer-branch
              />
              <p v-if="branchOptions.length === 0" class="mt-1 text-xs text-theme-secondary">
                No hay otra sede activa disponible para pedir el traslado.
              </p>
            </div>

            <div>
              <label
                class="block mb-1 text-sm font-medium text-theme-primary"
                for="transfer-reason"
              >
                Motivo
              </label>
              <UiTextarea
                id="transfer-reason"
                v-model="form.reason"
                :rows="3"
                :error="!!formError"
                aria-label="Motivo del traslado"
                placeholder="Cuéntanos por qué pides el traslado"
                data-transfer-reason
              />
            </div>

            <p
              v-if="formError"
              data-transfer-form-error
              role="alert"
              class="text-sm text-systemRed-600"
            >
              {{ formError }}
            </p>

            <UiButton
              type="submit"
              data-transfer-submit
              :disabled="!canSubmit"
              :loading="submitting"
            >
              Enviar solicitud
            </UiButton>
          </form>
        </UiCard>
      </section>

      <!-- ---------------------------------------------------------------- -->
      <!-- Visible scope                                                    -->
      <!-- ---------------------------------------------------------------- -->
      <section aria-label="Solicitudes de traslado" class="space-y-3">
        <div class="flex items-baseline gap-3">
          <h3 class="text-xl font-semibold text-label" data-transfers-section-title>
            Solicitudes de traslado
          </h3>
          <p class="text-sm text-theme-secondary">Lo que tu alcance puede leer</p>
        </div>

        <div v-if="loading && requests.length === 0" data-transfers-loading aria-busy="true">
          <UiSkeleton
            variant="list"
            animation="wave"
            aria-label="Cargando solicitudes de traslado"
          />
        </div>

        <div
          v-else-if="loadFailed"
          data-transfers-error
          role="alert"
          class="flex flex-wrap items-center gap-4 p-4 rounded-ios bg-systemRed-50"
          style="border: 1px solid var(--color-hairline)"
        >
          <ExclamationTriangleIcon
            class="h-8 w-8 flex-shrink-0 text-systemRed-600"
            aria-hidden="true"
          />
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-theme-primary">
              No pudimos cargar las solicitudes de traslado
            </p>
            <p class="mt-1 text-sm text-theme-secondary" data-transfers-error-message>
              {{ error || 'Revisa tu conexión y vuelve a intentarlo.' }}
            </p>
          </div>
          <UiButton variant="primary" size="sm" data-transfers-retry @click="load">
            Reintentar
          </UiButton>
        </div>

        <UiSectionEmpty
          v-else-if="requests.length === 0"
          data-transfers-empty
          title="No hay solicitudes de traslado"
          description="Aquí aparecen las solicitudes que tu alcance puede leer: las tuyas y, si administras una sede o el grupo, las de tu ámbito."
        />

        <div v-else class="space-y-3">
          <UiDataTable
            :data="requests"
            :columns="requestColumns"
            :searchable="false"
            :show-header="false"
            :pagination="false"
            data-transfers-table
          >
            <template #cell-applicant="{ row }">
              <span class="text-sm font-medium text-theme-primary" data-transfer-applicant>
                {{ row.user?.name || 'Profesional' }}
              </span>
            </template>
            <template #cell-route="{ row }">
              <span class="text-sm text-theme-primary" data-transfer-route>
                {{ branchName(row.from_branch) }} → {{ branchName(row.to_branch) }}
              </span>
            </template>
            <template #cell-reason="{ row }">
              <span class="text-sm text-theme-secondary">{{ row.reason }}</span>
            </template>
            <template #cell-status="{ row }">
              <UiStatusPill :status="row.status" :variant="statusVariant(row.status)" size="sm">
                {{ statusLabel(row.status) }}
              </UiStatusPill>
            </template>
            <template #cell-requested_at="{ row }">
              <span class="text-sm text-theme-secondary tabular-nums">
                {{ formatDate(row.requested_at) }}
              </span>
            </template>
            <template #cell-decided_at="{ row }">
              <span class="text-sm text-theme-secondary tabular-nums">
                {{ formatDate(row.decided_at) }}
              </span>
            </template>
            <template #actions="{ row }">
              <UiButton
                v-if="canCancel(row)"
                variant="ghost"
                size="sm"
                class="text-systemRed-600 hover:text-systemRed-700"
                :data-transfer-cancel="row.id"
                :disabled="submitting"
                @click="cancelRequestRow(row)"
              >
                Cancelar
              </UiButton>
            </template>
          </UiDataTable>

          <UiPagination
            v-if="pagination.last_page > 1"
            :current-page="pagination.current_page"
            :total-pages="pagination.last_page"
            :total="pagination.total"
            :per-page="pagination.per_page"
            @page-change="goToPage"
          />
        </div>
      </section>

      <!-- ---------------------------------------------------------------- -->
      <!-- Decisions (group scope)                                          -->
      <!-- ---------------------------------------------------------------- -->
      <section
        v-if="canDecide"
        aria-label="Solicitudes por decidir"
        class="space-y-3"
        data-transfers-decisions
      >
        <div class="flex items-baseline gap-3">
          <h3 class="text-xl font-semibold text-label">Solicitudes por decidir</h3>
          <p class="text-sm text-theme-secondary">Pendientes de decisión de gerencia</p>
        </div>

        <!--
          The empty copy is only true once the list actually loaded: while the
          first fetch is in flight, or when it failed, the section renders no
          "sin pendientes" claim.
        -->
        <UiSectionEmpty
          v-if="!loading && !loadFailed && pendingRequests.length === 0"
          data-transfers-decisions-empty
          title="Sin solicitudes pendientes"
          description="No hay traslados esperando decisión."
        />

        <div v-else-if="!loading && !loadFailed" class="grid gap-3">
          <UiCard
            v-for="row in pendingRequests"
            :key="row.id"
            variant="flat"
            data-decision-row
            :data-decision-id="row.id"
          >
            <div class="space-y-3">
              <div class="flex flex-wrap items-baseline gap-2">
                <p class="font-medium text-label" data-decision-applicant>
                  {{ row.user?.name || 'Profesional' }}
                </p>
                <p class="text-sm text-theme-secondary">
                  {{ branchName(row.from_branch) }} → {{ branchName(row.to_branch) }}
                </p>
                <p class="text-xs text-theme-secondary tabular-nums">
                  {{ formatDate(row.requested_at) }}
                </p>
              </div>

              <p class="text-sm text-theme-primary" data-decision-reason>{{ row.reason }}</p>

              <UiInput
                v-model="decisionNotes[row.id]"
                :data-decision-notes="row.id"
                placeholder="Notas de la decisión (opcional)"
                aria-label="Notas de la decisión"
                size="sm"
              />

              <p
                v-if="decisionErrors[row.id]"
                :data-decision-error="row.id"
                role="alert"
                class="text-sm text-systemRed-600"
              >
                {{ decisionErrors[row.id] }}
              </p>

              <div class="flex gap-2">
                <UiButton
                  size="sm"
                  :data-decision-approve="row.id"
                  :disabled="submitting"
                  @click="decide(row, 'approve')"
                >
                  Aprobar
                </UiButton>
                <UiButton
                  variant="danger"
                  size="sm"
                  :data-decision-reject="row.id"
                  :disabled="submitting"
                  @click="decide(row, 'reject')"
                >
                  Rechazar
                </UiButton>
              </div>
            </div>
          </UiCard>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiCard from '../../components/ui/Card.vue'
import UiDataTable from '../../components/ui/DataTable.vue'
import UiInput from '../../components/ui/Input.vue'
import UiPagination from '../../components/ui/Pagination.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiSkeleton from '../../components/ui/Skeleton.vue'
import UiStatusPill from '../../components/ui/StatusPill.vue'
import UiSectionEmpty from '../../components/ui/UiSectionEmpty.vue'
import UiTextarea from '../../components/ui/UiTextarea.vue'
import { useAuth } from '../../composables/useAuth'
import { useBranchTransfers } from '../../composables/useBranchTransfers'
import { useConfirm } from '../../composables/useConfirm'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'

const { user, getCurrentUser } = useAuth()
const { can } = usePermissions()
const { confirm } = useConfirm()
const toast = useToast()
const {
  requests,
  pagination,
  loading,
  error,
  branches,
  submitting,
  fetchRequests,
  fetchActiveBranches,
  requestTransfer,
  cancelRequest,
  approveRequest,
  rejectRequest,
  resetError
} = useBranchTransfers()

const form = reactive({ to_branch_id: null, reason: '' })
const formError = ref('')
const loadFailed = ref(false)
const decisionNotes = reactive({})
const decisionErrors = reactive({})

const canRequest = computed(() => Boolean(can.requestBranchTransfer?.value))

// The affiliation lives in the session (#305): `branch_id` is the sede the user
// operates, and `null` is group scope.
const userBranchId = computed(() => user.value?.branch_id ?? null)

// Deciding needs to *know* the actor has group scope: a session stored before
// the affiliation was published (`branch_id` absent) fails closed here instead
// of offering doors the API refuses.
const groupScope = computed(() => user.value?.branch_id === null)

// Ids travel as integers from the API, but a session restored from storage can
// hold a string; compare as text so the match never depends on the type.
const sameBranch = (left, right) =>
  left !== null &&
  left !== undefined &&
  right !== null &&
  right !== undefined &&
  String(left) === String(right)

// Deciding also needs group scope (D6): `decide.branch_transfer` is granted to
// administrador, so a sede administrator holds the permission but the API
// answers 403 without `branch.global`. The surface must not offer a door that
// always refuses.
const canDecide = computed(() => Boolean(can.decideBranchTransfer?.value) && groupScope.value)

const isOwnRequest = row =>
  row?.user_id !== null &&
  row?.user_id !== undefined &&
  String(row.user_id) === String(user.value?.id)

const currentBranch = computed(
  () => branches.value.find(branch => sameBranch(branch.id, userBranchId.value)) || null
)
const currentBranchLabel = computed(() => currentBranch.value?.name || 'Alcance de grupo')

// Asking for the sede you already belong to is not a transfer, and an inactive
// sede is not a destination (S4). Group scope has no sede to exclude, so every
// active sede is a valid destination for it (D1).
const branchOptions = computed(() =>
  branches.value
    .filter(branch => branch.is_active !== false && !sameBranch(branch.id, userBranchId.value))
    .map(branch => ({ value: branch.id, label: branch.name }))
)

const canSubmit = computed(
  () =>
    form.to_branch_id !== null &&
    form.to_branch_id !== undefined &&
    form.reason.trim().length > 0 &&
    !submitting.value
)

// The table renders exactly what the API scoped: own rows for a professional,
// incoming/outgoing rows for a sede administrator, everything for gerencia. The
// only client-side decision is who may withdraw a row.
const canCancel = row => row?.status === 'pending' && isOwnRequest(row)

// Nobody decides their own request (D3): the requester cancels, gerencia
// rejects.
const pendingRequests = computed(() =>
  requests.value.filter(row => row.status === 'pending' && !isOwnRequest(row))
)

const requestColumns = [
  { key: 'applicant', label: 'Solicitante' },
  { key: 'route', label: 'Trayecto' },
  { key: 'reason', label: 'Motivo' },
  { key: 'status', label: 'Estado' },
  { key: 'requested_at', label: 'Solicitada' },
  { key: 'decided_at', label: 'Decidida' }
]

const STATUS = {
  pending: { label: 'Pendiente', variant: 'warning' },
  approved: { label: 'Aprobada', variant: 'success' },
  rejected: { label: 'Rechazada', variant: 'error' },
  cancelled: { label: 'Cancelada', variant: 'neutral' }
}

const statusLabel = status => STATUS[status]?.label || status
const statusVariant = status => STATUS[status]?.variant || 'neutral'

const branchName = branch => branch?.name || 'Sin sede'

const formatDate = value => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}

const messageFor = (thrown, fallback) =>
  thrown?.response?.data?.message || thrown?.message || fallback

const load = async () => {
  loadFailed.value = false
  resetError()
  try {
    await Promise.all([fetchActiveBranches(), fetchRequests()])
  } catch {
    // The composable already recorded the server message in `error`; the page
    // only flips its own flag so the ladder shows the retry surface.
    loadFailed.value = true
  }
}

const goToPage = async page => {
  try {
    await fetchRequests({ page })
  } catch {
    loadFailed.value = true
  }
}

const submitRequest = async () => {
  if (!canSubmit.value) return
  formError.value = ''
  try {
    await requestTransfer({ to_branch_id: form.to_branch_id, reason: form.reason.trim() })
    toast.success('Solicitud de traslado enviada.')
    form.to_branch_id = null
    form.reason = ''
    await fetchRequests()
  } catch (thrown) {
    // 409 (one open request) and 422 land here: the form is where the user can
    // fix them, so the list is left mounted.
    formError.value = messageFor(thrown, 'No se pudo enviar la solicitud')
  }
}

const cancelRequestRow = async row => {
  const ok = await confirm({
    title: 'Cancelar solicitud',
    message: `¿Cancelar tu solicitud de traslado a ${branchName(row.to_branch)}?`,
    confirmText: 'Cancelar solicitud',
    cancelText: 'Volver',
    variant: 'danger'
  })
  if (!ok) return

  try {
    await cancelRequest(row.id)
    toast.success('Solicitud cancelada.')
    await fetchRequests()
  } catch (thrown) {
    toast.error(messageFor(thrown, 'No se pudo cancelar la solicitud'))
  }
}

const decide = async (row, decision) => {
  const notes = (decisionNotes[row.id] || '').trim()
  delete decisionErrors[row.id]
  try {
    if (decision === 'approve') {
      await approveRequest(row.id, notes)
      toast.success('Solicitud aprobada.')
    } else {
      await rejectRequest(row.id, notes)
      toast.success('Solicitud rechazada.')
    }
    decisionNotes[row.id] = ''
    await fetchRequests()
  } catch (thrown) {
    // 403 (own request, sede administrator) and 409 (the origin sede changed)
    // stay attached to the row that failed instead of blanking the section.
    decisionErrors[row.id] = messageFor(
      thrown,
      decision === 'approve'
        ? 'No se pudo aprobar la solicitud'
        : 'No se pudo rechazar la solicitud'
    )
  }
}

// A session stored before #305 carries no affiliation. The page needs it to
// name the current sede, to exclude it from the destinations and to gate the
// decision section, so it asks for the payload once here instead of forcing a
// refresh on every route (the global bootstrap only checks permissions).
const resolveAffiliation = async () => {
  if (!user.value || Object.prototype.hasOwnProperty.call(user.value, 'branch_id')) {
    return
  }

  try {
    await getCurrentUser()
  } catch {
    // The surface degrades from group scope to an explained refusal; the API
    // still enforces the rule.
  }
}

onMounted(async () => {
  await resolveAffiliation()
  await load()
})
</script>
