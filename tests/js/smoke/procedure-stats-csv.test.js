import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'

// procedure-stats-ui-redesign / T3 — S6 export CSV: exact RFC 4180 output,
// exact filename, the applied period (never the filter draft) and the
// disabled state when the period has no usage data.
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: getMock,
    post: async () => ({ data: {} }),
    put: async () => ({ data: {} }),
    patch: async () => ({ data: {} }),
    delete: async () => ({ data: {} }),
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
// Canvas rendering is not what this file exercises; a stub keeps the export
// assertions free of Chart.js lifecycle noise.
vi.mock('chart.js', () => {
  class ChartStub {
    destroy() {}
    static register() {}
  }
  return { Chart: ChartStub, registerables: [] }
})

import uiComponents from '../../../resources/js/plugins/ui-components'
import Page from '../../../resources/js/modules/procedure-catalog/ProcedureStatsPage.vue'
import {
  buildProcedureStatsCsv,
  buildProcedureStatsFilename
} from '../../../resources/js/modules/procedure-catalog/procedureStatsCsv'

const payload = {
  period: { from: '2026-07-22', to: '2026-10-19' },
  catalog: { total: 42, active: 30, inactive: 12 },
  top_procedures: [
    {
      procedure_id: 7,
      code: '=HYPERLINK("x")',
      name: 'Blanqueamiento, "premium"',
      usage_count: 3,
      total_quantity: 5,
      total_revenue: 750.5
    }
  ],
  by_specialty: [
    {
      specialty_id: 1,
      specialty_code: 'ODON',
      specialty_name: 'Odontología general',
      usage_count: 3,
      total_revenue: 750.5
    }
  ]
}

const emptyPayload = {
  ...payload,
  top_procedures: [],
  by_specialty: []
}

const expectedCsv = [
  '\uFEFFseccion,codigo,nombre,usos,cantidad,ingresos',
  'procedimiento,"\'=HYPERLINK(""x"")","Blanqueamiento, ""premium""",3,5,750.50',
  'especialidad,ODON,Odontología general,3,,750.50',
  ''
].join('\r\n')

const signInAs = role => {
  localStorage.setItem('auth_token', 'test-token')
  localStorage.setItem('user', JSON.stringify({ id: 1, name: 'Ana Admin', role }))
}

const mountPage = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }]
  })
  await router.push('/procedure-stats')
  await router.isReady()

  const wrapper = mount(Page, { global: { plugins: [router, uiComponents] } })
  await flushPromises()
  await flushPromises()
  return wrapper
}

// jsdom's Blob has no text(), and FileReader.readAsText() strips the BOM by
// spec. Read raw bytes and decode with ignoreBOM so the exact bytes (BOM
// included) are asserted.
const readBlobText = blob =>
  new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () =>
      resolve(new TextDecoder('utf-8', { ignoreBOM: true }).decode(reader.result))
    reader.onerror = () => reject(reader.error)
    reader.readAsArrayBuffer(blob)
  })

const originalCreateObjectURL = URL.createObjectURL
const originalRevokeObjectURL = URL.revokeObjectURL

let blobs = []
let clicks = []
const createObjectURL = vi.fn(blob => {
  blobs.push(blob)
  return 'blob:http://localhost:3000/procedure-stats'
})
const revokeObjectURL = vi.fn()
let clickSpy

describe('procedure stats csv export (T3 / S6)', () => {
  beforeEach(() => {
    signInAs('administrador')
    getMock.mockReset()
    getMock.mockResolvedValue({ data: payload })
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 9, 19, 12, 0, 0))

    blobs = []
    clicks = []
    createObjectURL.mockClear()
    revokeObjectURL.mockClear()
    URL.createObjectURL = createObjectURL
    URL.revokeObjectURL = revokeObjectURL
    clickSpy = vi
      .spyOn(HTMLAnchorElement.prototype, 'click')
      .mockImplementation(function handleClick() {
        clicks.push({ href: this.href, download: this.download })
      })
  })

  afterEach(() => {
    vi.useRealTimers()
    clickSpy.mockRestore()
    if (originalCreateObjectURL === undefined) {
      delete URL.createObjectURL
    } else {
      URL.createObjectURL = originalCreateObjectURL
    }
    if (originalRevokeObjectURL === undefined) {
      delete URL.revokeObjectURL
    } else {
      URL.revokeObjectURL = originalRevokeObjectURL
    }
  })

  it('escapes commas, quotes and spreadsheet formulas in the exact CSV', () => {
    const csv = buildProcedureStatsCsv({
      topProcedures: payload.top_procedures,
      bySpecialty: payload.by_specialty
    })

    expect(csv).toBe(expectedCsv)
  })

  it('builds the exact filename from the period bounds', () => {
    expect(buildProcedureStatsFilename('2026-07-22', '2026-10-19')).toBe(
      'estadisticas-catalogo-2026-07-22_2026-10-19.csv'
    )
  })

  it('downloads the applied period on click and revokes the object URL', async () => {
    const wrapper = await mountPage()

    // The draft filters change but are NOT applied: the export must still use
    // the applied period (S6 — "no el borrador de filtros").
    await wrapper.find('#procedure-stats-from').setValue('2026-09-01')
    await wrapper.find('#procedure-stats-to').setValue('2026-09-15')
    await flushPromises()

    await wrapper.find('[data-export-csv]').trigger('click')
    await flushPromises()

    expect(createObjectURL).toHaveBeenCalledTimes(1)
    expect(clicks).toEqual([
      {
        href: 'blob:http://localhost:3000/procedure-stats',
        download: 'estadisticas-catalogo-2026-07-22_2026-10-19.csv'
      }
    ])
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:http://localhost:3000/procedure-stats')
    expect(await readBlobText(blobs[0])).toBe(expectedCsv)

    wrapper.unmount()
  })

  it('disables the export button when the period has no usage data', async () => {
    getMock.mockResolvedValue({ data: emptyPayload })

    const wrapper = await mountPage()

    const button = wrapper.find('[data-export-csv]')
    expect(button.exists()).toBe(true)
    expect(button.attributes('disabled')).toBeDefined()

    wrapper.unmount()
  })
})
