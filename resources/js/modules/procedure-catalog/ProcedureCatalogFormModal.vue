<template>
  <UiModal
    model-value
    :title="isEdit ? 'Editar procedimiento' : 'Nuevo procedimiento'"
    size="xl"
    @close="emit('close')"
  >
    <div class="space-y-6">
      <section data-form-group="identity">
        <h3 class="text-base font-semibold text-label">Identificación</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Código, nombre y especialidad con los que se reconocerá el procedimiento.
        </p>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <UiInput
              v-model="form.code"
              label="Código *"
              placeholder="ej. ORTO-BRACKETS"
              :disabled="isEdit"
              :error="errors.code"
              class="w-full"
            />
            <p v-if="isEdit" class="mt-1 text-xs text-theme-secondary">
              El código no puede modificarse.
            </p>
          </div>
          <UiInput v-model="form.name" label="Nombre *" :error="errors.name" class="w-full" />
        </div>
        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-theme-primary">Descripción</label>
          <UiTextarea v-model="form.description" :rows="3" class="w-full" />
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
          <div>
            <label class="mb-1 block text-sm font-medium text-theme-primary">Especialidad</label>
            <UiSelect
              v-model="form.specialty_id"
              :options="specialtyOptions"
              placeholder="Sin especialidad"
              label="Especialidad"
            />
          </div>
          <!-- #293 S1: what the procedure applies to (none|tooth|surface|quadrant|arch). -->
          <div data-field="site_type">
            <UiSelect
              v-model="form.site_type"
              :options="SITE_TYPE_OPTIONS"
              label="Aplicabilidad"
              placeholder="Sitio independiente"
            />
            <p class="mt-1 text-xs text-theme-secondary">
              Sobre lo que se realiza el procedimiento. La línea del plan o del presupuesto deberá
              declararlo.
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-theme-primary">Estado</label>
            <UiSelect
              v-model="form.is_active"
              :options="activeOptions"
              placeholder="Seleccionar estado"
              label="Estado"
            />
          </div>
        </div>
      </section>

      <!-- #293 S7: equivalencias con el nomenclador público (RM 550-2023/MINSA, SIS, otro). -->
      <section data-form-group="external-codes">
        <h3 class="text-base font-semibold text-label">Equivalencias</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Códigos del nomenclador público con los que se reconoce este procedimiento.
        </p>

        <div v-if="form.external_codes.length" class="mt-4 space-y-3">
          <div
            v-for="(externalCode, index) in form.external_codes"
            :key="index"
            data-external-code-row
            class="grid grid-cols-1 items-start gap-3 md:grid-cols-[180px_160px_1fr_auto]"
          >
            <UiSelect
              v-model="externalCode.system"
              :options="externalSystemOptions"
              label="Sistema"
            />
            <UiInput v-model="externalCode.code" label="Código" placeholder="ej. 997301" />
            <UiInput
              v-model="externalCode.description"
              label="Descripción"
              placeholder="Descripción del nomenclador (opcional)"
            />
            <UiButton
              type="button"
              variant="icon"
              size="sm"
              class="mt-7"
              aria-label="Quitar equivalencia"
              :data-external-code-remove="index"
              @click="removeExternalCode(index)"
            >
              <XMarkIcon class="h-4 w-4" aria-hidden="true" />
            </UiButton>
          </div>
        </div>

        <p v-else class="mt-4 text-sm text-theme-secondary" data-external-codes-empty>
          Este procedimiento no declara equivalencias.
        </p>

        <UiButton
          type="button"
          variant="secondary"
          size="sm"
          class="mt-3"
          data-external-code-add
          @click="addExternalCode"
        >
          <template #icon-left>
            <PlusIcon class="h-4 w-4" aria-hidden="true" />
          </template>
          Agregar equivalencia
        </UiButton>

        <ul
          v-if="externalCodeErrors.length"
          data-external-codes-errors
          class="mt-3 space-y-1 text-xs text-systemRed-600"
        >
          <li v-for="(message, index) in externalCodeErrors" :key="index">{{ message }}</li>
        </ul>
      </section>

      <!-- #295 S4/#434 S2: recipe of the stock inputs the procedure consumes. -->
      <section data-form-group="material-recipe">
        <h3 class="text-base font-semibold text-label">Receta de insumos</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Insumos del almacén que este procedimiento consume. El cierre de la atención descuenta
          estas cantidades del stock.
        </p>

        <p v-if="productsLoading" class="mt-3 text-xs text-theme-secondary">
          Cargando insumos del inventario…
        </p>

        <p
          v-else-if="productsError"
          data-material-recipe-load-error
          class="mt-3 flex flex-wrap items-center gap-2 text-xs text-systemRed-600"
        >
          <span>{{ productsError }}</span>
          <UiButton
            type="button"
            variant="ghost"
            size="sm"
            data-material-recipe-retry
            @click="loadProducts"
          >
            Reintentar
          </UiButton>
        </p>

        <div v-if="form.material_recipe.length" class="mt-4 space-y-3">
          <div
            v-for="(materialLine, index) in form.material_recipe"
            :key="index"
            :data-material-recipe-row="index"
            class="grid grid-cols-1 items-start gap-3 md:grid-cols-[1fr_160px_auto]"
          >
            <div :data-material-recipe-product="index">
              <UiSelect
                v-model="materialLine.product_id"
                :options="productOptions"
                label="Insumo"
                placeholder="Seleccionar insumo"
                searchable
                :error="errors[`material_recipe.${index}.product_id`]"
              />
              <p
                v-if="errors[`material_recipe.${index}.product_id`]"
                class="mt-1 text-xs text-systemRed-600"
              >
                {{ errors[`material_recipe.${index}.product_id`] }}
              </p>
            </div>
            <UiInput
              v-model.number="materialLine.quantity"
              label="Cantidad"
              type="number"
              step="0.001"
              min="0"
              :data-material-recipe-quantity="index"
              :error="errors[`material_recipe.${index}.quantity`]"
            />
            <UiButton
              type="button"
              variant="icon"
              size="sm"
              class="mt-7"
              aria-label="Quitar insumo"
              :data-material-recipe-remove="index"
              @click="removeMaterialLine(index)"
            >
              <XMarkIcon class="h-4 w-4" aria-hidden="true" />
            </UiButton>
          </div>
        </div>

        <p v-else class="mt-4 text-sm text-theme-secondary" data-material-recipe-empty>
          Este procedimiento no declara receta de insumos.
        </p>

        <UiButton
          type="button"
          variant="secondary"
          size="sm"
          class="mt-3"
          data-material-recipe-add
          @click="addMaterialLine"
        >
          <template #icon-left>
            <PlusIcon class="h-4 w-4" aria-hidden="true" />
          </template>
          Agregar insumo
        </UiButton>

        <ul
          v-if="materialRecipeErrors.length"
          data-material-recipe-errors
          class="mt-3 space-y-1 text-xs text-systemRed-600"
        >
          <li v-for="(message, index) in materialRecipeErrors" :key="index">{{ message }}</li>
        </ul>
      </section>

      <section data-form-group="pricing">
        <h3 class="text-base font-semibold text-label">Valor y duración</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Valores por defecto que se aplicarán al agendar o presupuestar.
        </p>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <UiInput
            v-model.number="form.default_cost"
            label="Costo por defecto (S/) *"
            type="number"
            step="0.01"
            min="0"
            :error="errors.default_cost"
            class="w-full"
          />
          <UiInput
            v-model.number="form.default_duration_minutes"
            label="Duración por defecto (min) *"
            type="number"
            step="5"
            min="5"
            max="480"
            :error="errors.default_duration_minutes"
            class="w-full"
          />
        </div>
      </section>

      <section data-form-group="clinical">
        <h3 class="text-base font-semibold text-label">Preparación clínica</h3>
        <p class="mt-1 text-sm text-theme-secondary">
          Requisitos, materiales y cuidados que el equipo clínico debe conocer.
        </p>
        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-theme-primary">
            Requisitos previos
          </label>
          <UiTextarea v-model="form.requirements" :rows="2" class="w-full" />
        </div>
        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-theme-primary">
            Materiales necesarios
          </label>
          <UiTextarea
            v-model="form.materials_needed"
            :rows="2"
            placeholder="Separar por comas. Ej: resina, adhesivo, matriz"
            class="w-full"
          />
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-6">
          <label class="flex items-center gap-2 text-sm text-theme-primary">
            <input
              v-model="form.requires_anesthesia"
              type="checkbox"
              class="h-4 w-4 rounded border-theme text-accent focus:ring-accent"
            />
            Requiere anestesia
          </label>
          <label class="flex items-center gap-2 text-sm text-theme-primary">
            <input
              v-model="form.requires_radiographs"
              type="checkbox"
              class="h-4 w-4 rounded border-theme text-accent focus:ring-accent"
            />
            Requiere radiografías
          </label>
        </div>
        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-theme-primary">
            Contraindicaciones
          </label>
          <UiTextarea v-model="form.contraindications" :rows="2" class="w-full" />
        </div>
        <div class="mt-4">
          <label class="mb-1 block text-sm font-medium text-theme-primary">
            Cuidados post-procedimiento
          </label>
          <UiTextarea v-model="form.post_procedure_care" :rows="2" class="w-full" />
        </div>
      </section>

      <p v-if="formError" class="text-sm text-systemRed-600" data-form-error>{{ formError }}</p>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3">
        <UiButton variant="secondary" :disabled="saving" @click="emit('close')">Cancelar</UiButton>
        <UiButton :disabled="saving" :loading="saving" data-form-submit @click="onSubmit">
          {{ isEdit ? 'Actualizar' : 'Crear' }}
        </UiButton>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { XMarkIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { useApi } from '../../composables/useApi'
import { useProcedureCatalog } from '../../composables/useProcedureCatalog'
import { useToast } from '../../composables/useToast'
import { SITE_TYPE_OPTIONS } from './procedureSite'
import UiModal from '../../components/ui/Modal.vue'
import UiButton from '../../components/ui/Button.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import UiTextarea from '../../components/ui/UiTextarea.vue'

const props = defineProps({
  procedure: { type: Object, default: null },
  specialties: { type: Array, default: () => [] }
})

const emit = defineEmits(['close', 'saved'])

const { get } = useApi()
const { createProcedure, updateProcedure } = useProcedureCatalog()
const toast = useToast()

const isEdit = computed(() => Boolean(props.procedure))
const saving = ref(false)
const errors = ref({})
const formError = ref('')

// #295 S4: the recipe consumes stock; the editor offers the inventory catalogue
// and keeps the products the recipe already declares, even when the page does
// not include them (inactive or outside the first page).
const products = ref([])
const productsLoading = ref(false)
const productsError = ref('')
const recipeSeeds = ref([])

const materialRecipeErrors = computed(() =>
  Object.entries(errors.value)
    .filter(([field]) => /^material_recipe(\.\d+)?$/.test(field))
    .map(([, message]) => message)
)

const productOptionLabel = product => {
  const base = product.code ? `${product.name} — ${product.code}` : product.name
  return product.unit ? `${base} (${product.unit})` : base
}

const productOptions = computed(() => {
  const seen = new Set()
  const options = []
  const push = product => {
    const id = Number(product.id)
    if (seen.has(id)) return
    seen.add(id)
    options.push({ value: id, label: productOptionLabel(product) })
  }

  products.value.forEach(push)
  recipeSeeds.value.forEach(push)
  return options
})

const loadProducts = async () => {
  productsLoading.value = true
  productsError.value = ''
  try {
    const response = await get('/api/inventory/products', {
      params: { per_page: 100, is_active: 1 }
    })
    products.value = Array.isArray(response?.data) ? response.data : []
  } catch (error) {
    products.value = []
    productsError.value =
      error?.response?.data?.message || 'No se pudieron cargar los insumos del inventario.'
  } finally {
    productsLoading.value = false
  }
}

const addMaterialLine = () => {
  form.value.material_recipe.push({ product_id: null, quantity: null })
}

const removeMaterialLine = index => {
  form.value.material_recipe.splice(index, 1)
}

const activeOptions = [
  { value: true, label: 'Activo' },
  { value: false, label: 'Inactivo' }
]

// #293 S7: the known nomenclators plus any other financiador (the API accepts
// any slug; `otro` is the generic one the editor offers).
const externalSystemOptions = [
  { value: 'minsa_rm550_2023', label: 'MINSA RM 550-2023' },
  { value: 'sis', label: 'SIS' },
  { value: 'otro', label: 'Otro' }
]

const externalCodeErrors = computed(() =>
  Object.entries(errors.value)
    .filter(([field]) => field === 'external_codes' || field.startsWith('external_codes.'))
    .map(([, message]) => message)
)

const addExternalCode = () => {
  form.value.external_codes.push({
    system: 'minsa_rm550_2023',
    code: '',
    description: ''
  })
}

const removeExternalCode = index => {
  form.value.external_codes.splice(index, 1)
}

const specialtyOptions = computed(() => [
  { value: null, label: 'Sin especialidad' },
  ...props.specialties.map(specialty => ({ value: specialty.id, label: specialty.name }))
])

const getEmptyForm = () => ({
  code: '',
  name: '',
  description: '',
  specialty_id: null,
  site_type: 'none',
  default_cost: 0,
  default_duration_minutes: 30,
  requirements: '',
  materials_needed: '',
  requires_anesthesia: false,
  requires_radiographs: false,
  contraindications: '',
  post_procedure_care: '',
  is_active: true,
  external_codes: [],
  material_recipe: []
})

const form = ref(getEmptyForm())

onMounted(() => {
  loadProducts()
  if (!props.procedure) return
  const recipe = props.procedure.material_recipe ?? []
  recipeSeeds.value = recipe
    .filter(line => line.product_id !== null && line.product_id !== undefined)
    .map(line => ({
      id: Number(line.product_id),
      name: line.product_name || `Insumo #${line.product_id}`,
      code: null,
      unit: line.unit || ''
    }))
  form.value = {
    code: props.procedure.code,
    name: props.procedure.name,
    description: props.procedure.description || '',
    specialty_id: props.procedure.specialty_id ?? null,
    site_type: props.procedure.site_type ?? 'none',
    default_cost: Number(props.procedure.default_cost ?? 0),
    default_duration_minutes: Number(props.procedure.default_duration_minutes ?? 30),
    requirements: props.procedure.requirements || '',
    materials_needed: props.procedure.materials_needed || '',
    requires_anesthesia: Boolean(props.procedure.requires_anesthesia),
    requires_radiographs: Boolean(props.procedure.requires_radiographs),
    contraindications: props.procedure.contraindications || '',
    post_procedure_care: props.procedure.post_procedure_care || '',
    is_active: Boolean(props.procedure.is_active),
    external_codes: (props.procedure.external_codes ?? []).map(externalCode => ({
      system: externalCode.system,
      code: externalCode.code ?? '',
      description: externalCode.description ?? ''
    })),
    material_recipe: recipe.map(line => ({
      product_id: line.product_id ?? null,
      quantity: line.quantity === null || line.quantity === undefined ? null : Number(line.quantity)
    }))
  }
})

const validate = () => {
  const nextErrors = {}
  if (!isEdit.value && !form.value.code?.trim()) nextErrors.code = 'Requerido'
  if (!form.value.name?.trim()) nextErrors.name = 'Requerido'
  if (
    form.value.default_cost === null ||
    form.value.default_cost === undefined ||
    form.value.default_cost < 0
  ) {
    nextErrors.default_cost = 'Debe ser mayor o igual a 0'
  }
  if (!form.value.default_duration_minutes || form.value.default_duration_minutes < 5) {
    nextErrors.default_duration_minutes = 'Mínimo 5 min'
  }
  form.value.material_recipe.forEach((materialLine, index) => {
    if (!materialLine.product_id) {
      nextErrors[`material_recipe.${index}.product_id`] = 'Selecciona el insumo'
    }
    if (!(Number(materialLine.quantity) > 0)) {
      nextErrors[`material_recipe.${index}.quantity`] = 'La cantidad debe ser mayor a cero.'
    }
  })
  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

const onSubmit = async () => {
  if (saving.value) return
  formError.value = ''
  if (!validate()) return
  saving.value = true
  try {
    const payload = {
      ...form.value,
      // #293 S7: the editor replaces the whole set, so a blank row would be a
      // half-declared equivalence; empty rows never travel.
      external_codes: form.value.external_codes
        .filter(externalCode => (externalCode.code || '').trim() !== '')
        .map(externalCode => ({
          system: externalCode.system,
          code: externalCode.code.trim(),
          description: (externalCode.description || '').trim() || null
        })),
      // #295 S4: the list replaces the whole recipe; `[]` clears it.
      material_recipe: form.value.material_recipe.map(materialLine => ({
        product_id: materialLine.product_id,
        quantity: Number(materialLine.quantity)
      }))
    }
    let result
    if (isEdit.value) {
      delete payload.code
      result = await updateProcedure(props.procedure.id, payload)
    } else {
      result = await createProcedure(payload)
    }
    emit('saved', result)
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = Object.fromEntries(
        Object.entries(error.response.data.errors).map(([field, messages]) => [field, messages[0]])
      )
      formError.value = 'Revisa los campos marcados.'
    } else {
      formError.value = error.response?.data?.message || 'No se pudo guardar el procedimiento'
      toast.error(formError.value)
    }
  } finally {
    saving.value = false
  }
}
</script>
