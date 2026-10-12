import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'

// Shared visual vocabulary (patients redesign): UiSectionEmpty owns the
// dashboard's empty-state anatomy (canvas panel + hairline, dental motif
// behind, one icon, one title + one sentence) as a reusable ui primitive.
import UiSectionEmpty from '../../../resources/js/components/ui/UiSectionEmpty.vue'
import DashboardSectionEmpty from '../../../resources/js/modules/dashboard/DashboardSectionEmpty.vue'

const normalize = text => text.replace(/\s+/g, ' ').trim()

describe('UiSectionEmpty primitive', () => {
  it('renders the required title and the optional description on the canvas panel', () => {
    const wrapper = mount(UiSectionEmpty, {
      props: {
        title: 'Sin citas para hoy',
        description: 'Cuando registres citas, aparecerán aquí.'
      }
    })

    const panel = wrapper.get('[data-section-empty]')
    expect(panel.classes()).toContain('p-6')
    expect(panel.classes()).toContain('rounded-ios')
    expect(panel.attributes('style')).toContain('var(--color-canvas)')
    expect(panel.attributes('style')).toContain('var(--color-hairline)')

    const paragraphs = panel.findAll('p')
    expect(paragraphs).toHaveLength(2)
    expect(normalize(paragraphs[0].text())).toBe('Sin citas para hoy')
    expect(normalize(paragraphs[1].text())).toBe('Cuando registres citas, aparecerán aquí.')
  })

  it('drops the description line when the description prop is empty', () => {
    const wrapper = mount(UiSectionEmpty, {
      props: { title: 'Sin pendientes' }
    })

    const paragraphs = wrapper.findAll('p')
    expect(paragraphs).toHaveLength(1)
    expect(normalize(paragraphs[0].text())).toBe('Sin pendientes')
  })

  it('renders the default 8x8 stroke-1.5 label-tertiary icon when no icon slot is given', () => {
    const wrapper = mount(UiSectionEmpty, {
      props: { title: 'Sin citas para hoy' }
    })

    const icon = wrapper.find('svg')
    expect(icon.exists()).toBe(true)
    expect(icon.classes()).toContain('h-8')
    expect(icon.classes()).toContain('w-8')
    expect(icon.attributes('stroke-width')).toBe('1.5')
    expect(icon.attributes('style')).toContain('var(--color-label-tertiary-label)')
    expect(icon.attributes('aria-hidden')).toBe('true')
  })

  it('replaces the default icon when the icon slot is provided', () => {
    const wrapper = mount(UiSectionEmpty, {
      props: { title: 'Sin citas para hoy' },
      slots: {
        icon: '<svg data-custom-icon class="mx-auto h-8 w-8 mb-3" stroke-width="1.5" />'
      }
    })

    expect(wrapper.find('[data-custom-icon]').exists()).toBe(true)
    expect(wrapper.findAll('svg')).toHaveLength(1)
  })

  it('decorates the block with the dental motif unless motif is false', () => {
    const decorated = mount(UiSectionEmpty, {
      props: { title: 'Sin citas para hoy' }
    })

    const motif = decorated.find('[data-empty-motif]')
    expect(motif.exists()).toBe(true)
    expect(motif.attributes('aria-hidden')).toBe('true')
    expect(motif.findAll('img').length).toBeGreaterThanOrEqual(6)

    const clean = mount(UiSectionEmpty, {
      props: { title: 'Sin citas para hoy', motif: false }
    })

    expect(clean.find('[data-empty-motif]').exists()).toBe(false)
    expect(clean.find('.motif-scene').exists()).toBe(false)
  })

  it('renders the default slot inside the action wrapper only when content exists', () => {
    const empty = mount(UiSectionEmpty, {
      props: { title: 'Sin citas para hoy' }
    })
    expect(empty.find('.mt-4').exists()).toBe(false)

    const withAction = mount(UiSectionEmpty, {
      props: { title: 'Sin citas para hoy' },
      slots: { default: '<button data-empty-action>Ver agenda</button>' }
    })
    const action = withAction.get('[data-empty-action]')
    expect(action.classes()).toHaveLength(0)
    expect(action.element.parentElement.classList.contains('mt-4')).toBe(true)
  })

  it('keeps the dashboard wrapper anatomy identical while forwarding its props', () => {
    const wrapper = mount(DashboardSectionEmpty, {
      attrs: { 'data-state': 'empty-pending' },
      props: { title: 'Sin pendientes', description: 'Aún no hay pendientes.' }
    })

    const panel = wrapper.get('[data-section-empty]')
    expect(panel.attributes('data-state')).toBe('empty-pending')
    expect(panel.classes()).toContain('p-6')
    expect(panel.find('[data-empty-motif]').exists()).toBe(true)
    expect(panel.findAll('p')).toHaveLength(2)
    // No icon slot given: the primitive's default icon renders and no
    // empty action wrapper is emitted.
    expect(panel.find('svg').exists()).toBe(true)
    expect(panel.find('.mt-4').exists()).toBe(false)
  })

  it('forwards the dashboard wrapper icon and default slots to the primitive', () => {
    const wrapper = mount(DashboardSectionEmpty, {
      props: { title: 'Sin citas para hoy' },
      slots: {
        icon: '<svg data-custom-icon class="mx-auto h-8 w-8 mb-3" />',
        default: '<button data-empty-action>Ver agenda</button>'
      }
    })

    const panel = wrapper.get('[data-section-empty]')
    expect(panel.findAll('svg')).toHaveLength(1)
    expect(panel.find('[data-custom-icon]').exists()).toBe(true)
    expect(panel.find('[data-empty-action]').exists()).toBe(true)
    expect(panel.find('.mt-4').exists()).toBe(true)
    expect(panel.findAll('p')).toHaveLength(1)
  })
})
