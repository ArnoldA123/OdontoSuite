import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

// T6 — el ancho del contenido es una propiedad de la RUTA, no del layout.
//
// Medido por el usuario en una ventana de 2.559 px: `mx-auto max-w-7xl` deja
// 1.280 px de contenido y ~640 px en blanco por lado, así que la agenda del
// calendario pinta cinco columnas con barra horizontal mientras el resto de la
// pantalla está vacío. La ruta `/calendar` pasa a contenido fluido; cualquier
// otra página conserva exactamente la columna centrada de 1.280 px.
//
// Esta guarda mide las dos cosas que pueden divergir: lo que declara el layout
// montado (clases reales de los contenedores) y lo que declara la tabla de
// rutas de `app.js` (la lista de rutas fluidas del router).
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    setToken: () => {},
    normalizeError: () => ''
  })
}))
vi.mock('../../../resources/js/composables/useEcho', () => ({
  useEcho: () => ({
    echo: {},
    channel: () => ({}),
    privateChannel: () => ({}),
    connectionStatus: { value: 'connected' }
  })
}))
vi.mock('../../../resources/js/composables/useWebSocketNotifications', () => ({
  useWebSocketNotifications: () => ({})
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import AppLayout from '../../../resources/js/components/layout/AppLayout.vue'
import {
  FULL_WIDTH_ROUTE_PATHS,
  isFullWidthLayout
} from '../../../resources/js/router/layoutContent.js'

const APP_JS = readFileSync(resolve(process.cwd(), 'resources/js/app.js'), 'utf8')

const mountShellAt = async path => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push(path)
  await router.isReady()

  const wrapper = mount(AppLayout, {
    global: { plugins: [router, uiComponents] },
    slots: { default: '<div data-shell-page />' }
  })
  await flushPromises()
  return wrapper
}

const content = wrapper => wrapper.get('[data-app-content]')
const chromeContent = wrapper => wrapper.get('[data-app-chrome-content]')

const BOUNDED = ['mx-auto', 'max-w-7xl', 'px-4', 'sm:px-6', 'lg:px-8']
const FLUID = ['w-full', 'px-4', 'sm:px-6', 'lg:px-8']

beforeEach(() => {
  getMock.mockReset()
  getMock.mockResolvedValue({ data: [], meta: {} })
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role: 'administrador' }))
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('ancho del contenido por ruta (T6)', () => {
  it('la agenda del calendario ocupa el ancho completo con su padding lateral', async () => {
    const wrapper = await mountShellAt('/calendar')

    for (const node of [content(wrapper), chromeContent(wrapper)]) {
      expect(node.attributes('data-app-content-width')).toBe('full')
      expect(node.classes()).toEqual(expect.arrayContaining(FLUID))
      // El ancho completo no puede conservar el tope de 1.280 px ni el centrado
      // que le sobra al lado.
      expect(node.classes()).not.toContain('max-w-7xl')
      expect(node.classes()).not.toContain('mx-auto')
    }

    wrapper.unmount()
  })

  it('el resto de las superficies conserva la columna de 1.280 px', async () => {
    for (const path of ['/dashboard', '/patients', '/cash-register', '/medical-records/12']) {
      const wrapper = await mountShellAt(path)

      for (const node of [content(wrapper), chromeContent(wrapper)]) {
        expect(node.attributes('data-app-content-width'), path).toBe('bounded')
        expect(node.classes(), path).toEqual(expect.arrayContaining(BOUNDED))
        expect(node.classes(), path).not.toContain('w-full')
      }

      wrapper.unmount()
    }
  })

  it('la lista de rutas fluidas y la tabla de rutas de app.js no divergen', () => {
    // Sólo el calendario. Añadir una ruta acá sin declararla en `app.js` (o
    // declararla en `app.js` y olvidarla acá) falla con su nombre.
    expect(FULL_WIDTH_ROUTE_PATHS).toEqual(['/calendar'])

    const declaredPaths = [...APP_JS.matchAll(/path:\s*'([^']+)'/g)].map(match => match[1])
    expect(declaredPaths).toContain('/calendar')
    expect(declaredPaths.length).toBeGreaterThan(20)

    for (const path of FULL_WIDTH_ROUTE_PATHS) {
      expect(declaredPaths, `${path} must exist in app.js`).toContain(path)
      expect(isFullWidthLayout({ path }), path).toBe(true)
    }

    for (const path of declaredPaths) {
      if (FULL_WIDTH_ROUTE_PATHS.includes(path)) continue
      expect(isFullWidthLayout({ path }), `${path} must keep the centered column`).toBe(false)
    }
  })

  it('una ruta puede declarar el ancho en su meta; el nombre no basta', () => {
    expect(isFullWidthLayout({ path: '/patients', meta: { fullWidth: true } })).toBe(true)
    expect(isFullWidthLayout({ path: '/calendar/extra' })).toBe(true)
    expect(isFullWidthLayout({ path: '/calendario' })).toBe(false)
    expect(isFullWidthLayout({ name: 'calendar' })).toBe(false)
    expect(isFullWidthLayout(null)).toBe(false)
  })
})
