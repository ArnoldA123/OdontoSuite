<template>
  <div class="tooth-selector">
    <div class="mb-4">
      <label class="block text-sm font-medium text-theme-primary mb-2">
        {{ label }}
      </label>
      <div class="text-sm text-theme-secondary mb-3">
        Haz clic en las piezas dentales para seleccionarlas
      </div>
    </div>

    <!-- Arco dental superior -->
    <div class="mb-6">
      <div class="text-center text-sm font-medium text-theme-primary mb-2">
        Arco Superior
      </div>
      <div class="flex justify-center space-x-1">
        <div
          v-for="tooth in upperTeeth"
          :key="tooth.number"
          class="tooth tooth-upper"
          :class="[
          :class="
          [isSelected(tooth.number)
          ?
          'tooth-selected'
          :
          'tooth-unselected']"
          @click="toggleTooth(tooth.number)"
        >
          <div class="tooth-number">
            {{ tooth.number }}
          </div>
          <div class="tooth-name">
            {{ tooth.name }}
          </div>
        </div>
      </div>
    </div>

    <!-- Arco dental inferior -->
    <div class="mb-6">
      <div class="text-center text-sm font-medium text-theme-primary mb-2">
        Arco Inferior
      </div>
      <div class="flex justify-center space-x-1">
        <div
          v-for="tooth in lowerTeeth"
          :key="tooth.number"
          class="tooth tooth-lower"
          :class="[
          :class="
          [isSelected(tooth.number)
          ?
          'tooth-selected'
          :
          'tooth-unselected']"
          @click="toggleTooth(tooth.number)"
        >
          <div class="tooth-number">
            {{ tooth.number }}
          </div>
          <div class="tooth-name">
            {{ tooth.name }}
          </div>
        </div>
      </div>
    </div>

    <!-- Piezas seleccionadas -->
    <div v-if="selectedTeeth.length > 0" class="mt-4">
      <div class="text-sm font-medium text-theme-primary mb-2">
        Piezas seleccionadas:
      </div>
      <div class="flex flex-wrap gap-2">
        <span
          v-for="toothNumber in selectedTeeth"
          :key="toothNumber"
          class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-primary-100 text-primary-800"
        >
          {{ toothNumber }}
          <button
            class="ml-2 text-primary-600 hover:text-primary-800"
            @click="removeTooth(toothNumber)"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </span>
      </div>
    </div>

    <!-- Botones de acción -->
    <div class="flex justify-between mt-4">
      <button
        class="px-3 py-1 text-sm text-primary-600 hover:text-primary-800 transition-colors"
        @click="selectAll"
      >
        Seleccionar todas
      </button>
      <button
        class="px-3 py-1 text-sm text-theme-secondary hover:text-theme-primary transition-colors"
        @click="clearAll"
      >
        Limpiar selección
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  label: {
    type: String,
    default: 'Seleccionar piezas dentales'
  },
  maxSelections: {
    type: Number,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

// Estado reactivo
const selectedTeeth = ref([...props.modelValue])

// Datos de piezas dentales
const upperTeeth = [
  { number: '18', name: 'M3' },
  { number: '17', name: 'M2' },
  { number: '16', name: 'M1' },
  { number: '15', name: 'PM2' },
  { number: '14', name: 'PM1' },
  { number: '13', name: 'C' },
  { number: '12', name: 'I2' },
  { number: '11', name: 'I1' },
  { number: '21', name: 'I1' },
  { number: '22', name: 'I2' },
  { number: '23', name: 'C' },
  { number: '24', name: 'PM1' },
  { number: '25', name: 'PM2' },
  { number: '26', name: 'M1' },
  { number: '27', name: 'M2' },
  { number: '28', name: 'M3' }
]

const lowerTeeth = [
  { number: '48', name: 'M3' },
  { number: '47', name: 'M2' },
  { number: '46', name: 'M1' },
  { number: '45', name: 'PM2' },
  { number: '44', name: 'PM1' },
  { number: '43', name: 'C' },
  { number: '42', name: 'I2' },
  { number: '41', name: 'I1' },
  { number: '31', name: 'I1' },
  { number: '32', name: 'I2' },
  { number: '33', name: 'C' },
  { number: '34', name: 'PM1' },
  { number: '35', name: 'PM2' },
  { number: '36', name: 'M1' },
  { number: '37', name: 'M2' },
  { number: '38', name: 'M3' }
]

// Métodos
const toggleTooth = toothNumber => {
  if (isSelected(toothNumber)) {
    removeTooth(toothNumber)
  } else {
    addTooth(toothNumber)
  }
}

const addTooth = toothNumber => {
  if (props.maxSelections && selectedTeeth.value.length >= props.maxSelections) {
    return
  }

  if (!selectedTeeth.value.includes(toothNumber)) {
    selectedTeeth.value.push(toothNumber)
    emitChange()
  }
}

const removeTooth = toothNumber => {
  const index = selectedTeeth.value.indexOf(toothNumber)
  if (index > -1) {
    selectedTeeth.value.splice(index, 1)
    emitChange()
  }
}

const isSelected = toothNumber => {
  return selectedTeeth.value.includes(toothNumber)
}

const selectAll = () => {
  const allTeeth = [...upperTeeth, ...lowerTeeth].map(tooth => tooth.number)
  selectedTeeth.value = allTeeth
  emitChange()
}

const clearAll = () => {
  selectedTeeth.value = []
  emitChange()
}

const emitChange = () => {
  emit('update:modelValue', [...selectedTeeth.value])
  emit('change', [...selectedTeeth.value])
}

// Watchers
watch(
  () => props.modelValue,
  newValue => {
    selectedTeeth.value = [...newValue]
  }
)
</script>

<style scoped>
.tooth {
  @apply w-12 h-16 border-2 rounded-ios cursor-pointer transition-all duration-200 flex flex-col items-center justify-center text-xs font-medium;
}

.tooth-upper {
  @apply border-primary-300;
}

.tooth-lower {
  @apply border-green-300;
}

.tooth-selected {
  @apply bg-accent border-accent text-white;
}

.tooth-unselected {
  @apply bg-theme-surface-elevated hover:bg-theme-surface;
}

.tooth-upper.tooth-unselected:hover {
  @apply border-primary-400 bg-primary-50;
}

.tooth-lower.tooth-unselected:hover {
  @apply border-green-400 bg-green-50;
}

.tooth-number {
  @apply text-xs font-bold;
}

.tooth-name {
  @apply text-xs;
}
</style>
