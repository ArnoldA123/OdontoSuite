<template>
  <div v-if="siteType !== 'none'" class="space-y-3" data-procedure-site :data-site-type="siteType">
    <p v-if="loadFailed" class="text-xs text-systemRed-700" data-procedure-site-error>
      No pudimos cargar las piezas dentales. Revisa tu conexión e inténtalo de nuevo.
    </p>

    <div v-if="needsPiece" data-site-field="dental_piece_id">
      <UiSelect
        :model-value="pieceValue"
        :options="pieceOptions"
        label="Pieza dental"
        placeholder="Selecciona la pieza"
        :disabled="disabled"
        :error="errors.dental_piece_id"
        @change="onPieceChange"
      />
    </div>

    <div v-if="siteType === 'surface'" data-site-field="tooth_surface_id">
      <UiSelect
        :model-value="surfaceValue"
        :options="surfaceOptions"
        label="Superficie dental"
        :placeholder="pieceValue ? 'Selecciona la superficie' : 'Elige primero la pieza'"
        :disabled="disabled || !pieceValue"
        :error="errors.tooth_surface_id"
        @change="onSurfaceChange"
      />
    </div>

    <div v-if="siteType === 'quadrant'" data-site-field="site_code">
      <UiSelect
        :model-value="codeValue"
        :options="QUADRANTS"
        label="Cuadrante"
        placeholder="Selecciona el cuadrante"
        :disabled="disabled"
        :error="errors.site_code"
        @change="onCodeChange"
      />
    </div>

    <div v-if="siteType === 'arch'" data-site-field="site_code">
      <UiSelect
        :model-value="codeValue"
        :options="ARCHES"
        label="Arcada"
        placeholder="Selecciona la arcada"
        :disabled="disabled"
        :error="errors.site_code"
        @change="onCodeChange"
      />
    </div>

    <p v-if="summary" class="text-xs text-theme-secondary" data-procedure-site-summary>
      Sitio: {{ summary }}
    </p>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useApi } from '@/composables/useApi'
import UiSelect from '@/components/ui/Select.vue'
import { ARCHES, QUADRANTS, siteSummary } from '@/modules/procedure-catalog/procedureSite'

const props = defineProps({
  siteType: { type: String, default: 'none' },
  modelValue: {
    type: Object,
    default: () => ({ dental_piece_id: null, tooth_surface_id: null, site_code: null })
  },
  errors: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue'])

const { get } = useApi()

const pieces = ref([])
const loadFailed = ref(false)

const needsPiece = computed(() => props.siteType === 'tooth' || props.siteType === 'surface')

const idOf = value => (value === null || value === undefined || value === '' ? null : value)

const pieceValue = computed(() => idOf(props.modelValue?.dental_piece_id))
const surfaceValue = computed(() => idOf(props.modelValue?.tooth_surface_id))
const codeValue = computed(() => props.modelValue?.site_code ?? null)

const pieceOptions = computed(() =>
  pieces.value.map(piece => ({
    value: piece.id,
    label: [piece.fdi_number ? `#${piece.fdi_number}` : '', piece.name ?? '']
      .filter(Boolean)
      .join(' · ')
  }))
)

const selectedPiece = computed(
  () => pieces.value.find(piece => String(piece.id) === String(pieceValue.value)) ?? null
)

const surfaceOptions = computed(() =>
  (selectedPiece.value?.surfaces ?? []).map(surface => ({
    value: surface.id,
    label: `${surface.surface_name} (${surface.abbreviation})`
  }))
)

const summary = computed(() => siteSummary(props.modelValue, selectedPiece.value))

const loadPieces = async () => {
  try {
    const response = await get('/api/dental-pieces')
    pieces.value = Array.isArray(response?.data) ? response.data : []
    loadFailed.value = false
  } catch {
    pieces.value = []
    loadFailed.value = true
  }
}

// A surface is the whole address of its piece, so changing the piece drops the
// surface that belonged to the previous one.
const onPieceChange = value => {
  emit('update:modelValue', {
    dental_piece_id: idOf(value),
    tooth_surface_id: null,
    site_code: null
  })
}

const onSurfaceChange = value => {
  emit('update:modelValue', {
    dental_piece_id: idOf(pieceValue.value),
    tooth_surface_id: idOf(value),
    site_code: null
  })
}

const onCodeChange = value => {
  emit('update:modelValue', {
    dental_piece_id: null,
    tooth_surface_id: null,
    site_code: value || null
  })
}

onMounted(loadPieces)
</script>
