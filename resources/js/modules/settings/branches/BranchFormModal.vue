<template>
  <UiModal
    model-value
    :title="isEdit ? 'Editar sucursal' : 'Nueva sucursal'"
    size="lg"
    @close="emit('close')"
  >
    <form class="space-y-4" @submit.prevent="onSubmit">
      <!-- Codigo y nombre -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Codigo
            <span class="text-red-500">*</span>
          </label>
          <UiInput
            v-model="form.code"
            :disabled="isEdit"
            placeholder="ej. SC-LIM-01"
            :error="errors.code"
            class="w-full"
          />
          <p v-if="isEdit" class="text-xs text-theme-secondary mt-1">
            El codigo no puede modificarse una vez creado.
          </p>
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Nombre
            <span class="text-red-500">*</span>
          </label>
          <UiInput
            v-model="form.name"
            placeholder="ej. Sede Central Lima"
            :error="errors.name"
            class="w-full"
          />
        </div>
      </div>

      <!-- Direccion -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-1">Direccion</label>
        <UiInput
          v-model="form.address"
          placeholder="Av. / Jr. / Calle, numero, distrito"
          :error="errors.address"
          class="w-full"
        />
      </div>

      <!-- Ciudad / Estado / Pais -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">
            Ciudad
            <span class="text-red-500">*</span>
          </label>
          <UiInput v-model="form.city" placeholder="Lima" :error="errors.city" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Departamento</label>
          <UiInput v-model="form.state" placeholder="Lima" :error="errors.state" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Pais</label>
          <UiInput v-model="form.country" placeholder="Peru" class="w-full" />
        </div>
      </div>

      <!-- Telefono / Email -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Telefono</label>
          <UiInput
            v-model="form.phone"
            placeholder="+51 1 426-0001"
            :error="errors.phone"
            class="w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-theme-primary mb-1">Email</label>
          <UiInput
            v-model="form.email"
            type="email"
            placeholder="sucursal@odontosuite.pe"
            :error="errors.email"
            class="w-full"
          />
        </div>
      </div>

      <!-- Descripcion -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-1">Descripcion</label>
        <UiTextarea
          v-model="form.description"
          :rows="2"
          placeholder="Notas internas sobre esta sede (opcional)"
          class="w-full"
        />
      </div>

      <!-- Estado (activo/inactivo) -->
      <div>
        <label class="block text-sm font-medium text-theme-primary mb-1">Estado</label>
        <select
          v-model="form.is_active"
          class="w-full px-3 py-2 border border-theme rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-accent bg-theme-surface-elevated text-theme-primary"
        >
          <option :value="true">Activa</option>
          <option :value="false">Inactiva</option>
        </select>
        <p class="text-xs text-theme-secondary mt-1">
          Las sucursales inactivas no aparecen en los dropdowns de abrir caja ni de seleccion de
          sede.
        </p>
      </div>
    </form>

    <template #footer>
      <div class="flex justify-end gap-3">
        <UiButton variant="secondary" :disabled="saving" @click="emit('close')">
Cancelar
</UiButton>
        <UiButton :disabled="saving" @click="onSubmit">
          {{ saving ? 'Guardando...' : isEdit ? 'Actualizar' : 'Crear' }}
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useBranches } from '../../../composables/useBranches'
import { useToast } from '../../../composables/useToast'
import UiModal from '../../../components/ui/Modal.vue'
import UiButton from '../../../components/ui/Button.vue'
import UiInput from '../../../components/ui/Input.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'

const props = defineProps({
  branch: { type: Object, default: null }
})

const emit = defineEmits(['close', 'saved'])

const { createBranch, updateBranch } = useBranches()
const toast = useToast()

const isEdit = computed(() => !!props.branch)

const getEmptyForm = () => ({
  code: '',
  name: '',
  address: '',
  city: '',
  state: '',
  country: 'Peru',
  phone: '',
  email: '',
  description: '',
  is_active: true
})

const form = ref(getEmptyForm())
const errors = ref({})
const saving = ref(false)

onMounted(() => {
  if (props.branch) {
    form.value = {
      code: props.branch.code,
      name: props.branch.name,
      address: props.branch.address || '',
      city: props.branch.city || '',
      state: props.branch.state || '',
      country: props.branch.country || 'Peru',
      phone: props.branch.phone || '',
      email: props.branch.email || '',
      description: props.branch.description || '',
      is_active: !!props.branch.is_active
    }
  }
})

const validate = () => {
  const e = {}
  if (!isEdit.value && !form.value.code?.trim()) e.code = 'Requerido'
  if (!form.value.name?.trim()) e.name = 'Requerido'
  if (!form.value.city?.trim()) e.city = 'Requerido'
  if (form.value.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
    e.email = 'Email invalido'
  }
  errors.value = e
  return Object.keys(e).length === 0
}

const onSubmit = async () => {
  if (!validate()) return
  saving.value = true
  try {
    let result
    if (isEdit.value) {
      // No enviamos code (es inmutable) ni timestamps
      const { code: _code, created_at: _ca, updated_at: _ua, id: _id, ...payload } = form.value
      result = await updateBranch(props.branch.id, payload)
    } else {
      result = await createBranch(form.value)
    }
    emit('saved', result)
  } catch (err) {
    if (err.response?.data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(err.response.data.errors).map(([k, v]) => [k, v[0]])
      )
    } else {
      toast.error(err.response?.data?.message || 'Error al guardar la sucursal')
    }
  } finally {
    saving.value = false
  }
}
</script>
