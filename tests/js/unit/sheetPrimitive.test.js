import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Sheet from '../../../resources/js/components/ui/Sheet.vue'

// Issues #379 / #385 — the sheet never inherited the two fixes Modal already
// had: it always pointed `aria-labelledby` at the default-header id (which a
// consumer `#header` never renders) and armed the Trap before the panel ref
// existed on a `v-model` open. The navigation sheet and the AI analysis sheet
// are the two consumers.
const mounted = []

const track = wrapper => {
  mounted.push(wrapper)
  return wrapper
}

const mountSheet = options =>
  track(
    mount(Sheet, {
      props: { modelValue: false, title: 'Navegación', ...options?.props },
      slots: options?.slots,
      attachTo: document.body
    })
  )

const dialog = () => document.querySelector('[role="dialog"]')

const pressTab = (options = {}) => {
  document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, ...options }))
}

beforeEach(() => {
  document.body.style.overflow = ''
})

afterEach(() => {
  while (mounted.length > 0) mounted.pop().unmount()
  document.body.innerHTML = ''
  document.body.style.overflow = ''
  delete HTMLElement.prototype.offsetParent
})

describe('sheet accessible name (issue #379)', () => {
  it('names the dialog from the default header it renders', async () => {
    const sheet = mountSheet()
    await sheet.setProps({ modelValue: true })
    await flushPromises()

    const labelledBy = dialog().getAttribute('aria-labelledby')

    expect(labelledBy).toBeTruthy()
    expect(document.getElementById(labelledBy)?.textContent).toContain('Navegación')
    expect(dialog().getAttribute('aria-label')).toBeNull()
  })

  it('falls back to aria-label when a consumer replaces the header', async () => {
    const sheet = mountSheet({
      slots: {
        header: '<div data-nav-header><span>OdontoSuite</span></div>',
        default: '<nav><a href="/dashboard">Panel</a></nav>'
      }
    })
    await sheet.setProps({ modelValue: true })
    await flushPromises()

    expect(dialog().getAttribute('aria-labelledby')).toBeNull()
    expect(dialog().getAttribute('aria-label')).toBe('Navegación')
  })

  it('honours the labelledby a consumer points at its own heading', async () => {
    const sheet = mountSheet({
      props: { labelledby: 'nav-heading' },
      slots: { header: '<h2 id="nav-heading">Navegación</h2>' }
    })
    await sheet.setProps({ modelValue: true })
    await flushPromises()

    expect(dialog().getAttribute('aria-labelledby')).toBe('nav-heading')
    expect(dialog().getAttribute('aria-label')).toBeNull()
  })

  it('does not point at an id that never renders when there is no title', async () => {
    const sheet = mountSheet({ props: { title: undefined }, slots: { header: '<p>Panel</p>' } })
    await sheet.setProps({ modelValue: true })
    await flushPromises()

    expect(dialog().getAttribute('aria-labelledby')).toBeNull()
    expect(dialog().getAttribute('aria-label')).toBeNull()
  })
})

describe('sheet focus trap (issues #379 / #385)', () => {
  beforeEach(() => {
    Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
      configurable: true,
      get() {
        return this.parentElement
      }
    })
  })

  it('traps focus when it opens through v-model', async () => {
    const sheet = mountSheet({
      props: { closable: false },
      slots: { default: '<button data-inside>A</button><button data-inside>B</button>' }
    })
    await flushPromises()
    expect(document.activeElement).toBe(document.body)

    await sheet.setProps({ modelValue: true })
    await flushPromises()

    expect(dialog().contains(document.activeElement)).toBe(true)

    document.querySelectorAll('[data-inside]')[1].focus()
    pressTab()
    expect(document.querySelectorAll('[data-inside]')[0]).toBe(document.activeElement)

    pressTab({ shiftKey: true })
    expect(document.querySelectorAll('[data-inside]')[1]).toBe(document.activeElement)
  })
})
