import Button from '../components/ui/Button.vue'
import Input from '../components/ui/Input.vue'
import Card from '../components/ui/Card.vue'
import RadioGroup from '../components/ui/RadioGroup.vue'
import Select from '../components/ui/Select.vue'
import Badge from '../components/ui/Badge.vue'
import Avatar from '../components/ui/Avatar.vue'
import Modal from '../components/ui/Modal.vue'
import Sheet from '../components/ui/Sheet.vue'
import Toast from '../components/ui/Toast.vue'
import LoadingSpinner from '../components/ui/LoadingSpinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Skeleton from '../components/ui/Skeleton.vue'
import Breadcrumbs from '../components/ui/Breadcrumbs.vue'
import StatusPill from '../components/ui/StatusPill.vue'
import ProgressBar from '../components/ui/ProgressBar.vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import FilterBar from '../components/ui/FilterBar.vue'
import AppLayout from '../components/layout/AppLayout.vue'
import FloatingActionButton from '../components/layout/FloatingActionButton.vue'
import MobileMenu from '../components/layout/MobileMenu.vue'
import PageHeader from '../components/layout/PageHeader.vue'

export default {
  install(app) {
    // Componentes UI base
    app.component('UiButton', Button)
    app.component('UiInput', Input)
    app.component('UiCard', Card)
    app.component('UiRadioGroup', RadioGroup)
    app.component('UiSelect', Select)
    app.component('UiBadge', Badge)
    app.component('UiAvatar', Avatar)
    app.component('UiModal', Modal)
    app.component('UiSheet', Sheet)
    app.component('UiToast', Toast)
    app.component('LoadingSpinner', LoadingSpinner)
    app.component('EmptyState', EmptyState)
    app.component('UiSkeleton', Skeleton)
    app.component('UiBreadcrumbs', Breadcrumbs)
    app.component('UiStatusPill', StatusPill)
    app.component('UiProgressBar', ProgressBar)
    app.component('UiConfirmDialog', ConfirmDialog)
    app.component('UiFilterBar', FilterBar)

    // Componentes de layout
    app.component('AppLayout', AppLayout)
    app.component('FloatingActionButton', FloatingActionButton)
    app.component('MobileMenu', MobileMenu)
    app.component('PageHeader', PageHeader)
  }
}



