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
import AppLayout from '../components/layout/AppLayout.vue'
import FloatingActionButton from '../components/layout/FloatingActionButton.vue'
import MobileMenu from '../components/layout/MobileMenu.vue'

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

    // Componentes de layout
    app.component('AppLayout', AppLayout)
    app.component('FloatingActionButton', FloatingActionButton)
    app.component('MobileMenu', MobileMenu)
  }
}



