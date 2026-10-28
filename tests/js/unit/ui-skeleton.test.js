import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Skeleton from '../../../resources/js/components/ui/Skeleton.vue'

// #411 — the size props are the caller's when it declares them. The hero paints
// its loading badge and its KPI figures inside a flex row, where the variant's
// default rectangle collapsed to zero width and the skeleton was present but
// invisible.
const styleOf = wrapper => wrapper.find('.skeleton-item').attributes('style') || ''

describe('UiSkeleton size props', () => {
  it('keeps the size the variant paints when the caller declares none', () => {
    const wrapper = mount(Skeleton, { props: { variant: 'rectangular' } })

    expect(styleOf(wrapper)).toContain('width: 100%')
    expect(styleOf(wrapper)).toContain('height: 200px')
  })

  it('honours an explicit width and height over the variant default', () => {
    const wrapper = mount(Skeleton, {
      props: { variant: 'rectangular', width: 120, height: 28 }
    })

    expect(styleOf(wrapper)).toContain('width: 120px')
    expect(styleOf(wrapper)).toContain('height: 28px')
  })

  it('sizes a single text bar so a figure can stand in its own place', () => {
    const wrapper = mount(Skeleton, {
      props: { variant: 'text', count: 1, width: '88px', height: '1.75rem' }
    })

    expect(wrapper.findAll('.skeleton-item')).toHaveLength(1)
    expect(styleOf(wrapper)).toContain('width: 88px')
    expect(styleOf(wrapper)).toContain('height: 1.75rem')
  })
})
