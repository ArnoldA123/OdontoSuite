import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { InboxIcon } from '@heroicons/vue/24/outline'
import EmptyState from '../../../resources/js/components/ui/EmptyState.vue'

// #205 — heroicons v2 ships functional components (functions), which Vue
// accepts in `<component :is>` but rejects against a `[String, Object]` prop
// type. Every page that passes an icon to UiEmptyState mounted with a
// "[Vue warn]: Invalid prop: type check failed for prop \"icon\"" in the
// console. This smoke mounts the primitive with a real heroicons component and
// fails if that warning comes back.

let warnSpy = null

afterEach(() => {
  warnSpy?.mockRestore()
  warnSpy = null
})

const mountWithIcon = () =>
  mount(EmptyState, {
    props: {
      icon: InboxIcon,
      title: 'Sin datos',
      description: 'No hay elementos para mostrar'
    }
  })

const iconTypeWarnings = () =>
  warnSpy.mock.calls
    .map(args => String(args[0]))
    .filter(message => message.includes('Invalid prop: type check failed for prop "icon"'))

describe('ui empty state icon prop', () => {
  it('accepts a heroicons functional component without a prop type warning', () => {
    warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

    const wrapper = mountWithIcon()

    expect(iconTypeWarnings()).toEqual([])
    expect(wrapper.find('svg').exists()).toBe(true)

    wrapper.unmount()
  })

  it('still accepts the string icon contract', () => {
    warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

    const wrapper = mount(EmptyState, {
      props: { icon: 'inbox', title: 'Sin datos' }
    })

    expect(iconTypeWarnings()).toEqual([])

    wrapper.unmount()
  })
})
