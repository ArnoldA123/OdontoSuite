<template>
  <UiModal model-value title="Registrar addendum" size="lg" @close="emit('close')">
    <form data-addendum-form class="space-y-5" @submit.prevent="submit">
      <div class="rounded-ios bg-systemGray-50 p-3" data-addendum-context>
        <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">
          {{ isEvolution ? 'Corrige una evolución' : 'Corrige la historia' }}
        </p>
        <p class="mt-1 text-sm text-theme-primary">{{ target.label }}</p>
      </div>

      <UiInput
        v-model="form.reason"
        data-addendum-field="reason"
        label="Motivo de la corrección"
        maxlength="255"
        required
        placeholder="Error de digitación, dato omitido, resultado posterior"
        :error="errors.reason"
      />

      <div data-addendum-field="content">
        <label for="addendum-content" class="mb-2 block text-sm font-medium text-theme-primary">
          Addendum
        </label>
        <UiTextarea
          id="addendum-content"
          v-model="form.content"
          :rows="6"
          placeholder="Texto de la corrección, fechado y firmado con su usuario"
        />
        <p v-if="errors.content" class="mt-1 text-xs text-error-500">
          {{ errors.content }}
        </p>
      </div>

      <p class="text-xs text-theme-secondary" data-addendum-notice>
        El registro original no se modifica: el addendum se agrega con su autor, fecha y motivo.
      </p>

      <div
        class="flex flex-wrap items-center justify-end gap-3 border-t border-[color:var(--color-hairline)] pt-4"
      >
        <UiButton type="button" variant="secondary" :disabled="saving" @click="emit('close')">
          Cancelar
        </UiButton>
        <UiButton type="submit" :loading="saving" data-addendum-submit>Registrar addendum</UiButton>
      </div>
    </form>
  </UiModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useMedicalRecords } from '@/composables/useMedicalRecords'
import { useToast } from '@/composables/useToast'
import UiButton from '@/components/ui/Button.vue'
import UiInput from '@/components/ui/Input.vue'
import UiModal from '@/components/ui/Modal.vue'
import UiTextarea from '@/components/ui/UiTextarea.vue'

const props = defineProps({
  /**
   * `{ kind: 'record' | 'evolution', id, label }`: the surface the correction
   * belongs to. The id is the record id or the evolution id, never both.
   */
  target: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['close', 'saved'])

const { addRecordAddendum, addEvolutionAddendum } = useMedicalRecords()
const toast = useToast()

const form = ref({ reason: '', content: '' })
const errors = ref({})
const saving = ref(false)

const isEvolution = computed(() => props.target?.kind === 'evolution')

const submit = async () => {
  if (saving.value) return

  const next = {}

  if (form.value.reason.trim() === '') {
    next.reason = 'Escribe el motivo de la corrección'
  }

  if (form.value.content.trim() === '') {
    next.content = 'Escribe el contenido del addendum'
  }

  errors.value = next

  if (Object.keys(next).length > 0) return

  saving.value = true

  try {
    const payload = {
      reason: form.value.reason.trim(),
      content: form.value.content.trim()
    }

    const addendum = isEvolution.value
      ? await addEvolutionAddendum(props.target.id, payload)
      : await addRecordAddendum(props.target.id, payload)

    toast.success('Addendum registrado')
    emit('saved', addendum)
  } catch (error) {
    const serverErrors = error?.response?.data?.errors

    if (serverErrors) {
      errors.value = Object.fromEntries(
        Object.entries(serverErrors).map(([key, value]) => [
          key,
          Array.isArray(value) ? value[0] : String(value)
        ])
      )
    } else {
      toast.error(error?.response?.data?.message || 'No pudimos registrar el addendum')
    }
  } finally {
    saving.value = false
  }
}
</script>
