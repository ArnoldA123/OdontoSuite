import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { fileURLToPath, URL as NodeURL } from 'node:url'

// AI analysis detail surface (T4): header identity, image with fallback,
// findings/recommendations/metadata, the gated review form and the recorded
// outcome, plus annulment with a required reason (#296). Permissions and the
// confirm promise are mocked because the surface only consumes their contracts.
const harness = vi.hoisted(() => ({
  reviewAllowed: true,
  voidAllowed: true,
  confirm: vi.fn()
}))

vi.mock('../../../resources/js/composables/usePermissions', () => ({
  usePermissions: () => ({
    can: {
      'ai-analysis.review': { value: harness.reviewAllowed },
      'ai-analysis.void': { value: harness.voidAllowed }
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
  assistive: true,
  assistive_notice: 'Resultado de apoyo a la decisión asistido por inteligencia artificial.',
  notice_acknowledged_at: null,
  notice_acknowledged_by: null,
  estimated_cost: null,
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
  harness.voidAllowed = true
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

  it('renders the assistive notice, its declaration and the estimated cost', () => {
    const wrapper = mountModal(
      analysisFixture({
        notice_acknowledged_at: '2026-10-01T10:16:00.000000Z',
        notice_acknowledged_by: { id: 2, name: 'Dr. Pérez' },
        estimated_cost: 0.0312
      })
    )

    expect(wrapper.find('[data-ai-assistive-notice]').text()).toBe(
      'Resultado de apoyo a la decisión asistido por inteligencia artificial.'
    )

    const metadata = wrapper.find('[data-ai-section="metadata"]')
    expect(metadata.text()).toContain('Aviso al paciente')
    expect(metadata.text()).toContain('Declarado por')
    expect(metadata.text()).toContain('Costo estimado')
    expect(wrapper.find('[data-ai-meta="notice-date"]').text()).toContain('2026')
    expect(wrapper.find('[data-ai-meta="notice-by"]').text()).toBe('Dr. Pérez')
    expect(wrapper.find('[data-ai-meta="cost"]').text()).toBe('$0.0312')

    wrapper.unmount()
  })

  it('falls back to the assistive literal and placeholders without notice data', () => {
    const wrapper = mountModal(analysisFixture({ assistive_notice: null }))

    expect(wrapper.find('[data-ai-assistive-notice]').text()).toBe(
      'Apoyo a la decisión asistido por IA. No sustituye el diagnóstico profesional.'
    )
    expect(wrapper.find('[data-ai-meta="notice-date"]').text()).toBe('—')
    expect(wrapper.find('[data-ai-meta="notice-by"]').text()).toBe('—')
    expect(wrapper.find('[data-ai-meta="cost"]').text()).toBe('—')

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
    // S3: a reviewed analysis is still annulled, and its review trace survives.
    expect(wrapper.find('[data-ai-void]').exists()).toBe(true)

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

  it('requires a reason and confirms before emitting the annulment', async () => {
    harness.confirm.mockResolvedValue(false)
    const wrapper = mountModal(analysisFixture())

    const reason = wrapper.find('[data-ai-void-reason]')
    const button = wrapper.find('[data-ai-void]')

    expect(reason.attributes('maxlength')).toBe('255')
    expect(button.text()).toContain('Anular análisis')
    expect(button.attributes('disabled')).toBeDefined()

    await reason.setValue('Duplicado del estudio previo')
    expect(button.attributes('disabled')).toBeUndefined()

    await button.trigger('click')
    await flushPromises()

    expect(harness.confirm).toHaveBeenCalledWith({
      title: 'Anular análisis',
      message:
        '¿Anular este análisis? Se conserva la traza de revisión y el motivo queda registrado.',
      confirmText: 'Anular',
      variant: 'danger'
    })
    expect(wrapper.emitted('void')).toBeUndefined()

    harness.confirm.mockResolvedValue(true)
    await button.trigger('click')
    await flushPromises()

    expect(wrapper.emitted('void')).toEqual([[42, 'Duplicado del estudio previo']])

    wrapper.unmount()
  })

  it('renders the annulment trace instead of the form for a voided analysis', () => {
    const wrapper = mountModal(
      analysisFixture({
        voided: true,
        voided_at: '2026-10-03T09:00:00.000000Z',
        void_reason: 'Se subió la radiografía equivocada',
        voided_by: { id: 5, name: 'Dra. Ruiz' }
      })
    )

    expect(wrapper.find('[data-ai-void-reason]').exists()).toBe(false)
    expect(wrapper.find('[data-ai-void]').exists()).toBe(false)

    const trace = wrapper.find('[data-ai-void-trace]')
    expect(trace.exists()).toBe(true)
    expect(trace.text()).toContain('Anulado por')
    expect(trace.text()).toContain('Fecha de anulación')
    expect(trace.text()).toContain('Motivo')
    expect(wrapper.find('[data-ai-voided-by]').text()).toBe('Dra. Ruiz')
    expect(wrapper.find('[data-ai-voided-at]').text()).toContain('2026')
    expect(wrapper.find('[data-ai-void-reason-value]').text()).toBe(
      'Se subió la radiografía equivocada'
    )

    wrapper.unmount()
  })

  it('falls back to placeholders when the annulment trace has no values', () => {
    const wrapper = mountModal(
      analysisFixture({ voided: true, voided_at: null, void_reason: null, voided_by: null })
    )

    expect(wrapper.find('[data-ai-voided-by]').text()).toBe('—')
    expect(wrapper.find('[data-ai-voided-at]').text()).toBe('—')
    expect(wrapper.find('[data-ai-void-reason-value]').text()).toBe('—')

    wrapper.unmount()
  })

  it('hides the review form and the annulment block without their permissions', () => {
    harness.reviewAllowed = false
    harness.voidAllowed = false
    const wrapper = mountModal(analysisFixture())

    expect(wrapper.find('[data-ai-review-form]').exists()).toBe(false)
    expect(wrapper.find('[data-ai-review-denied]').text()).toBe(
      'No tienes permiso para revisar este análisis.'
    )
    expect(wrapper.find('[data-ai-void]').exists()).toBe(false)
    expect(wrapper.find('[data-ai-void-reason]').exists()).toBe(false)

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

  it('composes the patient name when the payload omits full_name', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {})

    const wrapper = mountModal(
      analysisFixture({
        patient: { id: 7, first_name: 'Ana', last_name: 'Torres' }
      })
    )

    expect(wrapper.find('[data-ai-detail-patient]').text()).toBe('Ana Torres')
    expect(
      warnSpy.mock.calls
        .map(args => String(args[0]))
        .filter(message => message.includes('type check failed for prop "status"'))
    ).toEqual([])

    warnSpy.mockRestore()
    wrapper.unmount()
  })
})
