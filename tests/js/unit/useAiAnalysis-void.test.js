import { beforeEach, describe, expect, it, vi } from 'vitest'

// Issue #423 — the API answers the annulment of a cited analysis with 422 and
// the reason under `errors`, while the generic `message` is «Error de
// validación». The toast is the only surface of that action, so it must carry
// the reason the reader needs.
const harness = vi.hoisted(() => ({
  post: vi.fn(),
  success: vi.fn(),
  error: vi.fn()
}))

vi.mock('../../../resources/js/composables/useApi', () => ({
  useApi: () => ({
    get: vi.fn(),
    post: harness.post,
    put: vi.fn(),
    delete: vi.fn(),
    request: vi.fn()
  })
}))

vi.mock('../../../resources/js/composables/useToast', () => ({
  useToast: () => ({ success: harness.success, error: harness.error })
}))

import { useAiAnalysis } from '../../../resources/js/composables/useAiAnalysis'

beforeEach(() => {
  harness.post.mockReset()
  harness.success.mockReset()
  harness.error.mockReset()
})

describe('useAiAnalysis annulment failure', () => {
  it('surfaces the validation reason the API publishes', async () => {
    harness.post.mockRejectedValue({
      response: {
        status: 422,
        data: {
          message: 'Error de validación',
          errors: {
            analysis: ['No se puede anular un análisis citado en una evolución o en un plan.']
          }
        }
      }
    })

    const { voidAnalysis } = useAiAnalysis()

    await expect(voidAnalysis(2, 'Intento de anulación')).rejects.toBeTruthy()
    expect(harness.error).toHaveBeenCalledWith(
      'No se puede anular un análisis citado en una evolución o en un plan.'
    )
  })

  it('falls back to the generic message when the API sends no detail', async () => {
    harness.post.mockRejectedValue({ response: { status: 500, data: {} } })

    const { voidAnalysis } = useAiAnalysis()

    await expect(voidAnalysis(2, 'Intento de anulación')).rejects.toBeTruthy()
    expect(harness.error).toHaveBeenCalledWith('Error al anular análisis')
  })

  it('keeps the success toast and stays silent on errors when it works', async () => {
    harness.post.mockResolvedValue({ data: { data: { id: 3 } } })

    const { voidAnalysis } = useAiAnalysis()
    await voidAnalysis(3, 'Duplicado del estudio previo')

    expect(harness.success).toHaveBeenCalledTimes(1)
    expect(harness.error).not.toHaveBeenCalled()
  })
})
