import { describe, it, expect } from 'vitest'
import {
  buildAppointmentAvailability,
  buildHourGrid,
  groupAppointmentTypes,
  rankAppointmentTypes,
  specialtyKeyOf
} from '../../../resources/js/composables/useAppointmentAvailability'

const professionals = [
  { id: 2, name: 'Dra. Carmen Salazar', specialty: 'Odontología', role: 'odontologo' },
  { id: 3, name: 'Dr. Marco Pinto', specialty: 'Implantología', role: 'implantologo' },
  { id: 4, name: 'Dra. Lucía Ferrer', specialty: 'orthodontics', role: 'odontologo' }
]

const chairs = [
  { id: 7, name: 'Sillón 1', code: 'S1' },
  { id: 8, name: 'Sillón 2', code: 'S2' },
  { id: 9, name: 'Sillón 3', code: 'S3' }
]

const at = value => new Date(`2026-11-05T${value}:00`)

const windowStart = at('09:00')
const windowEnd = at('09:45')

const appointment = (overrides = {}) => ({
  id: 100,
  user_id: 2,
  dental_chair_id: 7,
  appointment_type_id: 5,
  scheduled_at: '2026-11-05T09:00:00',
  ends_at: '2026-11-05T09:30:00',
  duration_minutes: 30,
  status: 'scheduled',
  ...overrides
})

const personFor = (result, userId) => result.professionals.find(person => person.id === userId)
const chairFor = (result, chairId) => result.chairs.find(chair => chair.id === chairId)

describe('buildAppointmentAvailability — profesionales y sillones independientes', () => {
  it('publishes one row per professional and one per chair, not a pair list', () => {
    const result = buildAppointmentAvailability({
      appointments: [],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(result.unknown).toBe(false)
    expect(result.professionals.map(person => person.id)).toEqual([2, 3, 4])
    expect(result.chairs.map(chair => chair.id)).toEqual([7, 8, 9])
    expect(result.professionals.every(person => person.free)).toBe(true)
    expect(result.chairs.every(chair => chair.free)).toBe(true)
  })

  it('marks a professional busy with the end of the appointment that crosses the window', () => {
    const result = buildAppointmentAvailability({
      appointments: [appointment({ id: 1, user_id: 2, dental_chair_id: 7 })],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(personFor(result, 2).free).toBe(false)
    expect(personFor(result, 2).busyUntil).toBe('09:30')
    expect(personFor(result, 3).free).toBe(true)
  })

  it('keeps chair availability independent from the professional who is using it', () => {
    const result = buildAppointmentAvailability({
      // El Dr. Pinto (3) ocupa el Sillón 1 (7); la Dra. Salazar (2) está libre.
      appointments: [appointment({ id: 1, user_id: 3, dental_chair_id: 7 })],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(personFor(result, 2).free).toBe(true)
    expect(personFor(result, 3).free).toBe(false)
    expect(chairFor(result, 7).free).toBe(false)
    expect(chairFor(result, 7).busyUntil).toBe('09:30')
    expect(chairFor(result, 8).free).toBe(true)
  })

  it('never blocks a free professional on a free chair because of a previous pairing', () => {
    // La agenda del día usó Pinto en el Sillón 1; Salazar en el Sillón 2 sigue
    // libre a esa hora, así que los dos recursos libres son combinables.
    const result = buildAppointmentAvailability({
      appointments: [
        appointment({
          id: 1,
          user_id: 3,
          dental_chair_id: 7,
          scheduled_at: '2026-11-05T08:00:00',
          ends_at: '2026-11-05T08:30:00'
        })
      ],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(personFor(result, 2).free).toBe(true)
    expect(chairFor(result, 7).free).toBe(true)
    expect(chairFor(result, 8).free).toBe(true)
  })

  it('reads an adjacent appointment as free in both directions', () => {
    const atNine = appointment({
      id: 1,
      user_id: 2,
      dental_chair_id: 7,
      scheduled_at: '2026-11-05T09:00:00',
      ends_at: '2026-11-05T09:30:00'
    })

    // La ventana arranca exactamente donde termina la cita: `[inicio, fin)`.
    const afterTheEnd = buildAppointmentAvailability({
      appointments: [atNine],
      professionals,
      chairs,
      start: at('09:30'),
      end: at('10:00')
    })

    expect(personFor(afterTheEnd, 2).free).toBe(true)
    expect(personFor(afterTheEnd, 2).busyUntil).toBeNull()
    expect(chairFor(afterTheEnd, 7).free).toBe(true)

    // La otra dirección: la ventana termina donde la cita empieza.
    const startsAtNineThirty = appointment({
      id: 2,
      user_id: 2,
      dental_chair_id: 7,
      scheduled_at: '2026-11-05T09:30:00',
      ends_at: '2026-11-05T10:00:00'
    })
    const beforeTheStart = buildAppointmentAvailability({
      appointments: [startsAtNineThirty],
      professionals,
      chairs,
      start: at('09:00'),
      end: at('09:30')
    })

    expect(personFor(beforeTheStart, 2).free).toBe(true)
    expect(chairFor(beforeTheStart, 7).free).toBe(true)
  })

  it('keeps the later end when two appointments of the professional overlap', () => {
    const result = buildAppointmentAvailability({
      appointments: [
        appointment({ id: 1, user_id: 2, dental_chair_id: 7, ends_at: '2026-11-05T09:20:00' }),
        appointment({
          id: 2,
          user_id: 2,
          dental_chair_id: 8,
          scheduled_at: '2026-11-05T09:15:00',
          ends_at: '2026-11-05T10:00:00'
        })
      ],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(personFor(result, 2).busyUntil).toBe('10:00')
    expect(chairFor(result, 8).busyUntil).toBe('10:00')
  })

  it('ignores cancelled appointments and treats the window as half-open', () => {
    const result = buildAppointmentAvailability({
      appointments: [
        appointment({ id: 1, status: 'cancelled', ends_at: '2026-11-05T11:00:00' }),
        appointment({
          id: 2,
          user_id: 3,
          dental_chair_id: 8,
          scheduled_at: '2026-11-05T08:30:00',
          ends_at: '2026-11-05T09:00:00'
        })
      ],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(personFor(result, 2).free).toBe(true)
    expect(personFor(result, 3).free).toBe(true)
    expect(chairFor(result, 7).free).toBe(true)
    expect(result.busyUntil.users).toEqual({})
  })

  it('does not let the appointment being edited block itself', () => {
    const result = buildAppointmentAvailability({
      appointments: [appointment({ id: 42, user_id: 2, dental_chair_id: 7 })],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd,
      excludeAppointmentId: 42
    })

    expect(personFor(result, 2).free).toBe(true)
    expect(chairFor(result, 7).free).toBe(true)
  })

  it('derives the end from duration_minutes when the row has no ends_at', () => {
    const result = buildAppointmentAvailability({
      appointments: [
        appointment({ id: 1, user_id: 2, dental_chair_id: 7, ends_at: null, duration_minutes: 45 })
      ],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(personFor(result, 2).free).toBe(false)
    expect(personFor(result, 2).busyUntil).toBe('09:45')
  })

  it('degrades to unknown when the day agenda was never read', () => {
    const result = buildAppointmentAvailability({
      appointments: null,
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(result.unknown).toBe(true)
    expect(result.professionals).toEqual([])
    expect(result.chairs).toEqual([])
    expect(result.suggestions).toEqual([])
  })

  it('degrades to unknown when the window is incomplete or inverted', () => {
    const noEnd = buildAppointmentAvailability({
      appointments: [],
      professionals,
      chairs,
      start: windowStart,
      end: null
    })
    const inverted = buildAppointmentAvailability({
      appointments: [],
      professionals,
      chairs,
      start: windowEnd,
      end: windowStart
    })

    expect(noEnd.unknown).toBe(true)
    expect(noEnd.professionals).toEqual([])
    expect(inverted.unknown).toBe(true)
  })

  it('an empty but read day is known: every professional and chair is free', () => {
    const result = buildAppointmentAvailability({
      appointments: [],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(result.unknown).toBe(false)
    expect(result.professionals.every(person => person.free)).toBe(true)
    expect(result.chairs.every(chair => chair.free)).toBe(true)
  })

  it('publishes no rows when the catalogs are empty', () => {
    const result = buildAppointmentAvailability({
      appointments: [],
      professionals: [],
      chairs: [],
      start: windowStart,
      end: windowEnd
    })

    expect(result.unknown).toBe(false)
    expect(result.professionals).toEqual([])
    expect(result.chairs).toEqual([])
  })
})

describe('buildAppointmentAvailability — atajos de la agenda del día', () => {
  it('offers the pairs the day already used, in chronological order', () => {
    const result = buildAppointmentAvailability({
      appointments: [
        appointment({
          id: 1,
          user_id: 3,
          dental_chair_id: 9,
          scheduled_at: '2026-11-05T11:00:00',
          ends_at: '2026-11-05T11:30:00'
        }),
        appointment({
          id: 2,
          user_id: 2,
          dental_chair_id: 8,
          scheduled_at: '2026-11-05T08:00:00',
          ends_at: '2026-11-05T08:30:00'
        })
      ],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(result.suggestions).toHaveLength(2)
    expect(result.suggestions[0].user.id).toBe(2)
    expect(result.suggestions[0].chair.id).toBe(8)
    expect(result.suggestions[1].user.id).toBe(3)
    expect(result.suggestions[1].chair.id).toBe(9)
    // El atajo de la mañana no ocupa la ventana de las 09:00: sigue libre.
    expect(result.suggestions.every(suggestion => suggestion.free)).toBe(true)
  })

  it('keeps a repeated pair as a single suggestion', () => {
    const result = buildAppointmentAvailability({
      appointments: [
        appointment({
          id: 1,
          user_id: 2,
          dental_chair_id: 7,
          scheduled_at: '2026-11-05T08:00:00',
          ends_at: '2026-11-05T08:30:00'
        }),
        appointment({
          id: 2,
          user_id: 2,
          dental_chair_id: 7,
          scheduled_at: '2026-11-05T10:00:00',
          ends_at: '2026-11-05T10:30:00'
        })
      ],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(result.suggestions).toHaveLength(1)
  })

  it('declares a suggestion busy with its reason instead of hiding it', () => {
    const result = buildAppointmentAvailability({
      appointments: [appointment({ id: 1, user_id: 2, dental_chair_id: 7 })],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(result.suggestions[0].free).toBe(false)
    expect(result.suggestions[0].busyUntil).toBe('09:30')
    expect(result.suggestions[0].user.name).toBe('Dra. Carmen Salazar')
  })

  it('drops a suggestion whose resources are no longer in the catalogs', () => {
    const result = buildAppointmentAvailability({
      appointments: [appointment({ id: 1, user_id: 99, dental_chair_id: 7 })],
      professionals,
      chairs,
      start: windowStart,
      end: windowEnd
    })

    expect(result.suggestions).toEqual([])
  })
})

describe('buildHourGrid — casillas de 15 minutos', () => {
  const date = '2026-11-05'
  const gridArgs = {
    appointments: [],
    date,
    durationMinutes: 30
  }

  it('builds 07:00–20:00 in 15-minute cells grouped by hour', () => {
    const grid = buildHourGrid(gridArgs)

    expect(grid.unknown).toBe(false)
    expect(grid.slots).toHaveLength(52)
    expect(grid.slots[0].value).toBe('07:00')
    expect(grid.slots[grid.slots.length - 1].value).toBe('19:45')
    expect(grid.blocks.map(block => block.label)).toEqual(['07–10', '10–13', '13–16', '16–20'])
    // Tres horas por bloque; el último se queda con el resto del día.
    expect(grid.blocks.map(block => block.rows.length)).toEqual([3, 3, 3, 4])
    expect(grid.blocks[0].rows[0].slots.map(slot => slot.value)).toEqual([
      '07:00',
      '07:15',
      '07:30',
      '07:45'
    ])
  })

  it('disables the cells that collide with the selected professional', () => {
    const grid = buildHourGrid({
      ...gridArgs,
      userId: 2,
      appointments: [
        appointment({
          id: 1,
          user_id: 2,
          dental_chair_id: 7,
          scheduled_at: '2026-11-05T09:00:00',
          ends_at: '2026-11-05T09:30:00'
        })
      ]
    })

    const blocked = grid.slots.filter(slot => slot.disabled).map(slot => slot.value)
    // Una cita de 09:00 a 09:30 ocupa las casillas de 09:00 y 09:15 con una
    // duración de 30 min; 08:45 ya choca porque terminaría a las 09:15.
    expect(blocked).toEqual(['08:45', '09:00', '09:15'])
    const slot = grid.slots.find(candidate => candidate.value === '09:00')
    expect(slot.reason).toBe('Ocupado hasta 09:30')
    expect(slot.ariaLabel).toBe('09:00, ocupado hasta 09:30')
  })

  it('keeps the 09:30 cell open when the 09:00 appointment ends exactly there', () => {
    const grid = buildHourGrid({
      ...gridArgs,
      userId: 2,
      chairId: 7,
      appointments: [
        appointment({
          id: 1,
          user_id: 2,
          dental_chair_id: 7,
          scheduled_at: '2026-11-05T09:00:00',
          ends_at: '2026-11-05T09:30:00'
        })
      ]
    })

    // La ventana es semiabierta `[inicio, fin)`: la casilla 09:30–10:00 no choca
    // con una cita que termina a las 09:30, la misma regla que el servidor.
    const adjacent = grid.slots.find(slot => slot.value === '09:30')
    expect(adjacent.disabled).toBe(false)
    expect(adjacent.reason).toBe('')
    expect(adjacent.ariaLabel).toBe('09:30')

    // La de las 09:15 sigue ocupada: su ventana 09:15–09:45 cruza el fin.
    expect(grid.slots.find(slot => slot.value === '09:15').disabled).toBe(true)
  })

  it('keeps the 09:00 cell open when the appointment starts exactly there', () => {
    const grid = buildHourGrid({
      ...gridArgs,
      userId: 2,
      chairId: 7,
      appointments: [
        appointment({
          id: 1,
          user_id: 2,
          dental_chair_id: 7,
          scheduled_at: '2026-11-05T09:30:00',
          ends_at: '2026-11-05T10:00:00'
        })
      ]
    })

    // La otra dirección: una cita que empieza a las 09:30 no ocupa la ventana
    // 09:00–09:30, que termina justo donde la otra abre.
    const adjacent = grid.slots.find(slot => slot.value === '09:00')
    expect(adjacent.disabled).toBe(false)
    expect(adjacent.reason).toBe('')

    // La de las 09:15 sí choca: 09:15–09:45 se solapa con la 09:30–10:00.
    expect(grid.slots.find(slot => slot.value === '09:15').disabled).toBe(true)
  })

  it('disables the cells of a chair busy with someone else', () => {
    const grid = buildHourGrid({
      ...gridArgs,
      userId: 2,
      chairId: 7,
      appointments: [
        appointment({
          id: 1,
          user_id: 3,
          dental_chair_id: 7,
          scheduled_at: '2026-11-05T10:00:00',
          ends_at: '2026-11-05T10:45:00'
        })
      ]
    })

    const blocked = grid.slots.filter(slot => slot.disabled).map(slot => slot.value)
    expect(blocked).toEqual(['09:45', '10:00', '10:15', '10:30'])
  })

  it('keeps every cell open when neither the professional nor the chair is chosen', () => {
    const grid = buildHourGrid({
      ...gridArgs,
      appointments: [appointment({ id: 1, user_id: 2, dental_chair_id: 7 })]
    })

    expect(grid.slots.every(slot => slot.disabled === false)).toBe(true)
  })

  it('does not block the appointment being edited', () => {
    const grid = buildHourGrid({
      ...gridArgs,
      userId: 2,
      excludeAppointmentId: 42,
      appointments: [appointment({ id: 42, user_id: 2, dental_chair_id: 7 })]
    })

    expect(grid.slots.every(slot => slot.disabled === false)).toBe(true)
  })

  it('never claims a cell is occupied when the agenda was not read', () => {
    const grid = buildHourGrid({ ...gridArgs, appointments: null, userId: 2, chairId: 7 })

    expect(grid.unknown).toBe(true)
    expect(grid.slots).toHaveLength(52)
    expect(grid.slots.every(slot => slot.disabled === false)).toBe(true)
    expect(grid.slots.every(slot => slot.reason === '')).toBe(true)
  })

  it('indexes every cell in day order so the keyboard can walk them', () => {
    const grid = buildHourGrid(gridArgs)

    expect(grid.slots.map(slot => slot.index)).toEqual(
      Array.from({ length: 52 }, (_, index) => index)
    )
  })
})

describe('groupAppointmentTypes — tipos por especialidad', () => {
  const types = [
    {
      id: 1,
      name: 'Consulta General',
      default_duration_minutes: 30,
      specialty: { id: 3, code: 'general', name: 'General' }
    },
    {
      id: 2,
      name: 'Ortodoncia',
      default_duration_minutes: 30,
      specialty: { id: 5, code: 'ortodoncia', name: 'Ortodoncia' }
    },
    {
      id: 3,
      name: 'Empaste',
      default_duration_minutes: 45,
      specialty: { id: 6, code: 'rehabilitacion', name: 'Rehabilitación' }
    },
    { id: 4, name: 'Otro tipo', default_duration_minutes: 15, specialty: null }
  ]

  it('puts the types of the selected professional specialty first', () => {
    const grouped = groupAppointmentTypes(types, { specialty: 'orthodontics' })

    expect(grouped.preferred.map(type => type.id)).toEqual([2])
    expect(grouped.specialtyLabel).toBe('Ortodoncia')
  })

  it('matches the professional by catalog code or by display name', () => {
    expect(groupAppointmentTypes(types, { specialty: 'ortodoncia' }).preferred).toHaveLength(1)
    expect(groupAppointmentTypes(types, { specialty: 'Ortodoncia' }).preferred).toHaveLength(1)
    expect(groupAppointmentTypes(types, { specialty: 'Cirugía Oral' }).preferred).toHaveLength(0)
  })

  it('keeps the types without a specialty in their own group, read last', () => {
    const grouped = groupAppointmentTypes(types, null)

    expect(grouped.preferred).toEqual([])
    expect(grouped.groups.map(group => group.label)).toEqual([
      'General',
      'Ortodoncia',
      'Rehabilitación',
      'Sin especialidad'
    ])
    expect(grouped.groups[grouped.groups.length - 1].types.map(type => type.id)).toEqual([4])
  })

  it('never drops a type: the groups hold the whole catalog', () => {
    const grouped = groupAppointmentTypes(types, { specialty: 'general' })

    expect(grouped.groups.flatMap(group => group.types.map(type => type.id)).sort()).toEqual([
      1, 2, 3, 4
    ])
  })

  it('has no preferred types when the professional has no clinical specialty', () => {
    expect(groupAppointmentTypes(types, { specialty: 'dental_technician' }).preferred).toEqual([])
    expect(groupAppointmentTypes(types, null).preferred).toEqual([])
  })

  it('canonicalises the legacy strings the users table still stores', () => {
    expect(specialtyKeyOf('orthodontics')).toBe('ortodoncia')
    expect(specialtyKeyOf('implantology')).toBe('implantologia')
    expect(specialtyKeyOf({ code: 'cirugia_oral' })).toBe('cirugia_oral')
    expect(specialtyKeyOf({ name: 'Cirugía Oral' })).toBe('cirugia_oral')
    expect(specialtyKeyOf('assistant')).toBeNull()
    expect(specialtyKeyOf(null)).toBeNull()
  })
})

describe('rankAppointmentTypes', () => {
  const types = [
    { id: 5, name: 'Consulta general', default_duration_minutes: 30 },
    { id: 6, name: 'Control ortodoncia', default_duration_minutes: 20 },
    { id: 7, name: 'Resina compuesta', default_duration_minutes: 45 },
    { id: 8, name: 'Endodoncia', default_duration_minutes: 60 },
    { id: 9, name: 'Blanqueamiento', default_duration_minutes: 90 }
  ]

  it('falls back to catalog order when the day has no appointments', () => {
    expect(rankAppointmentTypes(types, []).map(type => type.id)).toEqual([5, 6, 7, 8])
  })

  it('ranks the day usage first and keeps catalog order as the tiebreak', () => {
    const day = [
      appointment({ id: 1, appointment_type_id: 8 }),
      appointment({ id: 2, appointment_type_id: 8 }),
      appointment({ id: 3, appointment_type_id: 7 })
    ]

    expect(rankAppointmentTypes(types, day).map(type => type.id)).toEqual([8, 7, 5, 6])
  })

  it('does not count cancelled appointments as usage', () => {
    const day = [appointment({ id: 1, appointment_type_id: 9, status: 'cancelled' })]

    expect(rankAppointmentTypes(types, day).map(type => type.id)).toEqual([5, 6, 7, 8])
  })
})
