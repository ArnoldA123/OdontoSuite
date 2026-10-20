import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { fileURLToPath, URL as NodeURL } from 'node:url'

// AI analysis detail surface (T4): header identity, image with fallback,
// findings/recommendations/metadata, the gated review form and the recorded
// outcome, plus deletion behind useConfirm. Permissions and the confirm
// promise are mocked because the surface only consumes their contracts.
const harness = vi.hoisted(() => ({
  reviewAllowed: true,
  deleteAllowed: true,
  confirm: vi.fn()
}))

vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: {
      'ai-analysis.review': { value: harness.reviewAllowed },
      'ai-analysis.delete': { value: harness.deleteAllowed }
    }
  })
}))

vi.mock('../../../resources/js/composables/useConfirm', () => ({
  useConfirm: () => ({
    confirm: harness.confirm,
    isOpen: { value: false },
    title: { value: '' },
    message: { value: '' },
    confirmText: { value: '' },
    cancelText: { value: '' },
    variant: { value: 'default' },
    loading: { value: false },
    handleConfirm: () => {},
    handleCancel: () => {}
  })
}))

import uiComponents from '../../../resources/js/plugins/ui-components'
import AiAnalysisModal from '../../../resources/js/modules/ai-analysis/components/AiAnalysisModal.vue'

const analysisFixture = (overrides = {}) => ({
  id: 42,
  status: 'completed',
  reviewed: false,
  confidence_score: 91.5,
  created_at: '2026-10-01T10:15:00.000000Z',
  model_used: 'gpt-4o-mini',
  tokens_used: 1530,
  findings: [
    'Caries oclusal en pieza 16',
    { description: 'Pérdida ósea leve en sector anterior' },
    { finding: 'Lesión periapical en pieza 21' },
    { text: 'Resto del examen sin hallazgos' }
  ],
  recommendations: ['Endodoncia en pieza 16', { description: 'Control radiográfico en 30 días' }],
  patient: { id: 7, full_name: 'Ana Torres' },
  clinical_attachment: {
    id: 3,
    file_path: 'clinical-attachments/rx.jpg',
    original_name: 'rx.jpg',
    category: 'radiografia'
  },
  requested_by: { id: 2, name: 'Dr. Pérez' },
  ...overrides
})

const mountModal = analysis =>
  mount(AiAnalysisModal, {
    props: { analysis },
    global: {
      plugins: [uiComponents],
      stubs: { teleport: true }
    }
  })

beforeEach(() => {
  harness.reviewAllowed = true
  harness.deleteAllowed = true
  harness.confirm.mockReset()
})

describe('ai analysis detail surface', () => {
  it('renders the patient header, findings, recommendations and metadata', () => {
    const wrapper = mountModal(analysisFixture())

    expect(wrapper.find('[data-ai-detail]').exists()).toBe(true)
    expect(wrapper.find('[data-ai-detail-patient]').text()).toBe('Ana Torres')
    expect(wrapper.find('[data-ai-detail-category]').text()).toBe('Radiografía')
    expect(wrapper.find('[data-ai-detail-status]').text()).toBe('Completado')

    const findings = wrapper.find('[data-ai-section="findings"]')
    expect(findings.text()).toContain('Hallazgos')
    expect(findings.text()).toContain('Caries oclusal en pieza 16')
    expect(findings.text()).toContain('Pérdida ósea leve en sector anterior')
    expect(findings.text()).toContain('Lesión periapical en pieza 21')
    expect(findings.text()).toContain('Resto del examen sin hallazgos')

    const recommendations = wrapper.find('[data-ai-section="recommendations"]')
    expect(recommendations.text()).toContain('Recomendaciones')
    expect(recommendations.text()).toContain('Endodoncia en pieza 16')
    expect(recommendations.text()).toContain('Control radiográfico en 30 días')

    const metadata = wrapper.find('[data-ai-section="metadata"]')
    expect(metadata.text()).toContain('Metadatos')
    expect(wrapper.find('[data-ai-meta="model"]').text()).toContain('gpt-4o-mini')
    expect(wrapper.find('[data-ai-meta="tokens"]').text()).toContain('1530')
    expect(wrapper.find('[data-ai-meta="date"]').text()).toContain('2026')
    expect(wrapper.find('[data-ai-meta="requested-by"]').text()).toContain('Dr. Pérez')

    const confidence = wrapper.find('[data-ai-confidence]')
    expect(confidence.text()).toContain('91.5%')
    expect(confidence.text()).toContain('Alta')

    wrapper.unmount()
  })

  it('renders the empty section copy when findings and recommendations are absent', () => {
    const wrapper = mountModal(analysisFixture({ findings: [], recommendations: null }))

    expect(wrapper.find('[data-ai-section="findings"]').text()).toContain(
      'Sin hallazgos reportados.'
    )
    expect(wrapper.find('[data-ai-section="recommendations"]').text()).toContain(
      'Sin recomendaciones registradas.'
    )

    wrapper.unmount()
  })

  it('maps every status to its Spanish label', () => {
    const labels = {
      pending: 'Pendiente',
      processing: 'Procesando',
      completed: 'Completado',
      failed: 'Fallido'
    }

    Object.entries(labels).forEach(([status, label]) => {
      const wrapper = mountModal(analysisFixture({ status }))
      expect(wrapper.find('[data-ai-detail-status]').text()).toBe(label)
      wrapper.unmount()
    })
  })

  it('renders the storage image and falls back when the attachment has no file path', () => {
    const withImage = mountModal(analysisFixture())
    const image = withImage.find('[data-ai-image]')
    expect(image.exists()).toBe(true)
    expect(image.attributes('src')).toBe('/storage/clinical-attachments/rx.jpg')
    expect(image.attributes('alt')).toBe('rx.jpg')
    expect(withImage.find('[data-ai-image-fallback]').exists()).toBe(false)
    withImage.unmount()

    const withoutImage = mountModal(analysisFixture({ clinical_attachment: null }))
    expect(withoutImage.find('[data-ai-image]').exists()).toBe(false)
    const fallback = withoutImage.find('[data-ai-image-fallback]')
    expect(fallback.exists()).toBe(true)
    expect(fallback.text()).toContain('Imagen no disponible')
    expect(withoutImage.find('[data-ai-detail-category]').text()).toBe('—')
    withoutImage.unmount()
  })

  it('keeps the review submit disabled until a decision is chosen and emits the review', async () => {
    const wrapper = mountModal(analysisFixture())

    const form = wrapper.find('[data-ai-review-form]')
    expect(form.exists()).toBe(true)
    expect(wrapper.find('[data-ai-review-submit]').text()).toContain('Guardar revisión')
    expect(wrapper.find('[data-ai-review-submit]').attributes('disabled')).toBeDefined()

    const notes = wrapper.find('[data-ai-review-notes]')
    expect(notes.attributes('maxlength')).toBe('1000')
    expect(wrapper.find('[data-ai-review-notes-count]').text()).toBe('0 / 1000')

    const decision = wrapper.find('[data-ai-review-decision]')
    await decision.find('button').trigger('click')

    const optionLabels = decision
      .findAll('button')
      .map(button => button.text().trim())
      .filter(Boolean)
    expect(optionLabels).toContain('Selecciona una decisión')
    expect(optionLabels).toContain('Aceptado')
    expect(optionLabels).toContain('Rechazado')
    expect(optionLabels).toContain('Parcial')

    const accepted = decision.findAll('button').find(button => button.text().trim() === 'Aceptado')
    expect(accepted).toBeTruthy()
    await accepted.trigger('click')

    expect(wrapper.find('[data-ai-review-submit]').attributes('disabled')).toBeUndefined()

    await notes.setValue('Conforme')
    expect(wrapper.find('[data-ai-review-notes-count]').text()).toBe('8 / 1000')

    await form.trigger('submit')
    expect(wrapper.emitted('review')).toEqual([[42, 'accepted', 'Conforme']])

    wrapper.unmount()
  })

  it('shows the recorded outcome instead of the form for a reviewed analysis', () => {
    const wrapper = mountModal(
      analysisFixture({
        reviewed: true,
        review_decision: 'accepted',
        review_notes: 'Sin hallazgos adicionales',
        reviewed_by: { id: 5, name: 'Dra. Ruiz' },
        reviewed_at: '2026-10-02T15:30:00.000000Z'
      })
    )

    expect(wrapper.find('[data-ai-review-form]').exists()).toBe(false)
    expect(wrapper.find('[data-ai-review-outcome]').exists()).toBe(true)
    expect(wrapper.find('[data-ai-review-decision-label]').text()).toBe('Aceptado')
    expect(wrapper.find('[data-ai-review-notes-value]').text()).toBe('Sin hallazgos adicionales')
    expect(wrapper.find('[data-ai-review-reviewer]').text()).toBe('Dra. Ruiz')
    expect(wrapper.find('[data-ai-review-date]').text()).toContain('2026')
    expect(wrapper.find('[data-ai-delete]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('falls back to placeholders for missing review and metadata values', () => {
    const wrapper = mountModal(
      analysisFixture({
        reviewed: true,
        review_decision: 'unknown',
        review_notes: null,
        reviewed_by: null,
        reviewed_at: null,
        confidence_score: null,
        model_used: null,
        tokens_used: null,
        requested_by: null
      })
    )

    expect(wrapper.find('[data-ai-review-decision-label]').text()).toBe('—')
    expect(wrapper.find('[data-ai-review-notes-value]').text()).toBe('Sin notas')
    expect(wrapper.find('[data-ai-review-reviewer]').text()).toBe('—')
    expect(wrapper.find('[data-ai-review-date]').text()).toBe('—')
    expect(wrapper.find('[data-ai-confidence]').text()).toBe('—')
    expect(wrapper.find('[data-ai-meta="model"]').text()).toBe('—')
    expect(wrapper.find('[data-ai-meta="tokens"]').text()).toBe('—')
    expect(wrapper.find('[data-ai-meta="requested-by"]').text()).toBe('—')

    wrapper.unmount()
  })

  it('only emits delete after useConfirm resolves true', async () => {
    harness.confirm.mockResolvedValue(false)
    const wrapper = mountModal(analysisFixture())

    await wrapper.find('[data-ai-delete]').trigger('click')
    await flushPromises()

    expect(harness.confirm).toHaveBeenCalledWith({
      title: 'Eliminar análisis',
      message: '¿Estás seguro de que quieres eliminar este análisis?',
      confirmText: 'Eliminar',
      variant: 'danger'
    })
    expect(wrapper.emitted('delete')).toBeUndefined()

    harness.confirm.mockResolvedValue(true)
    await wrapper.find('[data-ai-delete]').trigger('click')
    await flushPromises()

    expect(wrapper.emitted('delete')).toEqual([[42]])

    wrapper.unmount()
  })

  it('hides the review form and the delete action without the review permission', () => {
    harness.reviewAllowed = false
    harness.deleteAllowed = false
    const wrapper = mountModal(analysisFixture())

    expect(wrapper.find('[data-ai-review-form]').exists()).toBe(false)
    expect(wrapper.find('[data-ai-review-denied]').text()).toBe(
      'No tienes permiso para revisar este análisis.'
    )
    expect(wrapper.find('[data-ai-delete]').exists()).toBe(false)

    wrapper.unmount()
  })
})

describe('ai analysis detail hygiene', () => {
  it('keeps the surface free of scoped styles, gradients and dead color aliases', () => {
    const path = fileURLToPath(
      new NodeURL(
        '../../../resources/js/modules/ai-analysis/components/AiAnalysisModal.vue',
        import.meta.url
      )
    )
    const source = readFileSync(path, 'utf8')

    expect(source).not.toContain('<style')
    expect(source).not.toContain('bg-gradient-')
    expect(source).not.toContain('text-ink-')
    expect(source).not.toContain('systemBlue')
    expect(source).not.toContain('text-[11px]')
    expect(source).not.toContain('border-theme')
  })
})
