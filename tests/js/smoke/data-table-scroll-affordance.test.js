import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

// Issue #209 — at 390 px the table only shows its first column and the
// internal scroll was invisible: an admin could believe the table had one
// column and never reach the row actions. The primitive must advertise the
// hidden columns on the overflowing edge, keep the scroll internal, and stop
// advertising once the user reaches the end.
import DataTable from '../../../resources/js/components/ui/DataTable.vue'

const COLUMNS = [
  { key: 'name', label: 'Especialidad' },
  { key: 'procedures', label: 'Procedimientos' },
  { key: 'professionals', label: 'Profesionales' },
  { key: 'status', label: 'Estado' },
  { key: 'updated', label: 'Actualizado' }
]

const ROWS = [
  { id: 1, name: 'Ortodoncia', procedures: 8, professionals: 3, status: 'Activa', updated: 'ayer' },
  { id: 2, name: 'Endodoncia', procedures: 5, professionals: 2, status: 'Activa', updated: 'hoy' }
]

const mountTable = () =>
  mount(DataTable, {
    props: { columns: COLUMNS, data: ROWS },
    attachTo: document.body
  })

const viewport = wrapper => wrapper.find('[data-table-scroll-viewport]')

/**
 * jsdom performs no layout: scrollWidth/clientWidth stay 0 unless the test
 * declares the geometry. `scrollLeft` is writable so the scroll path can be
 * exercised the same way a touch drag would.
 */
const setGeometry = (wrapper, { scrollWidth, clientWidth, scrollLeft = 0 }) => {
  const { element } = viewport(wrapper)
  Object.defineProperty(element, 'scrollWidth', { configurable: true, value: scrollWidth })
  Object.defineProperty(element, 'clientWidth', { configurable: true, value: clientWidth })
  element.scrollLeft = scrollLeft
}

const remeasure = async () => {
  window.dispatchEvent(new Event('resize'))
  await nextTick()
  await nextTick()
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('DataTable horizontal scroll affordance (issue #209)', () => {
  it('keeps the horizontal scroll inside the table, never on the document', () => {
    const wrapper = mountTable()

    const scrollContainer = viewport(wrapper)
    expect(scrollContainer.exists()).toBe(true)

    // The primitive root only clips its own rounded corners; it must not open
    // a second scroll axis on the page.
    expect(wrapper.element.classList.contains('overflow-x-auto')).toBe(false)
    expect(wrapper.element.classList.contains('overflow-auto')).toBe(false)

    // The compiled `@apply` never reaches jsdom, so the guard reads the
    // primitive: one scroll axis, owned by the table viewport.
    const source = readFileSync(resolve('resources/js/components/ui/DataTable.vue'), 'utf8')
    expect(source.match(/overflow-x-auto/g)).toHaveLength(1)
    expect(source).toMatch(/\.data-table-wrapper\s*\{\s*@apply overflow-x-auto;\s*\}/)

    wrapper.unmount()
  })

  it('does not advertise a scroll it cannot perform', async () => {
    const wrapper = mountTable()
    setGeometry(wrapper, { scrollWidth: 320, clientWidth: 320 })
    await remeasure()

    expect(wrapper.find('[data-table-scroll-affordance]').exists()).toBe(false)
    expect(wrapper.find('[data-table-scroll]').attributes('data-table-scroll')).toBe('none')

    wrapper.unmount()
  })

  it('advertises the hidden columns and the right edge when the table overflows', async () => {
    const wrapper = mountTable()
    setGeometry(wrapper, { scrollWidth: 720, clientWidth: 320 })
    await remeasure()

    expect(wrapper.find('[data-table-scroll]').attributes('data-table-scroll')).toBe('right')

    const hint = wrapper.find('[data-table-scroll-affordance]')
    expect(hint.exists()).toBe(true)
    expect(hint.text()).toMatch(/desliza/i)
    expect(hint.text()).toMatch(/columnas/i)

    // The affordance is decorative: the table itself carries the data.
    expect(hint.attributes('aria-hidden')).toBe('true')
    expect(wrapper.find('[data-table-scroll-edge="right"]').exists()).toBe(true)
    expect(wrapper.find('[data-table-scroll-edge="left"]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('swaps the affordance to the left edge and drops the hint at the end', async () => {
    const wrapper = mountTable()
    setGeometry(wrapper, { scrollWidth: 720, clientWidth: 320 })
    await remeasure()

    const { element } = viewport(wrapper)
    element.scrollLeft = 400
    element.dispatchEvent(new Event('scroll'))
    await nextTick()

    expect(wrapper.find('[data-table-scroll]').attributes('data-table-scroll')).toBe('left')
    expect(wrapper.find('[data-table-scroll-edge="left"]').exists()).toBe(true)
    expect(wrapper.find('[data-table-scroll-edge="right"]').exists()).toBe(false)
    expect(wrapper.find('[data-table-scroll-affordance]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('re-measures when the column set changes without a data change', async () => {
    const wrapper = mountTable()
    setGeometry(wrapper, { scrollWidth: 320, clientWidth: 320 })
    await remeasure()

    expect(wrapper.find('[data-table-scroll-affordance]').exists()).toBe(false)

    Object.defineProperty(viewport(wrapper).element, 'scrollWidth', {
      configurable: true,
      value: 720
    })
    await wrapper.setProps({ columns: [...COLUMNS, { key: 'extra', label: 'Extra' }] })
    await nextTick()
    await nextTick()

    expect(wrapper.find('[data-table-scroll]').attributes('data-table-scroll')).toBe('right')
    expect(wrapper.find('[data-table-scroll-affordance]').exists()).toBe(true)

    wrapper.unmount()
  })

  it('re-measures when the table data changes', async () => {
    const wrapper = mountTable()
    setGeometry(wrapper, { scrollWidth: 720, clientWidth: 320 })
    await remeasure()

    Object.defineProperty(viewport(wrapper).element, 'scrollWidth', {
      configurable: true,
      value: 320
    })
    await wrapper.setProps({ data: [...ROWS, { id: 3, name: 'Periodoncia' }] })
    await nextTick()
    await nextTick()

    expect(wrapper.find('[data-table-scroll-affordance]').exists()).toBe(false)

    wrapper.unmount()
  })
})
