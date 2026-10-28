import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

// #424 (S2): the stats tab is patient-scoped, so it cannot know by itself what
// the workspace resolved. The page hands it the decision through two booleans
// and the component renders exactly one action: an evolution when a history is
// selected, the history creation when the patient has none, nothing otherwise.
import MedicalRecordStats from '../../../resources/js/modules/medical-records/components/MedicalRecordStats.vue'

const emptyStats = {
  total_records: 0,
  total_evolutions: 0,
  total_attachments: 0,
  pending_follow_ups: 0
}

const statsWithData = {
  total_records: 1,
  total_evolutions: 2,
  total_attachments: 1,
  pending_follow_ups: 0,
  last_evolution: {
    id: 5,
    evolution_date: '2026-03-05',
    specialty: 'endodoncia',
    assessment: 'Caries'
  },
  specialties_involved: ['endodoncia'],
  attachment_categories: { radiografia: 1 }
}

const mountStats = (props = {}) =>
  mount(MedicalRecordStats, { props: { stats: emptyStats, ...props } })

describe('medical record stats empty state action', () => {
  it('offers an evolution when the workspace has a selected history', async () => {
    const wrapper = mountStats({ canCreateEvolution: true })

    const action = wrapper.get('[data-state="empty-stats"] [data-empty-stats-action]')
    expect(action.text()).toBe('Nueva evolución')

    await action.trigger('click')

    expect(wrapper.emitted('create-evolution')).toHaveLength(1)

    wrapper.unmount()
  })

  it('offers the history creation when the patient has no history yet', async () => {
    const wrapper = mountStats({ canCreateRecord: true })

    const action = wrapper.get('[data-state="empty-stats"] [data-empty-stats-action]')
    expect(action.text()).toBe('Nueva historia')

    await action.trigger('click')

    expect(wrapper.emitted('create-record')).toHaveLength(1)
    expect(wrapper.emitted('create-evolution')).toBeUndefined()

    wrapper.unmount()
  })

  it('prefers the evolution when both decisions are available', () => {
    const wrapper = mountStats({ canCreateEvolution: true, canCreateRecord: true })

    const actions = wrapper.findAll('[data-state="empty-stats"] [data-empty-stats-action]')
    expect(actions).toHaveLength(1)
    expect(actions[0].text()).toBe('Nueva evolución')

    wrapper.unmount()
  })

  it('declares what is missing without an action when neither decision is available', () => {
    const wrapper = mountStats()

    expect(wrapper.get('[data-state="empty-stats"]').text()).toContain(
      'Sin actividad en esta historia'
    )
    expect(wrapper.find('[data-empty-stats-action]').exists()).toBe(false)

    wrapper.unmount()
  })

  it('renders the stats blocks instead of the empty state once there is data', () => {
    const wrapper = mountStats({ stats: statsWithData, canCreateEvolution: true })

    expect(wrapper.find('[data-state="empty-stats"]').exists()).toBe(false)
    expect(wrapper.find('[data-empty-stats-action]').exists()).toBe(false)
    expect(wrapper.find('[data-last-evolution-date]').exists()).toBe(true)

    wrapper.unmount()
  })
})
