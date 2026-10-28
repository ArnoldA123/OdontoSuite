import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import FileUpload from '../../../resources/js/components/ui/FileUpload.vue'

// Issue #426 — the primitive owned no rejection channel: a wrong file (type or
// size) opened a native alert() and the consumer's inline error stayed empty,
// while the rejected preview was never painted because the file was never
// adopted. The contract: the primitive refuses the file outright (no preview,
// no fileSelected), publishes { reason, message } through `fileRejected`, and
// exposes a silent clear() so a consumer that ran its own validation can drop
// the adopted file and hand the picker back to the user.
const mounted = []

const track = wrapper => {
  mounted.push(wrapper)
  return wrapper
}

const mountUpload = (props = {}) => track(mount(FileUpload, { props, attachTo: document.body }))

const fileInput = wrapper => wrapper.find('input[type="file"]')

const selectFile = async (wrapper, file) => {
  const { element } = fileInput(wrapper)
  Object.defineProperty(element, 'files', { value: [file], configurable: true })
  await fileInput(wrapper).trigger('change')
  await flushPromises()
}

const imageFile = () => new File(['x'], 'rx.jpg', { type: 'image/jpeg' })
const textFile = () => new File(['x'], 'notes.txt', { type: 'text/plain' })

const fileOfSize = (bytes, type = 'image/jpeg') => {
  const file = new File(['x'], 'huge.jpg', { type })
  Object.defineProperty(file, 'size', { value: bytes })
  return file
}

const preview = () => document.querySelector('.preview-image')
const clearButton = () => document.querySelector('.clear-btn')
const uploadPrompt = () => document.querySelector('.upload-prompt')
const fileInfo = () => document.querySelector('.file-info')

// FileReader resolves on the jsdom event loop, so give it a few turns.
const waitForPreview = async () => {
  for (let attempt = 0; attempt < 20 && !preview(); attempt += 1) {
    await new Promise(resolve => setTimeout(resolve, 0))
  }
  return preview()
}

const lastEmission = (wrapper, event) => {
  const emissions = wrapper.emitted(event) || []
  return emissions[emissions.length - 1]
}

afterEach(() => {
  while (mounted.length > 0) mounted.pop().unmount()
  document.body.innerHTML = ''
  vi.restoreAllMocks()
})

describe('FileUpload rejection channel (issue #426)', () => {
  it('never opens a native alert when it refuses a file', async () => {
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {})
    const wrapper = mountUpload({ maxSize: 1024 })

    await selectFile(wrapper, textFile())
    await selectFile(wrapper, fileOfSize(2048))

    expect(alertSpy).not.toHaveBeenCalled()
    expect(wrapper.emitted('fileRejected')).toHaveLength(2)
  })

  it('rejects a non-image file with reason "type" and does not select it', async () => {
    const wrapper = mountUpload()

    await selectFile(wrapper, textFile())

    expect(wrapper.emitted('fileSelected')).toBeUndefined()
    expect(lastEmission(wrapper, 'fileRejected')).toEqual([
      { reason: 'type', message: 'Solo se permiten archivos de imagen' }
    ])
    expect(await waitForPreview()).toBeNull()
    expect(fileInfo()).toBeNull()
    expect(uploadPrompt()).not.toBeNull()
  })

  it('rejects a file over maxSize with reason "size" and does not select it', async () => {
    const wrapper = mountUpload({ maxSize: 1024 })

    await selectFile(wrapper, fileOfSize(1025))

    expect(wrapper.emitted('fileSelected')).toBeUndefined()
    expect(lastEmission(wrapper, 'fileRejected')).toEqual([
      { reason: 'size', message: 'El archivo es demasiado grande. Máximo 1 KB' }
    ])
    expect(await waitForPreview()).toBeNull()
    expect(uploadPrompt()).not.toBeNull()
  })

  it('selects a valid image, paints the preview and labels the clear control', async () => {
    const wrapper = mountUpload()
    const file = imageFile()

    await selectFile(wrapper, file)

    expect(lastEmission(wrapper, 'fileSelected')).toEqual([file])
    expect(wrapper.emitted('fileRejected')).toBeUndefined()
    expect(await waitForPreview()).not.toBeNull()
    expect(fileInfo().textContent).toContain('rx.jpg')

    const clear = clearButton()
    expect(clear).not.toBeNull()
    expect(clear.getAttribute('type')).toBe('button')
    expect(clear.getAttribute('aria-label')).toBe('Quitar la imagen seleccionada')
  })

  it('exposes a silent clear() that drops the preview without emitting fileCleared', async () => {
    const wrapper = mountUpload()

    await selectFile(wrapper, imageFile())
    expect(await waitForPreview()).not.toBeNull()

    wrapper.vm.clear()
    await flushPromises()

    expect(preview()).toBeNull()
    expect(fileInfo()).toBeNull()
    expect(uploadPrompt()).not.toBeNull()
    expect(wrapper.emitted('fileCleared')).toBeUndefined()
    expect(fileInput(wrapper).element.value).toBe('')
  })

  it('keeps the X button emitting fileCleared', async () => {
    const wrapper = mountUpload()

    await selectFile(wrapper, imageFile())
    expect(await waitForPreview()).not.toBeNull()

    clearButton().click()
    await flushPromises()

    expect(wrapper.emitted('fileCleared')).toHaveLength(1)
    expect(preview()).toBeNull()
  })

  // #426 residual: the sheet adopts the file (its own primitive runs with
  // `maxSize = Infinity`) and only then applies its 20 MB rule, calling
  // clear(). The preview read is async, so without invalidation it lands after
  // the reset and repaints the rejected file as if it were ready.
  it('does not repaint the preview of a file dropped before its read settled', async () => {
    const wrapper = mountUpload()

    await selectFile(wrapper, imageFile())

    // The consumer refuses it in the same tick `fileSelected` arrives, before
    // the FileReader finished.
    wrapper.vm.clear()
    await flushPromises()

    expect(await waitForPreview()).toBeNull()
    expect(fileInfo()).toBeNull()
    expect(uploadPrompt()).not.toBeNull()

    // A later adoption still paints its own preview: invalidation is scoped to
    // the dropped file, not a permanent mute.
    await selectFile(wrapper, imageFile())

    expect(await waitForPreview()).not.toBeNull()
  })
})
