import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DataTable from '../../../resources/js/components/ui/DataTable.vue'

// #436 — the catalog list must inherit the table contract of its module. The
// primitive needs to let a caller pin the table width (the catalog columns are
// wider than a phone) and keep its own row hooks, without changing what any
// existing consumer renders.
const COLUMNS = [
  { key: 'code', label: 'Código' },
  { key: 'name', label: 'Nombre' }
]

const ROWS = [
  { id: 7, code: 'ENDO-001', name: 'Endodoncia unirradicular' },
  { id: 8, code: 'OBT-001', name: 'Obturación' }
]

const mountTable = props =>
  mount(DataTable, {
    props: {
      columns: COLUMNS,
      data: ROWS,
      searchable: false,
      pagination: false,
      showHeader: false,
      ...props
    }
  })

describe('DataTable caller contract (#436)', () => {
  it('applies the declared table class and the per-row attributes', () => {
    const wrapper = mountTable({
      tableClass: 'min-w-[880px]',
      rowAttributes: row => ({ 'data-procedure-row': String(row.id) })
    })

    expect(wrapper.find('.data-table').classes()).toContain('min-w-[880px]')

    const rows = wrapper.findAll('tbody tr[data-procedure-row]')
    expect(rows).toHaveLength(2)
    expect(rows[0].attributes('data-procedure-row')).toBe('7')
    expect(rows[1].attributes('data-procedure-row')).toBe('8')

    wrapper.unmount()
  })

  it('renders the previous markup when no extra table contract is declared', () => {
    const wrapper = mountTable({})

    expect(wrapper.find('.data-table').classes()).toHaveLength(1)
    expect(wrapper.find('tbody tr[data-procedure-row]').exists()).toBe(false)

    wrapper.unmount()
  })
})
