<template>
  <!-- Desktop table row -->
  <tr v-if="variant === 'table'" data-patient-row class="hover:bg-theme-surface transition-colors">
    <td class="px-6 py-4 whitespace-nowrap">
      <div class="flex items-center">
        <div class="flex-shrink-0 h-12 w-12">
          <div class="h-12 w-12 rounded-xl bg-gradient-accent flex items-center justify-center">
            <span class="text-sm font-semibold text-white">{{ initials }}</span>
          </div>
        </div>
        <div class="ml-4">
          <div class="text-sm font-semibold text-theme-primary">{{ displayName }}</div>
          <div
            class="text-sm text-theme-secondary"
            style="font-feature-settings: var(--font-features-tabular-nums)"
          >
            ID: {{ patient.id }}
          </div>
        </div>
      </div>
    </td>
    <td class="px-6 py-4 whitespace-nowrap">
      <div class="text-sm text-theme-primary">{{ patient.phone }}</div>
      <div class="text-sm text-theme-secondary">{{ patient.email }}</div>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary">{{ birthLabel }}</td>
    <td
      class="px-6 py-4 whitespace-nowrap text-sm text-theme-primary"
      style="font-feature-settings: var(--font-features-tabular-nums)"
    >
      {{ ageLabel }}
    </td>
    <td class="px-6 py-4 whitespace-nowrap">
      <UiBadge :variant="statusVariant" size="sm" shape="pill">{{ statusLabel }}</UiBadge>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
      <div class="flex space-x-2">
        <UiButton
          variant="ghost"
          size="sm"
          class="text-systemBlue-600 hover:text-systemBlue-700"
          @click="emit('view', patient)"
        >
          Ver
        </UiButton>
        <UiButton
          v-if="canEdit"
          variant="ghost"
          size="sm"
          class="text-systemGreen-700 hover:opacity-80"
          @click="emit('edit', patient)"
        >
          Editar
        </UiButton>
        <UiButton
          v-if="canDelete"
          variant="ghost"
          size="sm"
          class="text-systemRed-700 hover:opacity-80"
          @click="emit('delete', patient)"
        >
          Eliminar
        </UiButton>
      </div>
    </td>
  </tr>

  <!-- Mobile card row -->
  <div
    v-else
    data-patient-row
    class="bg-theme-surface-elevated rounded-2xl border border-hairline p-4"
  >
    <div class="flex items-start justify-between mb-3">
      <div class="flex items-center">
        <div class="h-12 w-12 rounded-xl bg-gradient-accent flex items-center justify-center mr-3">
          <span class="text-sm font-semibold text-white">{{ initials }}</span>
        </div>
        <div>
          <div class="text-lg font-semibold text-theme-primary">{{ displayName }}</div>
          <div
            class="text-sm text-theme-secondary"
            style="font-feature-settings: var(--font-features-tabular-nums)"
          >
            ID: {{ patient.id }}
          </div>
        </div>
      </div>
      <UiBadge :variant="statusVariant" size="sm" shape="pill">{{ statusLabel }}</UiBadge>
    </div>

    <div class="space-y-2 mb-4">
      <div class="flex items-center text-sm text-theme-secondary">
        <PhoneIcon class="w-4 h-4 mr-2 text-theme-secondary" aria-hidden="true" />
        {{ patient.phone }}
      </div>
      <div class="flex items-center text-sm text-theme-secondary">
        <EnvelopeIcon class="w-4 h-4 mr-2 text-theme-secondary" aria-hidden="true" />
        {{ patient.email }}
      </div>
      <div class="flex items-center text-sm text-theme-secondary">
        <CakeIcon class="w-4 h-4 mr-2 text-theme-secondary" aria-hidden="true" />
        {{ birthLabel }}
      </div>
      <div class="flex items-center text-sm text-theme-secondary">
        <ClockIcon class="w-4 h-4 mr-2 text-theme-secondary" aria-hidden="true" />
        <span class="font-medium text-theme-primary mr-1">Edad:</span>
        <span style="font-feature-settings: var(--font-features-tabular-nums)">{{ ageLabel }}</span>
      </div>
    </div>

    <div class="flex gap-2">
      <UiButton
        variant="ghost"
        size="sm"
        class="flex-1 text-systemBlue-600 hover:text-systemBlue-700"
        @click="emit('view', patient)"
      >
        Ver
      </UiButton>
      <UiButton
        v-if="canEdit"
        variant="ghost"
        size="sm"
        class="flex-1 text-systemGreen-700 hover:opacity-80"
        @click="emit('edit', patient)"
      >
        Editar
      </UiButton>
      <UiButton
        v-if="canDelete"
        variant="ghost"
        size="sm"
        class="flex-1 text-systemRed-700 hover:opacity-80"
        @click="emit('delete', patient)"
      >
        Eliminar
      </UiButton>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { CakeIcon, ClockIcon, EnvelopeIcon, PhoneIcon } from '@heroicons/vue/24/outline'
import UiBadge from '../../components/ui/Badge.vue'
import UiButton from '../../components/ui/Button.vue'
import { formatDateOnly } from '../../utils/formatters'

const props = defineProps({
  patient: { type: Object, required: true },
  variant: {
    type: String,
    default: 'table',
    validator: value => ['table', 'card'].includes(value)
  },
  canEdit: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false }
})

const emit = defineEmits(['view', 'edit', 'delete'])

const initials = computed(
  () => `${(props.patient.first_name || '').charAt(0)}${(props.patient.last_name || '').charAt(0)}`
)
const displayName = computed(() =>
  `${props.patient.first_name || ''} ${props.patient.last_name || ''}`.trim()
)
const birthLabel = computed(() => formatDateOnly(props.patient.birth_date) || 'N/A')
const ageLabel = computed(() => (props.patient.age != null ? `${props.patient.age} años` : '—'))
const statusVariant = computed(() => (props.patient.is_active ? 'success' : 'error'))
const statusLabel = computed(() => (props.patient.is_active ? 'Activo' : 'Inactivo'))
</script>
