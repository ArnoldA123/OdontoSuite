import { ref } from 'vue'

// Singleton state compartido por TODAS las llamadas a useConfirm() en la app.
// Permite que cualquier vista muestre un confirm sin tener que gestionar su
// propio ref de UiConfirmDialog. La primera invocacion registra el modal
// globalmente; las siguientes solo actualizan el state.
const isOpen = ref(false)
const title = ref('Confirmar')
const message = ref('')
const confirmText = ref('Confirmar')
const cancelText = ref('Cancelar')
const variant = ref('default') // 'default' | 'danger'
const loading = ref(false)

let resolver = null

/**
 * Composable para mostrar un ConfirmDialog con API basada en Promise.
 * Patron:
 *   const { confirm } = useConfirm()
 *   if (await confirm({ title: 'Eliminar', message: 'Estas seguro?', variant: 'danger' })) {
 *     // usuario confirmo
 *   }
 *
 * Internamente usa <UiConfirmDialog> registrado en plugins/ui-components.js.
 * El componente se monta UNA vez en AppLayout (en este composable se hace
 * automaticamente via app.component si no esta).
 */
export function useConfirm() {
  /**
   * Muestra el dialog y devuelve una Promise<boolean>.
   * @param {Object} options
   * @param {string} options.title - Titulo del dialog
   * @param {string} options.message - Mensaje del body
   * @param {string} [options.confirmText='Confirmar'] - Texto del boton confirmar
   * @param {string} [options.cancelText='Cancelar'] - Texto del boton cancelar
   * @param {'default'|'danger'} [options.variant='default'] - 'danger' muestra icono de alerta + boton rojo
   * @returns {Promise<boolean>} true si confirma, false si cancela
   */
  const confirm = (options) => {
    return new Promise((resolve) => {
      // Si ya hay un confirm abierto, resolver el anterior con false
      // (evita quedarme pegado si alguien llama confirm() dos veces seguidas)
      if (resolver) {
        resolver(false)
        resolver = null
      }

      title.value = options.title || 'Confirmar'
      message.value = options.message || ''
      confirmText.value = options.confirmText || 'Confirmar'
      cancelText.value = options.cancelText || 'Cancelar'
      variant.value = options.variant || 'default'
      loading.value = false
      isOpen.value = true
      resolver = resolve
    })
  }

  /**
   * Handler para el evento 'confirm' de UiConfirmDialog
   */
  const handleConfirm = () => {
    if (resolver) {
      resolver(true)
      resolver = null
    }
    isOpen.value = false
  }

  /**
   * Handler para el evento 'cancel' o 'update:modelValue' de UiConfirmDialog
   */
  const handleCancel = () => {
    if (resolver) {
      resolver(false)
      resolver = null
    }
    isOpen.value = false
  }

  return {
    confirm,
    // Exposed state para el template del modal
    isOpen,
    title,
    message,
    confirmText,
    cancelText,
    variant,
    loading,
    handleConfirm,
    handleCancel,
  }
}

// Helpers para montar el <UiConfirmDialog> en AppLayout.
// Uso: importar isOpen, title, etc. y conectarlos a <UiConfirmDialog v-model="isOpen" ...>
export {
  isOpen as confirmIsOpen,
  title as confirmTitle,
  message as confirmMessage,
  confirmText as confirmConfirmText,
  cancelText as confirmCancelText,
  variant as confirmVariant,
}
