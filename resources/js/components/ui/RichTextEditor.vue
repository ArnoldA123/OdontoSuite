<template>
  <div class="rich-text-editor">
    <div class="editor-toolbar">
      <div class="toolbar-group">
        <button
          type="button"
          :class="['toolbar-btn', isActive('bold') ? 'active' : '']"
          @click="execCommand('bold')"
          title="Negrita"
        >
          <BoldIcon class="w-4 h-4" />
        </button>
        <button
          type="button"
          :class="['toolbar-btn', isActive('italic') ? 'active' : '']"
          @click="execCommand('italic')"
          title="Cursiva"
        >
          <ItalicIcon class="w-4 h-4" />
        </button>
        <button
          type="button"
          :class="['toolbar-btn', isActive('underline') ? 'active' : '']"
          @click="execCommand('underline')"
          title="Subrayado"
        >
          <UnderlineIcon class="w-4 h-4" />
        </button>
      </div>

      <div class="toolbar-group">
        <button
          type="button"
          :class="['toolbar-btn', isActive('insertUnorderedList') ? 'active' : '']"
          @click="execCommand('insertUnorderedList')"
          title="Lista con viñetas"
        >
          <ListBulletIcon class="w-4 h-4" />
        </button>
        <button
          type="button"
          :class="['toolbar-btn', isActive('insertOrderedList') ? 'active' : '']"
          @click="execCommand('insertOrderedList')"
          title="Lista numerada"
        >
          <ListBulletIcon class="w-4 h-4" />
        </button>
      </div>

      <div class="toolbar-group">
        <select @change="changeFontSize" class="toolbar-select" title="Tamaño de fuente">
          <option value="">
Tamaño
</option>
          <option value="1">
Muy pequeño
</option>
          <option value="2">
Pequeño
</option>
          <option value="3">
Normal
</option>
          <option value="4">
Grande
</option>
          <option value="5">
Muy grande
</option>
          <option value="6">
Enorme
</option>
        </select>
      </div>

      <div class="toolbar-group">
        <button
          type="button"
          :class="['toolbar-btn', isActive('justifyLeft') ? 'active' : '']"
          @click="execCommand('justifyLeft')"
          title="Alinear izquierda"
        >
          ←
        </button>
        <button
          type="button"
          :class="['toolbar-btn', isActive('justifyCenter') ? 'active' : '']"
          @click="execCommand('justifyCenter')"
          title="Centrar"
        >
          ↔
        </button>
        <button
          type="button"
          :class="['toolbar-btn', isActive('justifyRight') ? 'active' : '']"
          @click="execCommand('justifyRight')"
          title="Alinear derecha"
        >
          →
        </button>
      </div>

      <div class="toolbar-group">
        <button type="button" @click="execCommand('undo')" class="toolbar-btn" title="Deshacer">
          <ArrowUturnLeftIcon class="w-4 h-4" />
        </button>
        <button type="button" @click="execCommand('redo')" class="toolbar-btn" title="Rehacer">
          <ArrowUturnRightIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <div
      ref="editor"
      class="editor-content" :class="[
        disabled ? 'disabled' : ''
      ]"
      :contenteditable="!disabled"
      @input="handleInput"
      @paste="handlePaste"
      @keydown="handleKeydown"
      v-html="modelValue"
    />

    <div v-if="showCharCount" class="editor-footer">
      <div class="text-sm text-theme-secondary">
        {{ charCount }} caracteres
        <span v-if="maxLength">/ {{ maxLength }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import {
  BoldIcon,
  ItalicIcon,
  UnderlineIcon,
  ListBulletIcon,
  ArrowUturnLeftIcon,
  ArrowUturnRightIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Escribe aquí...'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  maxLength: {
    type: Number,
    default: null
  },
  showCharCount: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

// Estado reactivo
const editor = ref(null)
const isComposing = ref(false)

// Computed
const charCount = computed(() => {
  return props.modelValue.length
})

const isOverLimit = computed(() => {
  return props.maxLength && charCount.value > props.maxLength
})

// Métodos
const execCommand = (command, value = null) => {
  if (props.disabled) return

  document.execCommand(command, false, value)
  editor.value?.focus()
  emitChange()
}

const isActive = command => {
  if (props.disabled) return false
  return document.queryCommandState(command)
}

const changeFontSize = event => {
  const size = event.target.value
  if (size) {
    execCommand('fontSize', size)
  }
}

const handleInput = () => {
  if (isComposing.value) return

  const content = editor.value?.innerHTML || ''
  emitChange()
}

const handlePaste = event => {
  if (props.disabled) {
    event.preventDefault()
    return
  }

  // Limpiar formato al pegar
  event.preventDefault()
  const text = event.clipboardData.getData('text/plain')
  document.execCommand('insertText', false, text)
}

const handleKeydown = event => {
  if (props.disabled) {
    event.preventDefault()
    return
  }

  // Prevenir entrada si se excede el límite
  if (props.maxLength && charCount.value >= props.maxLength && !isControlKey(event)) {
    event.preventDefault()
  }
}

const isControlKey = event => {
  return (
    event.ctrlKey ||
    event.metaKey ||
    event.key === 'Backspace' ||
    event.key === 'Delete' ||
    event.key === 'ArrowLeft' || event.key === 'ArrowRight' || event.key === 'ArrowUp' || event.key === 'ArrowDown'
}

const emitChange = () => {
  const content = editor.value?.innerHTML || ''
  emit('update:modelValue', content)
  emit('change', content)
}

const focus = () => {
  editor.value?.focus()
}

const blur = () => {
  editor.value?.blur()
}

const clear = () => {
  if (editor.value) {
    editor.value.innerHTML = ''
    emitChange()
  }
}

// Watchers
watch(
  () => props.modelValue,
  newValue => {
    if (editor.value && editor.value.innerHTML !== newValue) {
      editor.value.innerHTML = newValue
    }
  }
)

// Lifecycle
onMounted(() => {
  if (editor.value) {
    editor.value.innerHTML = props.modelValue
  }
})

// Expose methods
defineExpose({
  focus,
  blur,
  clear
})
</script>

<style scoped>
.rich-text-editor {
  @apply border border-theme rounded-ios overflow-hidden;
}

.editor-toolbar {
  @apply flex flex-wrap items-center gap-1 p-2 bg-theme-surface border-b border-theme;
}

.toolbar-group {
  @apply flex items-center gap-1;
}

.toolbar-btn {
  @apply p-2 text-theme-secondary hover:text-theme-primary hover:bg-theme-surface rounded transition-colors;
}

.toolbar-btn.active {
  @apply text-primary-600 bg-primary-100;
}

.toolbar-select {
  @apply px-2 py-1 text-sm border border-theme rounded bg-theme-surface-elevated;
}

.editor-content {
  @apply min-h-32 p-3 text-theme-primary focus:outline-none;
}

.editor-content:empty:before {
  content: attr(data-placeholder);
  @apply text-theme-secondary;
}

.editor-content.disabled {
  @apply bg-theme-surface cursor-not-allowed;
}

.editor-footer {
  @apply px-3 py-2 bg-theme-surface border-t border-theme;
}

/* Estilos para el contenido del editor */
.editor-content :deep(strong) {
  @apply font-bold;
}

.editor-content :deep(em) {
  @apply italic;
}

.editor-content :deep(u) {
  @apply underline;
}

.editor-content :deep(ul) {
  @apply list-disc list-inside;
}

.editor-content :deep(ol) {
  @apply list-decimal list-inside;
}

.editor-content :deep(li) {
  @apply ml-4;
}
</style>
