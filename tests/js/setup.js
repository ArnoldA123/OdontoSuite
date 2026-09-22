// Global jsdom polyfills for the frontend smoke suite (issue #23).
// Kept intentionally small: only browser APIs jsdom does not implement and
// that the mounted pages (or their layout) touch during render.
import { afterAll } from 'vitest'

// Node 26 exposes an experimental `localStorage` global that resolves to
// `undefined` without `--localstorage-file`, and it shadows the jsdom Storage
// (vitest skips re-defining globals Node already owns). Install a deterministic
// in-memory Storage so composables like useAuth() can read/write it.
{
  const store = new Map()
  const storage = {
    getItem: key => (store.has(String(key)) ? store.get(String(key)) : null),
    setItem: (key, value) => store.set(String(key), String(value)),
    removeItem: key => store.delete(String(key)),
    clear: () => store.clear(),
    key: index => Array.from(store.keys())[index] ?? null,
    get length() {
      return store.size
    }
  }
  try {
    Object.defineProperty(globalThis, 'localStorage', {
      value: storage,
      configurable: true,
      writable: true
    })
  } catch {
    globalThis.localStorage = storage
  }
}

// Timers scheduled by a mounted page can outlive the test. DashboardPage, for
// example, schedules a spring update that then loops on requestAnimationFrame.
// After the jsdom environment is torn down those callbacks reference globals
// that no longer exist, which vitest reports as an unhandled
// "requestAnimationFrame is not defined" error and fails the process. Track
// every timer and cancel the pending ones in afterAll.
const timers = new Set()
const nativeSetTimeout = globalThis.setTimeout
const nativeClearTimeout = globalThis.clearTimeout
const nativeSetInterval = globalThis.setInterval
const nativeClearInterval = globalThis.clearInterval
globalThis.setTimeout = (...args) => {
  const handle = nativeSetTimeout(...args)
  timers.add(handle)
  return handle
}
globalThis.clearTimeout = handle => {
  timers.delete(handle)
  return nativeClearTimeout(handle)
}
globalThis.setInterval = (...args) => {
  const handle = nativeSetInterval(...args)
  timers.add(handle)
  return handle
}
globalThis.clearInterval = handle => {
  timers.delete(handle)
  return nativeClearInterval(handle)
}

// Animation frame loop: setTimeout(16) is enough for mount-time springs.
globalThis.requestAnimationFrame = callback => globalThis.setTimeout(() => callback(Date.now()), 16)
globalThis.cancelAnimationFrame = handle => globalThis.clearTimeout(handle)
if (typeof window !== 'undefined') {
  window.requestAnimationFrame = globalThis.requestAnimationFrame
  window.cancelAnimationFrame = globalThis.cancelAnimationFrame
}
afterAll(() => {
  for (const handle of timers) {
    nativeClearTimeout(handle)
    nativeClearInterval(handle)
  }
  timers.clear()
})

// Media queries used by reduced-motion / responsive behaviour.
if (!globalThis.matchMedia) {
  globalThis.matchMedia = query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false
  })
}

class NoopObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
  takeRecords() {
    return []
  }
}
globalThis.IntersectionObserver = NoopObserver
globalThis.ResizeObserver = NoopObserver

// Canvas: jsdom has no 2d context, and Chart.js aborts without one. The proxy
// accepts any method call and returns benign values for the few calls whose
// result Chart.js actually reads.
if (typeof HTMLCanvasElement !== 'undefined') {
  HTMLCanvasElement.prototype.getContext = function () {
    const canvas = this
    const target = { canvas }
    const gradient = { addColorStop: () => {} }
    return new Proxy(target, {
      get(store, prop) {
        if (prop in store) return store[prop]
        if (prop === 'measureText') {
          return () => ({
            width: 0,
            actualBoundingBoxLeft: 0,
            actualBoundingBoxRight: 0,
            actualBoundingBoxAscent: 0,
            actualBoundingBoxDescent: 0
          })
        }
        if (prop === 'getLineDash') return () => []
        if (prop === 'createLinearGradient' || prop === 'createRadialGradient') {
          return () => gradient
        }
        if (prop === 'createPattern') return () => null
        return () => {}
      },
      set(store, prop, value) {
        store[prop] = value
        return true
      }
    })
  }
}

// Scroll APIs used by navigation helpers; no-ops keep them inert.
if (typeof window !== 'undefined') {
  window.scrollTo = () => {}
  window.scroll = () => {}
}
if (typeof Element !== 'undefined') {
  Element.prototype.scrollTo = () => {}
  Element.prototype.scrollIntoView = () => {}
  // @vueuse/motion falls back to the Web Animations API on some paths.
  if (!Element.prototype.animate) {
    Element.prototype.animate = () => ({
      cancel: () => {},
      finish: () => {},
      play: () => {},
      pause: () => {},
      addEventListener: () => {},
      removeEventListener: () => {},
      finished: Promise.resolve()
    })
  }
}
