<template>
  <UiModal
    :model-value="modelValue"
    :title="modalTitle"
    size="3xl"
    @update:model-value="closeModal"
    @close="closeModal"
  >
    <LoadingSpinner v-if="loadingData" class="py-8" size="md" text="Cargando datos..." />

    <!-- El cuerpo del modal ya es el contenedor que desplaza (`UiModal` le da
         `overflow-y-auto`): el formulario no abre un segundo scroll. Dos
         columnas en tableta y escritorio, una en móvil, con el ritmo de 20 px
         entre bloques que fija `design/system.md` §3. Cada columna declara
         `min-w-0`: sin esa pieza un hijo con ancho intrínseco (una fecha nativa,
         una casilla) empuja la columna y recorta el borde del diálogo. -->
    <form
      v-else
      id="appointment-form"
      data-appointment-form
      class="min-w-0 space-y-5 bg-canvas"
      novalidate
      @submit.prevent="saveAppointment"
    >
      <div class="grid min-w-0 gap-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <!-- Columna 1 · el paciente y el horario -->
        <div class="min-w-0 space-y-5">
          <!-- 1 · Paciente: el único campo que se busca -->
          <section class="space-y-2" data-appointment-section="patient">
            <div
              v-if="selectedPatient || form.patient_id"
              class="flex min-h-[44px] items-center gap-3 rounded-ios border border-hairline bg-theme-surface-elevated px-3 py-2 max-sm:min-h-[48px]"
              :data-appointment-patient="form.patient_id"
            >
              <span
                class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent-100 text-xs font-semibold text-accent-700"
                aria-hidden="true"
              >
                {{ initialsOf(selectedPatientName) }}
              </span>
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-theme-primary">
                  {{ selectedPatientName }}
                </p>
                <p class="truncate text-xs text-theme-secondary">{{ selectedPatientMeta }}</p>
              </div>
              <UiButton type="button" variant="ghost" size="sm" @click="changePatient">
                Cambiar
              </UiButton>
            </div>

            <div v-else>
              <div
                class="relative"
                role="combobox"
                aria-haspopup="listbox"
                :aria-expanded="pressed(showPatientResults)"
                :aria-controls="PATIENT_LISTBOX_ID"
                :aria-activedescendant="activePatientOptionId || undefined"
                @keydown.down.prevent.stop="moveActivePatient(1)"
                @keydown.up.prevent.stop="moveActivePatient(-1)"
                @keydown.enter.prevent.stop="chooseActivePatient"
                @keydown.esc="closePatientResults"
              >
                <UiInput
                  :id="PATIENT_SEARCH_ID"
                  :model-value="patientQuery"
                  label="Buscar por nombre o documento"
                  type="search"
                  placeholder="Nombre o documento"
                  autocomplete="off"
                  :hint="patientHint"
                  :error="patientError"
                  class="max-sm:[&_input]:min-h-[48px]"
                  @update:model-value="onPatientQuery"
                />

                <ul
                  v-if="showPatientResults && patientResults.length > 0"
                  :id="PATIENT_LISTBOX_ID"
                  role="listbox"
                  aria-label="Pacientes que coinciden"
                  class="absolute z-dropdown mt-1 max-h-64 w-full overflow-y-auto rounded-ios border border-hairline bg-theme-surface-elevated p-1 shadow-lg"
                >
                  <li
                    v-for="(patient, index) in patientResults"
                    :id="patientOptionId(index)"
                    :key="patient.id"
                    role="option"
                    :aria-selected="index === activePatientIndex ? 'true' : 'false'"
                    class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-md px-2 py-2 max-sm:min-h-[48px]"
                    :class="index === activePatientIndex ? 'bg-accent-100' : 'hover:bg-canvas'"
                    @click="selectPatient(patient)"
                  >
                    <span
                      class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-accent-100 text-xs font-semibold text-accent-700"
                      aria-hidden="true"
                    >
                      {{ initialsOf(patient.name) }}
                    </span>
                    <span class="min-w-0 flex-1">
                      <span class="block truncate text-sm font-medium text-theme-primary">
                        {{ patient.name }}
                      </span>
                      <span class="block truncate text-xs text-theme-secondary">
                        {{ patientMeta(patient) }}
                      </span>
                    </span>
                  </li>
                </ul>
              </div>

              <p v-if="patientNote" class="mt-1 text-xs text-theme-secondary" role="status">
                {{ patientNote }}
              </p>
            </div>
          </section>

          <!-- 2 · Cuándo: fecha nativa + casillas de 15 minutos -->
          <section class="space-y-3" data-appointment-section="when">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <p class="text-sm font-semibold text-theme-primary">Cuándo</p>
              <span v-if="windowLabel" class="text-xs text-theme-secondary" data-appointment-window>
                {{ windowLabel }}
              </span>
            </div>

            <div class="grid min-w-0 grid-cols-[minmax(0,1fr)_130px] gap-3">
              <UiInput
                v-model="form.date"
                label="Fecha"
                type="date"
                required
                :error="fieldError('date')"
                class="min-w-0 [&_input]:min-w-0 max-sm:[&_input]:min-h-[48px]"
                @blur="onFieldBlur('date')"
              />
              <div class="min-w-0" role="group" :aria-labelledby="DURATION_LABEL_ID">
                <p :id="DURATION_LABEL_ID" class="mb-1 text-sm font-medium text-theme-primary">
                  Duración
                </p>
                <UiSelect
                  v-model="form.duration_minutes"
                  :options="durationOptions"
                  label="Duración"
                  required
                  :error="fieldError('duration_minutes')"
                  class="max-sm:[&_button]:min-h-[48px]"
                  @change="onDurationChange"
                  @blur="onFieldBlur('duration_minutes')"
                />
              </div>
            </div>

            <!-- La hora se elige en casillas de 15 minutos, no en la rueda
                 del campo de hora nativo: sus casillas se agrupan por hora y se
                 paginan en tres bloques para que el bloque más alto nunca pase
                 de cinco filas. -->
            <div class="space-y-2" data-appointment-hour-picker>
              <p
                v-if="!hourGridReady"
                class="text-xs text-theme-secondary"
                data-appointment-hour-hint
              >
                {{ hourGridHint }}
              </p>

              <template v-else>
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <p class="flex flex-wrap items-baseline gap-x-2">
                    <span :id="TIME_LABEL_ID" class="text-sm font-medium text-theme-primary">
                      Hora de inicio
                    </span>
                    <span
                      v-if="endsAtLabel"
                      class="text-xs text-theme-secondary"
                      data-appointment-end
                    >
                      Termina {{ endsAtLabel }}
                    </span>
                  </p>
                  <div
                    class="inline-flex items-center gap-1 rounded-full border border-hairline bg-theme-surface-elevated p-1"
                    role="group"
                    aria-label="Bloques del día"
                    data-appointment-hour-blocks
                  >
                    <button
                      v-for="block in hourGrid.blocks"
                      :key="block.id"
                      type="button"
                      :aria-pressed="pressed(activeTimeBlock === block.id)"
                      class="min-h-[28px] rounded-full px-2.5 text-xs font-medium transition-colors focus:outline-none [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                      :class="
                        activeTimeBlock === block.id
                          ? 'bg-accent text-white'
                          : 'text-theme-secondary hover:text-theme-primary'
                      "
                      :data-appointment-hour-block="block.id"
                      @click="activeTimeBlock = block.id"
                    >
                      {{ block.label }}
                    </button>
                  </div>
                </div>

                <div
                  ref="hourGridRef"
                  class="grid gap-1"
                  role="group"
                  :aria-labelledby="TIME_LABEL_ID"
                  :aria-invalid="fieldError('time') ? 'true' : 'false'"
                  data-appointment-hour-grid
                  @keydown="onHourGridKeydown"
                >
                  <div
                    v-for="row in activeHourRows"
                    :key="row.hour"
                    class="grid min-w-0 grid-cols-4 gap-1"
                    :data-appointment-hour-row="row.hour"
                  >
                    <button
                      v-for="slot in row.slots"
                      :key="slot.value"
                      type="button"
                      class="min-h-[44px] min-w-0 rounded-ios border px-1 text-xs font-medium tabular-nums transition-colors focus:outline-none disabled:cursor-not-allowed [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                      :class="hourSlotClasses(slot)"
                      :disabled="slot.disabled"
                      :aria-pressed="pressed(form.time === slot.value)"
                      :aria-label="slot.ariaLabel"
                      :data-appointment-slot="slot.value"
                      :data-slot-index="slot.index"
                      :data-slot-busy="slot.disabled ? slot.busyUntil : null"
                      @click="chooseTime(slot)"
                    >
                      {{ slot.label }}
                    </button>
                  </div>
                </div>

                <p
                  v-if="gridUnknownNote"
                  class="text-xs text-theme-secondary"
                  data-appointment-hour-note
                >
                  {{ gridUnknownNote }}
                </p>
                <p v-if="fieldError('time')" class="text-xs text-error-500" role="alert">
                  {{ fieldError('time') }}
                </p>
              </template>
            </div>

            <p v-if="scheduleError" class="text-xs text-error-500" role="alert">
              {{ scheduleError }}
            </p>
          </section>
        </div>

        <!-- Columna 2 · quién lo atiende, qué se hace y lo que casi nunca se toca -->
        <div class="min-w-0 space-y-5">
          <!-- 3 · Tipo de cita. Los tipos dependen de la especialidad del
               profesional elegido; el catálogo completo vive detrás de «Otros
               tipos» y la sección nunca se parte: o cabe con sus chips o se
               pliega entera. -->
          <!-- 2 · Quién atiende y en qué ambiente: dos listas independientes -->
          <section class="space-y-3" data-appointment-section="team">
            <div class="space-y-1">
              <p :id="PAIR_LABEL_ID" class="text-sm font-semibold text-theme-primary">
                Quién atiende y en qué ambiente
              </p>
              <p class="text-xs text-theme-secondary" data-appointment-combination>
                {{ combinationLine }}
              </p>
            </div>

            <template v-if="pairsVisible">
              <div
                v-if="suggestedPairs.length > 0"
                class="space-y-1.5"
                role="group"
                :aria-labelledby="SUGGESTION_LABEL_ID"
                data-appointment-suggestions
              >
                <p :id="SUGGESTION_LABEL_ID" class="text-xs font-medium text-theme-secondary">
                  Ya trabajaron juntos hoy
                </p>
                <div class="flex flex-wrap gap-1.5">
                  <button
                    v-for="suggestion in suggestedPairs"
                    :key="`${suggestion.user.id}-${suggestion.chair.id}`"
                    type="button"
                    :disabled="!suggestion.free"
                    :aria-pressed="pressed(isPairSelected(suggestion))"
                    :aria-label="suggestionAriaLabel(suggestion)"
                    class="inline-flex min-h-[28px] max-w-full min-w-0 items-center gap-2 rounded-full border px-3 text-xs transition-colors focus:outline-none [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                    :class="pairRowClasses(suggestion)"
                    :data-appointment-suggestion="`${suggestion.user.id}-${suggestion.chair.id}`"
                    @click="selectPair(suggestion)"
                  >
                    <span class="min-w-0 break-words font-medium">
                      {{ suggestion.user.name }} · {{ chairLabel(suggestion.chair) }}
                    </span>
                    <span
                      class="shrink-0"
                      :class="suggestion.free ? 'text-systemGreen-700' : 'text-systemYellow-700'"
                      data-appointment-suggestion-state
                    >
                      {{ suggestion.free ? 'Libre' : `Ocupado hasta ${suggestion.busyUntil}` }}
                    </span>
                  </button>
                </div>
              </div>

              <!-- La puerta de combinación libre está siempre visible: no es un
                   escape escondido, y no bloquea ninguna mezcla por no ser «la
                   pareja» que la agenda usó antes. -->
              <div
                class="grid grid-cols-2 gap-3"
                data-appointment-team-door
                :data-appointment-free-professionals="freePersonCount"
                :data-appointment-free-chairs="freeChairCount"
              >
                <div
                  class="space-y-1.5"
                  role="group"
                  :aria-labelledby="PROFESSIONAL_LABEL_ID"
                  :aria-invalid="fieldError('user_id') ? 'true' : 'false'"
                  data-appointment-professional-list
                >
                  <div class="flex items-center justify-between gap-1">
                    <p :id="PROFESSIONAL_LABEL_ID" class="text-xs font-medium text-theme-secondary">
                      Profesional
                    </p>
                    <div
                      v-if="availabilityProfessionals.length > TEAM_PAGE_SIZE"
                      class="flex shrink-0 items-center gap-0.5 text-xs text-theme-secondary"
                      data-appointment-professionals-pager
                    >
                      <button
                        type="button"
                        class="min-h-[20px] min-w-[20px] rounded-md transition-colors hover:text-theme-primary focus:outline-none [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                        aria-label="Profesionales anteriores"
                        data-appointment-professionals-prev
                        @click="
                          shiftTeamPage('professionals', availabilityProfessionals.length, -1)
                        "
                      >
                        ‹
                      </button>
                      <span class="tabular-nums" data-appointment-professionals-range>
                        {{ pagerLabel(availabilityProfessionals.length, 'professionals') }}
                      </span>
                      <button
                        type="button"
                        class="min-h-[20px] min-w-[20px] rounded-md transition-colors hover:text-theme-primary focus:outline-none [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                        aria-label="Profesionales siguientes"
                        data-appointment-professionals-next
                        @click="shiftTeamPage('professionals', availabilityProfessionals.length, 1)"
                      >
                        ›
                      </button>
                    </div>
                  </div>
                  <ul class="space-y-1" data-appointment-professional-rows>
                    <li v-for="person in pagedProfessionals" :key="person.id">
                      <button
                        type="button"
                        :disabled="!person.free"
                        :aria-pressed="pressed(isProfessionalSelected(person))"
                        :aria-label="
                          person.free
                            ? `${person.name}, libre`
                            : `${person.name}, ocupado hasta ${person.busyUntil}`
                        "
                        class="flex min-h-[44px] w-full min-w-0 flex-col justify-center rounded-ios border px-2 py-1.5 text-left transition-colors focus:outline-none disabled:cursor-not-allowed [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                        :class="teamRowClasses(person, isProfessionalSelected(person))"
                        :data-appointment-professional="person.id"
                        @click="chooseProfessional(person)"
                      >
                        <span
                          class="block min-w-0 break-words text-xs font-medium leading-tight text-theme-primary"
                        >
                          {{ person.name }}
                        </span>
                        <span
                          class="block text-xs leading-tight"
                          :class="person.free ? 'text-systemGreen-700' : 'text-systemYellow-700'"
                        >
                          {{ person.free ? 'Libre' : `Ocupado hasta ${person.busyUntil}` }}
                        </span>
                      </button>
                    </li>
                  </ul>
                  <p v-if="fieldError('user_id')" class="text-xs text-error-500" role="alert">
                    {{ fieldError('user_id') }}
                  </p>
                </div>

                <div
                  class="space-y-1.5"
                  role="group"
                  :aria-labelledby="CHAIR_LABEL_ID"
                  :aria-invalid="fieldError('dental_chair_id') ? 'true' : 'false'"
                  data-appointment-chair-list
                >
                  <div class="flex items-center justify-between gap-1">
                    <p :id="CHAIR_LABEL_ID" class="text-xs font-medium text-theme-secondary">
                      Ambiente
                    </p>
                    <div
                      v-if="availabilityChairs.length > TEAM_PAGE_SIZE"
                      class="flex items-center gap-0.5 text-xs text-theme-secondary"
                      data-appointment-chairs-pager
                    >
                      <button
                        type="button"
                        class="min-h-[20px] min-w-[20px] rounded-md transition-colors hover:text-theme-primary focus:outline-none [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                        aria-label="Ambientes anteriores"
                        data-appointment-chairs-prev
                        @click="shiftTeamPage('chairs', availabilityChairs.length, -1)"
                      >
                        ‹
                      </button>
                      <span class="tabular-nums" data-appointment-chairs-range>
                        {{ pagerLabel(availabilityChairs.length, 'chairs') }}
                      </span>
                      <button
                        type="button"
                        class="min-h-[20px] min-w-[20px] rounded-md transition-colors hover:text-theme-primary focus:outline-none [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                        aria-label="Ambientes siguientes"
                        data-appointment-chairs-next
                        @click="shiftTeamPage('chairs', availabilityChairs.length, 1)"
                      >
                        ›
                      </button>
                    </div>
                  </div>
                  <ul class="space-y-1" data-appointment-chair-rows>
                    <li v-for="chair in pagedChairs" :key="chair.id">
                      <button
                        type="button"
                        :disabled="!chair.free"
                        :aria-pressed="pressed(isChairSelected(chair))"
                        :aria-label="
                          chair.free
                            ? `${chairLabel(chair)}, libre`
                            : `${chairLabel(chair)}, ocupado hasta ${chair.busyUntil}`
                        "
                        class="flex min-h-[44px] w-full min-w-0 flex-col justify-center rounded-ios border px-2 py-1.5 text-left transition-colors focus:outline-none disabled:cursor-not-allowed [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                        :class="teamRowClasses(chair, isChairSelected(chair))"
                        :data-appointment-chair="chair.id"
                        @click="chooseChair(chair)"
                      >
                        <span
                          class="block min-w-0 break-words text-xs font-medium leading-tight text-theme-primary"
                        >
                          {{ chair.name || chair.code }}
                        </span>
                        <span
                          class="block text-xs leading-tight"
                          :class="chair.free ? 'text-systemGreen-700' : 'text-systemYellow-700'"
                        >
                          {{ chair.free ? 'Libre' : `Ocupado hasta ${chair.busyUntil}` }}
                        </span>
                      </button>
                    </li>
                  </ul>
                  <p
                    v-if="fieldError('dental_chair_id')"
                    class="text-xs text-error-500"
                    role="alert"
                  >
                    {{ fieldError('dental_chair_id') }}
                  </p>
                </div>
              </div>
            </template>

            <template v-else>
              <p class="text-xs text-theme-secondary" data-appointment-availability-hint>
                {{ pairsHint }}
              </p>

              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" data-appointment-manual-pick>
                <div role="group" :aria-labelledby="PROFESSIONAL_LABEL_ID">
                  <p
                    :id="PROFESSIONAL_LABEL_ID"
                    class="mb-1 text-sm font-medium text-theme-primary"
                  >
                    Profesional
                  </p>
                  <UiSelect
                    v-model="form.user_id"
                    :options="professionalOptions"
                    label="Profesional"
                    placeholder="Seleccionar profesional"
                    required
                    searchable
                    clearable
                    :error="fieldError('user_id')"
                    class="max-sm:[&_button]:min-h-[48px]"
                    @blur="onFieldBlur('user_id')"
                  />
                </div>
                <div role="group" :aria-labelledby="CHAIR_LABEL_ID">
                  <p :id="CHAIR_LABEL_ID" class="mb-1 text-sm font-medium text-theme-primary">
                    Ambiente
                  </p>
                  <UiSelect
                    v-model="form.dental_chair_id"
                    :options="dentalChairOptions"
                    label="Ambiente"
                    placeholder="Seleccionar ambiente"
                    required
                    searchable
                    clearable
                    :error="fieldError('dental_chair_id')"
                    class="max-sm:[&_button]:min-h-[48px]"
                    @blur="onFieldBlur('dental_chair_id')"
                  />
                </div>
              </div>
            </template>
          </section>

          <section class="space-y-2" data-appointment-section="type">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <p :id="APPOINTMENT_TYPE_LABEL_ID" class="text-sm font-semibold text-theme-primary">
                Tipo de cita
              </p>
              <div class="flex items-baseline gap-3">
                <span
                  v-if="professionalSpecialtySummary"
                  class="text-xs text-theme-secondary"
                  data-appointment-type-specialty
                >
                  {{ professionalSpecialtySummary }}
                </span>
                <button
                  type="button"
                  :aria-pressed="pressed(showAllTypes)"
                  class="text-xs font-medium underline underline-offset-2 focus:outline-none [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                  :class="
                    showAllTypes
                      ? 'text-accent-700'
                      : 'text-theme-secondary hover:text-theme-primary'
                  "
                  data-appointment-type-more
                  @click="showAllTypes = !showAllTypes"
                >
                  {{ showAllTypes ? 'Solo los frecuentes' : 'Otros tipos' }}
                </button>
              </div>
            </div>

            <div
              v-if="!showTypeCatalog"
              class="flex flex-wrap gap-1.5"
              role="group"
              :aria-labelledby="APPOINTMENT_TYPE_LABEL_ID"
            >
              <button
                v-for="type in chipTypes"
                :key="type.id"
                type="button"
                :aria-pressed="pressed(String(form.appointment_type_id) === String(type.id))"
                :aria-label="chipAriaLabel(type)"
                class="inline-flex min-h-[44px] max-w-full min-w-0 flex-col justify-center rounded-full border px-2.5 py-1 text-xs transition-colors max-sm:min-h-[48px] focus:outline-none [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                :class="
                  String(form.appointment_type_id) === String(type.id)
                    ? 'border-accent bg-accent text-white'
                    : 'border-separator bg-theme-surface-elevated text-theme-primary hover:border-systemGray-500'
                "
                :data-appointment-type-chip="type.id"
                @click="chooseType(type)"
              >
                <span class="flex min-w-0 items-baseline gap-2">
                  <span class="min-w-0 break-words">{{ type.name }}</span>
                  <span
                    v-if="type.default_duration_minutes"
                    class="text-xs"
                    :class="
                      String(form.appointment_type_id) === String(type.id)
                        ? 'text-white/80'
                        : 'text-theme-secondary'
                    "
                  >
                    {{ type.default_duration_minutes }}′
                  </span>
                </span>
                <span
                  v-if="chipShowsSpecialty"
                  class="text-xs"
                  :class="
                    String(form.appointment_type_id) === String(type.id)
                      ? 'text-white/80'
                      : 'text-theme-secondary'
                  "
                  data-appointment-chip-specialty
                >
                  {{ specialtyLabelOf(type) }}
                </span>
              </button>
            </div>

            <!-- El catálogo completo vive en el flujo, no en un menú flotante:
                 se expande dentro de la sección y empuja el contenido, así no
                 hay overlay que recortar ni sección partida por el borde. -->
            <div
              v-if="showTypeCatalog"
              class="min-w-0 space-y-2 rounded-ios border border-hairline bg-theme-surface-elevated p-2.5"
              role="group"
              :aria-labelledby="APPOINTMENT_TYPE_LABEL_ID"
              data-appointment-type-catalog
            >
              <div class="min-w-0 space-y-1.5">
                <div
                  v-for="group in typeGroups.groups"
                  :key="group.label"
                  class="min-w-0"
                  :data-appointment-type-group="group.label"
                >
                  <p class="text-xs font-medium uppercase tracking-wide text-theme-secondary">
                    {{ group.label }}
                  </p>
                  <ul class="mt-0.5 grid min-w-0 grid-cols-2 gap-0.5">
                    <li v-for="type in group.types" :key="type.id" class="min-w-0">
                      <button
                        type="button"
                        :aria-pressed="
                          pressed(String(form.appointment_type_id) === String(type.id))
                        "
                        :aria-label="chipAriaLabel(type)"
                        class="flex min-h-[32px] w-full min-w-0 items-center justify-between gap-2 rounded-md px-1.5 py-1 text-left text-xs transition-colors focus:outline-none [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                        :class="
                          String(form.appointment_type_id) === String(type.id)
                            ? 'bg-accent-100 font-medium text-theme-primary ring-1 ring-accent'
                            : 'text-theme-primary hover:bg-canvas'
                        "
                        :data-appointment-type-option="type.id"
                        @click="chooseType(type)"
                      >
                        <span class="min-w-0 break-words">{{ type.name }}</span>
                        <span
                          v-if="type.default_duration_minutes"
                          class="shrink-0 text-theme-secondary"
                        >
                          {{ type.default_duration_minutes }}′
                        </span>
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
              <p
                v-if="fieldError('appointment_type_id')"
                class="text-xs text-error-500"
                role="alert"
              >
                {{ fieldError('appointment_type_id') }}
              </p>
            </div>
          </section>

          <!-- 4 · Detalles: lo que casi nunca se toca -->
          <details
            class="border-t border-hairline pt-3"
            data-appointment-more
            @toggle="onMoreToggle"
          >
            <summary
              class="flex min-h-[44px] cursor-pointer list-none items-center gap-2 text-sm font-medium text-theme-secondary focus:outline-none [&:focus-visible]:[box-shadow:var(--focus-ring-default)] max-sm:min-h-[48px]"
            >
              <svg
                class="h-2.5 w-2.5 shrink-0 transition-transform motion-reduce:transition-none"
                :class="moreOpen ? 'rotate-90' : ''"
                viewBox="0 0 10 10"
                fill="none"
                stroke="currentColor"
                aria-hidden="true"
              >
                <path d="M3 1l4 4-4 4" stroke-width="1.6" stroke-linecap="round" />
              </svg>
              Más detalles
            </summary>

            <div class="grid gap-3 pt-3">
              <div>
                <p class="mb-1 text-sm font-medium text-theme-primary">
                  Procedimiento
                  <span class="text-xs font-normal text-theme-secondary">
                    opcional · ajusta duración e importe
                  </span>
                </p>
                <ProcedureQuickPicker
                  v-if="useClinicalPicker"
                  v-model="form.procedure_id"
                  :specialty="procedureSpecialtyFilter"
                  @select="onProcedureSelected"
                />
                <ProcedureCatalogPicker
                  v-else
                  v-model="form.procedure_id"
                  :specialty="procedureSpecialtyFilter"
                  @select="onProcedureSelected"
                />
                <p v-if="selectedProcedure" class="mt-1 text-xs text-theme-secondary">
                  Duración {{ selectedProcedure.default_duration_minutes }} min ·
                  {{ formatCurrency(selectedProcedure.default_cost) }}
                </p>
              </div>

              <UiInput
                v-model="form.notes"
                label="Notas"
                type="textarea"
                placeholder="Indicaciones para la atención"
                :error="fieldError('notes')"
                class="max-sm:[&_textarea]:min-h-[48px]"
                @blur="onFieldBlur('notes')"
              />
            </div>
          </details>

          <!-- 5 · Estado: ciclo de vida, solo al editar una cita existente -->
          <section v-if="isEditMode" class="space-y-1.5 border-t border-hairline pt-3">
            <div
              v-if="statusEditable"
              class="space-y-1"
              role="group"
              :aria-labelledby="STATUS_LABEL_ID"
            >
              <p :id="STATUS_LABEL_ID" class="text-sm font-medium text-theme-primary">
                Estado de la cita
              </p>
              <UiSelect
                v-model="form.status"
                :options="statusOptions"
                label="Estado de la cita"
                class="max-sm:[&_button]:min-h-[48px]"
                @change="onStatusChange"
              />
            </div>
            <p v-else class="flex flex-wrap items-center gap-2 text-sm text-theme-secondary">
              <UiStatusBadge
                v-bind="statusBadgeProps(form.status)"
                :label="statusLabel(form.status)"
              />
              <span>Este estado lo cambia el flujo de atención, no este formulario.</span>
            </p>

            <!-- #394: cancelar desde la edición exige el mismo motivo que la
                 puerta de estado; sin este campo el diálogo no tendría por
                 dónde pedirlo. -->
            <UiInput
              v-if="form.status === 'cancelled'"
              v-model="form.cancellation_reason"
              label="Motivo de la cancelación"
              placeholder="Indica el motivo de la cancelación"
              type="textarea"
              data-appointment-cancel-reason
              :error="fieldError('cancellation_reason')"
              @blur="onFieldBlur('cancellation_reason')"
            />
          </section>
        </div>
      </div>

      <!-- CITAS-CONF-001 — duplicate-key 422 from AppointmentService::createAppointment
               (the DB unique constraint `unique_user_time_slot` /
               `unique_chair_time_slot` fires on the second commit when two
               reception desks race on the same slot). Template-level
               mapping: a friendly `<UiStatusBadge variant="error">` is
               rendered instead of a 500 toast. -->
      <UiStatusBadge
        v-if="duplicateKeyError"
        variant="error"
        label="Otra mesa ya reservó este horario"
      />
    </form>

    <!-- 7 · Pie: el resumen es la confirmación. Una sola fila —el resumen a la
         izquierda y las acciones a la derecha— para que el diálogo no gaste
         una banda entera en repetir lo que ya dijo. -->
    <template #footer>
      <div
        v-if="!loadingData"
        class="flex w-full min-w-0 flex-wrap items-center justify-between gap-3"
      >
        <p
          class="min-w-0 flex-1 basis-56 break-words rounded-ios bg-theme-surface px-3 py-1.5 text-sm text-theme-primary"
          data-appointment-summary
        >
          {{ summaryLine }}
        </p>
        <div class="flex shrink-0 gap-3">
          <UiButton
            type="button"
            variant="secondary"
            class="max-sm:min-h-[48px]"
            @click="closeModal"
          >
            Cancelar
          </UiButton>
          <UiButton
            type="submit"
            form="appointment-form"
            class="max-sm:min-h-[48px]"
            :loading="creating"
          >
            {{ submitButtonText }}
          </UiButton>
        </div>
      </div>
    </template>
  </UiModal>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useApi } from '../../composables/useApi'
import {
  DAY_END,
  DAY_START,
  SLOT_MINUTES,
  buildAppointmentAvailability,
  buildHourGrid,
  groupAppointmentTypes,
  rankAppointmentTypes,
  useAppointmentAvailability
} from '../../composables/useAppointmentAvailability'
import { useFieldValidation } from '../../composables/useFieldValidation'
import { useOptionsTransform } from '../../composables/useOptionsTransform'
import { useToast } from '../../composables/useToast'
import { formatCurrency, formatDate, formatTime } from '../../composables/useFormatters'
import { statusBadgeProps, statusLabel } from '../../modules/appointments/appointmentStatus'
import UiButton from '../ui/Button.vue'
import UiInput from '../ui/Input.vue'
import UiSelect from '../ui/Select.vue'
import UiModal from '../ui/Modal.vue'
import UiStatusBadge from '../ui/StatusBadge.vue'
import ProcedureQuickPicker from '../procedures/ProcedureQuickPicker.vue'
import ProcedureCatalogPicker from '../procedures/ProcedureCatalogPicker.vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  initialDate: {
    type: String,
    default: null
  },
  // #531: el tablero ofrece sus huecos con el ambiente de la columna (y el
  // profesional, cuando agrupa por ellos). Los ids son una propuesta, no una
  // elección: el formulario los adopta solo si su catálogo los publica.
  initialChairId: {
    type: [String, Number],
    default: null
  },
  initialProfessionalId: {
    type: [String, Number],
    default: null
  },
  appointment: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue', 'created', 'updated'])

const PATIENT_SEARCH_ID = 'appointment-patient-search'
const PATIENT_LISTBOX_ID = 'appointment-patient-results'
const APPOINTMENT_TYPE_LABEL_ID = 'appointment-type-label'
const PAIR_LABEL_ID = 'appointment-pair-label'
const SUGGESTION_LABEL_ID = 'appointment-suggestion-label'
// `UiSelect` no pinta su prop `label` (solo la usa para nombrar el listado de
// opciones), así que cada selector declara su rótulo visible y el grupo que lo
// contiene queda nombrado por él.
const DURATION_LABEL_ID = 'appointment-duration-label'
const TIME_LABEL_ID = 'appointment-time-label'
const PROFESSIONAL_LABEL_ID = 'appointment-professional-label'
const CHAIR_LABEL_ID = 'appointment-chair-label'
const STATUS_LABEL_ID = 'appointment-status-label'

// Las duraciones que el alta ofrece. Un valor guardado fuera de la lista (una
// cita vieja de 25 min) se agrega en vivo para no mostrar el campo vacío.
const DURATION_VALUES = [15, 20, 30, 45, 60, 90, 120]

// Los estados que las dos puertas de escritura aceptan (`Store`/`Update`
// AppointmentRequest). Los estados de custodia (`arrived`, `in_consultation`,
// `completed`) los escribe el flujo de atención, no este formulario.
const WRITABLE_STATUSES = ['scheduled', 'confirmed', 'cancelled', 'no_show', 'rescheduled']

const HONORIFIC = /^(dr|dra|sr|sra|lic|mg|mtra)\.?$/i

const NO_SPECIALTY_LABEL = 'Sin especialidad'

const { get, post, put } = useApi()
const { transformProfessionals, transformDentalChairs } = useOptionsTransform()
const toast = useToast()
const {
  appointments: dayAppointments,
  status: availabilityStatus,
  load: loadDayAgenda
} = useAppointmentAvailability()

const loadingData = ref(false)
const creating = ref(false)
const error = ref(null)
const scheduleError = ref('')
const professionals = ref([])
const appointmentTypes = ref([])
const dentalChairs = ref([])

const form = reactive({
  patient_id: '',
  patient: null,
  user_id: '',
  date: '',
  time: '',
  duration_minutes: 30,
  appointment_type_id: '',
  procedure_id: null,
  selected_procedure: null,
  final_amount: null,
  dental_chair_id: '',
  status: 'scheduled',
  cancellation_reason: '',
  notes: ''
})

// Una duración propuesta por el tipo de cita (o por el procedimiento) no debe
// pisar la que el usuario eligió a mano.
let durationTouched = false

const patientQuery = ref('')
const patientResults = ref([])
const patientSearchState = ref('idle')
const patientSearchError = ref('')
const patientNote = ref('')
const showPatientResults = ref(false)
const activePatientIndex = ref(0)
let patientSearchTimer = null
let patientSearchRequest = 0

const showAllTypes = ref(false)
const moreOpen = ref(false)
const activeTimeBlock = ref('morning')
const hourGridRef = ref(null)

// La lista de equipo no desplaza: muestra cuatro filas y pagina. Un scroller
// dentro del diálogo corta una fila a la mitad —el defecto que esta ronda
// retira— y con nueve profesionales activos no hay alto de pantalla que los
// muestre todos junto al resto del alta.
const TEAM_PAGE_SIZE = 4
const teamPages = reactive({ professionals: 0, chairs: 0 })

const rules = {
  patient_id: [value => (value ? null : 'Elige el paciente de la cita.')],
  date: [value => (value ? null : 'Elige la fecha de la cita.')],
  time: [value => (value ? null : 'Elige la hora de inicio.')],
  duration_minutes: [
    value => (Number(value) >= 15 ? null : 'Elige una duración de al menos 15 minutos.')
  ],
  appointment_type_id: [value => (value ? null : 'Elige el tipo de cita.')],
  user_id: [value => (value ? null : 'Elige quién atiende la cita.')],
  dental_chair_id: [value => (value ? null : 'Elige el ambiente.')],
  notes: [
    value =>
      String(value || '').length <= 1000 ? null : 'Las notas no pueden exceder 1000 caracteres.'
  ],
  cancellation_reason: [
    (value, current) =>
      current.status === 'cancelled' && !String(value || '').trim()
        ? 'Indica el motivo de la cancelación.'
        : null
  ]
}

const RULE_FIELDS = Object.keys(rules)

const {
  errors: validationErrors,
  validateField,
  validateAll,
  applyServerErrors
} = useFieldValidation(form, rules, { idleMs: 250 })

const touched = reactive(Object.fromEntries(RULE_FIELDS.map(field => [field, false])))

const fieldError = field => (touched[field] ? validationErrors[field] || '' : '')

const onFieldBlur = field => {
  touched[field] = true
  validateField(field)
}

const revealAllErrors = () => {
  for (const field of RULE_FIELDS) touched[field] = true
}

// Opciones transformadas para UiSelect
const professionalOptions = computed(() => transformProfessionals(professionals.value))
const dentalChairOptions = computed(() => transformDentalChairs(dentalChairs.value))

const specialtyLabelOf = type =>
  type?.specialty?.name || type?.specialty?.code || NO_SPECIALTY_LABEL

const statusOptions = computed(() =>
  WRITABLE_STATUSES.map(value => ({ value, label: statusLabel(value) }))
)

const isEditMode = computed(() => !!props.appointment?.id)
const modalTitle = computed(() => (isEditMode.value ? 'Editar cita' : 'Nueva cita'))
const submitButtonText = computed(() => (isEditMode.value ? 'Guardar cambios' : 'Agendar cita'))

// CITAS-CONF-001 — duplicate-key heuristic. The DB unique constraints
// `unique_user_time_slot` / `unique_chair_time_slot` fire on the second
// concurrent commit; Laravel translates them into a 422 with the constraint
// name in the message, and (for the explicit API surface) `code === 'duplicate_key'`.
// Template-only mapping — the canonical error feedback (toast.error) still
// runs in the catch block; this ref drives the friendly inline badge.
const duplicateKeyError = computed(() => {
  const e = error.value
  if (!e) return false
  if (e.code === 'duplicate_key') return true
  const status = e?.response?.status ?? e?.status
  const message = String(e?.response?.data?.message ?? e?.message ?? '')
  return status === 422 && /unique_(user|chair)_time_slot/.test(message)
})

const selectedProcedure = computed(() => form.selected_procedure)
const procedureSpecialtyFilter = computed(() => {
  const user = professionals.value.find(p => p.id === form.user_id)
  return user?.specialty || ''
})
const useClinicalPicker = computed(() => {
  const user = professionals.value.find(p => p.id === form.user_id)
  if (!user) return true
  const clinicalRoles = ['odontologo', 'implantologo', 'tecnico_dental', 'asistente']
  return clinicalRoles.includes(user.role)
})

const statusEditable = computed(() => WRITABLE_STATUSES.includes(form.status))

const durationOptions = computed(() => {
  const values = [...DURATION_VALUES]
  const current = Number(form.duration_minutes)
  if (current > 0 && !values.includes(current)) values.push(current)
  return values
    .sort((left, right) => left - right)
    .map(minutes => ({
      value: minutes,
      label: `${minutes} min`
    }))
})

const selectedProfessional = computed(
  () => professionals.value.find(user => String(user.id) === String(form.user_id)) || null
)

const selectedChair = computed(
  () => dentalChairs.value.find(chair => String(chair.id) === String(form.dental_chair_id)) || null
)

// El tipo depende de la especialidad del profesional: los suyos al frente.
const typeGroups = computed(() =>
  groupAppointmentTypes(appointmentTypes.value, selectedProfessional.value)
)

// «Odontología general · 2 tipos»: el rótulo del bloque declara a qué
// especialidad pertenecen los atajos cuando hay un profesional elegido. Sin
// profesional (o sin tipos suyos en el catálogo) no se inventa nada.
const professionalSpecialtySummary = computed(() => {
  const label = typeGroups.value.specialtyLabel
  const total = typeGroups.value.preferred.length

  if (!label || total === 0) return ''

  return `${label} · ${total} ${total === 1 ? 'tipo' : 'tipos'}`
})

const chipTypes = computed(() => {
  const ranked = rankAppointmentTypes(
    typeGroups.value.preferred.length > 0 ? typeGroups.value.preferred : appointmentTypes.value,
    dayAppointments.value,
    4
  )

  return ranked
})

// Ruido justo: cuando todos los chips son de la misma especialidad, el rótulo
// del bloque lo dice una vez; cuando el conjunto mezcla especialidades, cada
// chip declara la suya.
const chipShowsSpecialty = computed(
  () => new Set(chipTypes.value.map(type => specialtyLabelOf(type))).size > 1
)

const chipAriaLabel = type => {
  const parts = [type.name, specialtyLabelOf(type)]
  if (type.default_duration_minutes) parts.push(`${type.default_duration_minutes} minutos`)
  return parts.join(', ')
}

// El catálogo se abre cuando el usuario lo pide, cuando no hay ningún atajo
// que ofrecer y cuando el tipo ya elegido no está entre los frecuentes (editar
// una cita de otro tipo tiene que mostrar dónde está).
const showTypeCatalog = computed(() => {
  if (showAllTypes.value) return true
  if (chipTypes.value.length === 0) return true
  if (!form.appointment_type_id) return false
  return !chipTypes.value.some(type => String(type.id) === String(form.appointment_type_id))
})

const pressed = condition => (condition ? 'true' : 'false')

const pad = value => String(value).padStart(2, '0')

const composeLocalDate = (dateValue, timeValue) => {
  if (!dateValue || !timeValue) return null
  const [year, month, day] = String(dateValue).split('-').map(Number)
  const [hours, minutes] = String(timeValue).split(':').map(Number)
  if ([year, month, day, hours, minutes].some(part => Number.isNaN(part))) return null
  const value = new Date(year, month - 1, day, hours, minutes, 0, 0)
  return Number.isNaN(value.getTime()) ? null : value
}

const windowStart = computed(() => composeLocalDate(form.date, form.time))

const windowEnd = computed(() => {
  const start = windowStart.value
  const minutes = Number(form.duration_minutes)
  if (!start || !(minutes > 0)) return null
  return new Date(start.getTime() + minutes * 60000)
})

const availability = computed(() =>
  buildAppointmentAvailability({
    appointments: dayAppointments.value,
    professionals: professionals.value,
    chairs: dentalChairs.value,
    start: windowStart.value,
    end: windowEnd.value,
    excludeAppointmentId: props.appointment?.id ?? null,
    // El atajo deja de ser atajo si ocupa media columna: dos pares bastan para
    // ahorrar las dos decisiones, y el tercero está a un clic en las listas.
    suggestionLimit: 2
  })
)

const availabilityProfessionals = computed(() => availability.value.professionals)
const availabilityChairs = computed(() => availability.value.chairs)
const suggestedPairs = computed(() => availability.value.suggestions)

const pageSlice = (rows, key) => {
  const page = teamPages[key] || 0
  const start = page * TEAM_PAGE_SIZE

  return rows.slice(start, start + TEAM_PAGE_SIZE)
}

const pagedProfessionals = computed(() =>
  pageSlice(availabilityProfessionals.value, 'professionals')
)
const pagedChairs = computed(() => pageSlice(availabilityChairs.value, 'chairs'))

const pagerLabel = (total, key) => {
  const page = teamPages[key] || 0
  const start = page * TEAM_PAGE_SIZE + 1
  const end = Math.min(total, (page + 1) * TEAM_PAGE_SIZE)

  return `${start}–${end}/${total}`
}

const shiftTeamPage = (key, total, step) => {
  const pages = Math.max(1, Math.ceil(total / TEAM_PAGE_SIZE))
  const page = teamPages[key] || 0

  teamPages[key] = (page + step + pages) % pages
}
const availabilityKnown = computed(
  () => availabilityStatus.value === 'ready' && !availability.value.unknown
)
const freePersonCount = computed(
  () => availabilityProfessionals.value.filter(person => person.free).length
)
const freeChairCount = computed(() => availabilityChairs.value.filter(chair => chair.free).length)
const pairsVisible = computed(
  () =>
    availabilityKnown.value &&
    (availabilityProfessionals.value.length > 0 || availabilityChairs.value.length > 0)
)

// La sección nunca promete disponibilidad que no leyó: sin agenda del día la
// salida es el par de selectores, y el texto lo dice.
const pairsHint = computed(() => {
  if (availabilityStatus.value === 'loading') return 'Leyendo la agenda del día…'
  // Sin fecha u hora no hay ventana que leer: pedirla, no culpar a la agenda.
  if (!windowStart.value) return 'Elige la fecha y la hora para ver quién está libre.'
  if (!endsAtLabel.value) return 'Elige la duración de la cita.'
  if (availabilityStatus.value === 'unknown') {
    return 'No se pudo leer la agenda del día. Elige el profesional y el ambiente a mano.'
  }
  return 'Elige la fecha, la hora y la duración para ver quién está libre.'
})

const windowLabel = computed(() => {
  const start = windowStart.value
  const end = windowEnd.value
  if (!start || !end) return ''
  return `${formatTime(start)} – ${formatTime(end)}`
})

const endsAtLabel = computed(() => {
  const end = windowEnd.value
  return end ? formatTime(end) : ''
})

const selectedPatient = computed(() => form.patient)

const initialsOf = name => {
  const words = String(name || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
  const meaningful = words.filter(word => !HONORIFIC.test(word))
  const source = meaningful.length > 0 ? meaningful : words
  return (
    source
      .slice(0, 2)
      .map(word => word.charAt(0).toUpperCase())
      .join('') || '··'
  )
}

const patientMeta = patient => {
  if (!patient) return ''
  const parts = []
  const document = patient.dni || patient.document_number
  if (document) parts.push(`DNI ${document}`)
  else if (patient.email) parts.push(patient.email)
  if (patient.age !== null && patient.age !== undefined && patient.age !== '') {
    parts.push(`${patient.age} años`)
  }
  return parts.join(' · ')
}

const selectedPatientName = computed(() => {
  const { patient } = form
  if (!patient) return form.patient_id ? `Paciente #${form.patient_id}` : ''
  return patient.name || [patient.first_name, patient.last_name].filter(Boolean).join(' ').trim()
})

const selectedPatientMeta = computed(() => patientMeta(form.patient))

const patientHint = computed(() => {
  if (!patientQuery.value) return ''
  if (patientSearchState.value === 'loading') return 'Buscando…'
  if (patientResults.value.length > 0) return `${patientResults.value.length} coincidencias`
  return 'Escribe al menos 2 caracteres del nombre o del documento.'
})

const patientError = computed(() => patientSearchError.value || fieldError('patient_id'))

const patientOptionId = index => `appointment-patient-option-${index}`

const activePatientOptionId = computed(() =>
  showPatientResults.value && patientResults.value.length > 0
    ? patientOptionId(activePatientIndex.value)
    : ''
)

const dateLabel = computed(() => {
  if (!form.date) return ''
  // `year: undefined` retira el año por defecto del formateador compartido: en
  // el pie la fecha se lee como parte de una frase, no como un sello.
  return formatDate(`${form.date}T12:00:00`, {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: undefined
  })
    .replace(/[.,]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
})

const timeRangeLabel = computed(() => {
  if (!form.time) return ''
  return endsAtLabel.value ? `${form.time}–${endsAtLabel.value}` : form.time
})

const summaryLine = computed(() => {
  const chunks = []
  if (selectedPatientName.value) chunks.push(selectedPatientName.value)
  const when = [dateLabel.value, timeRangeLabel.value].filter(Boolean).join(', ')
  if (when) chunks.push(when)
  const who = [selectedProfessional.value?.name, selectedChair.value?.name]
    .filter(Boolean)
    .join(' · ')
  if (who) chunks.push(who)
  return chunks.join(' · ') || 'Completa paciente, horario y quién atiende.'
})

// La combinación elegida, en una línea, sin prometer nada sobre su ocupación.
const combinationLine = computed(() => {
  const parts = []
  if (selectedProfessional.value) parts.push(selectedProfessional.value.name)
  if (selectedChair.value) {
    parts.push(`en ${selectedChair.value.name || selectedChair.value.code || 'el ambiente'}`)
  }
  const who = parts.join(' ')
  const when = timeRangeLabel.value
  if (!who && !when) return `Elige profesional y ambiente · ${freePersonCount.value} libres`
  return [who, when].filter(Boolean).join(' · ')
})

const chairLabel = chair => chair.name || chair.code || 'Ambiente'

const suggestionAriaLabel = pair => {
  const who = `${pair.user.name} en ${chairLabel(pair.chair)}`

  return pair.free ? `${who}, libre` : `${who}, ocupado hasta ${pair.busyUntil}`
}

const isPairSelected = pair =>
  String(form.user_id) === String(pair.user.id) &&
  String(form.dental_chair_id) === String(pair.chair.id)

const isProfessionalSelected = person => String(form.user_id) === String(person.id)

const isChairSelected = chair => String(form.dental_chair_id) === String(chair.id)

const pairRowClasses = pair => {
  if (!pair.free) return 'border-hairline bg-theme-surface text-theme-secondary cursor-not-allowed'
  if (isPairSelected(pair))
    return 'border-accent bg-accent-100 text-theme-primary ring-1 ring-accent'
  return 'border-separator bg-theme-surface-elevated text-theme-primary hover:border-systemGray-500'
}

const teamRowClasses = (resource, selected) => {
  if (!resource.free)
    return 'border-hairline bg-theme-surface text-theme-secondary cursor-not-allowed'
  if (selected) return 'border-accent bg-accent-100 ring-1 ring-accent'
  return 'border-separator bg-theme-surface-elevated hover:border-systemGray-500'
}

const hourSlotClasses = slot => {
  if (slot.disabled) return 'border-hairline bg-theme-surface text-theme-secondary'
  if (form.time === slot.value) return 'border-accent bg-accent text-white'
  return 'border-separator bg-theme-surface-elevated text-theme-primary hover:border-systemGray-500'
}

const selectPair = pair => {
  if (!pair.free) return
  form.user_id = pair.user.id
  form.dental_chair_id = pair.chair.id
  validateField('user_id')
  validateField('dental_chair_id')
}

const chooseProfessional = person => {
  if (!person.free) return
  form.user_id = person.id
  validateField('user_id')
}

const chooseChair = chair => {
  if (!chair.free) return
  form.dental_chair_id = chair.id
  validateField('dental_chair_id')
}

const chooseType = type => {
  form.appointment_type_id = type.id
  if (!durationTouched && type.default_duration_minutes) {
    form.duration_minutes = Number(type.default_duration_minutes)
  }
  validateField('appointment_type_id')
}

const onDurationChange = value => {
  form.duration_minutes = Number(value)
  durationTouched = true
  validateField('duration_minutes')
}

const onStatusChange = value => {
  form.status = value
  if (value !== 'cancelled') touched.cancellation_reason = false
}

const onMoreToggle = event => {
  moreOpen.value = Boolean(event?.target?.open)
}

const onProcedureSelected = proc => {
  form.selected_procedure = proc
  if (proc?.default_duration_minutes) {
    form.duration_minutes = Number(proc.default_duration_minutes)
    durationTouched = true
  }
  if (proc?.default_cost) {
    form.final_amount = Number(proc.default_cost)
  }
}

// --- Rejilla de horas -------------------------------------------------------

const hourGridReady = computed(() => Boolean(form.date) && Number(form.duration_minutes) >= 15)

const hourGridHint = computed(() => {
  if (!form.date) return 'Elige la fecha para ver las horas.'
  return 'Elige la duración para ver las horas.'
})

const hourGrid = computed(() =>
  buildHourGrid({
    appointments: dayAppointments.value,
    userId: form.user_id || null,
    chairId: form.dental_chair_id || null,
    durationMinutes: Number(form.duration_minutes) || 30,
    date: form.date || null,
    selectedTime: form.time || null,
    excludeAppointmentId: props.appointment?.id ?? null,
    dayStart: DAY_START,
    dayEnd: DAY_END,
    stepMinutes: SLOT_MINUTES,
    now: new Date()
  })
)

const activeHourRows = computed(() => {
  const block = hourGrid.value.blocks.find(candidate => candidate.id === activeTimeBlock.value)

  return block ? block.rows : []
})

const gridUnknownNote = computed(() => {
  if (availabilityStatus.value === 'loading') return 'Leyendo la agenda del día…'
  if (availabilityStatus.value === 'unknown') {
    return 'No se pudo leer la agenda del día: las horas ocupadas no se pueden marcar.'
  }
  return ''
})

const blockOfTime = value => {
  const slot = hourGrid.value.slots.find(candidate => candidate.value === value)
  return slot ? slot.block : null
}

const chooseTime = slot => {
  if (!slot || slot.disabled) return
  form.time = slot.value
  validateField('time')
}

// El alta nace con una hora válida: si la precarga cayó en el pasado (o el día
// cambió mientras el formulario estaba abierto), se adopta el primer hueco
// libre. El usuario conserva su elección mientras siga siendo futura.
const firstOpenSlot = computed(() => hourGrid.value.slots.find(slot => !slot.disabled) ?? null)

const settleChosenTime = () => {
  if (!props.modelValue || props.appointment) return
  if (!form.date) return

  const current = hourGrid.value.slots.find(slot => slot.value === form.time)
  if (current && !current.past) return

  const next = firstOpenSlot.value
  if (next) {
    form.time = next.value
    activeTimeBlock.value = next.block
  } else if (current?.past) {
    form.time = ''
  }
}

watch(
  () => [props.modelValue, form.date, form.time, form.duration_minutes, availabilityStatus.value],
  settleChosenTime,
  { flush: 'post' }
)

// Flechas mueven, Enter elige (el botón nativo ya responde a Enter/Espacio).
// El foco salta las casillas ocupadas: una flecha que aterriza en un control
// deshabilitado se lee como una flecha muerta.
const nextFocusableIndex = (from, step) => {
  const { slots } = hourGrid.value
  let cursor = from + step

  while (cursor >= 0 && cursor < slots.length) {
    if (!slots[cursor].disabled) return cursor
    cursor += step
  }

  return from
}

const focusSlot = index => {
  nextTick(() => {
    const node = hourGridRef.value?.querySelector(`[data-slot-index="${index}"]`)
    if (node && !node.disabled) node.focus()
  })
}

const onHourGridKeydown = event => {
  const steps = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: 4, ArrowUp: -4 }
  const step = steps[event.key]
  if (step === undefined) return

  const { slots } = hourGrid.value
  if (slots.length === 0) return

  // La flecha se mueve desde la casilla enfocada, no desde la elegida: moverse
  // para mirar no cambia el valor de la cita.
  const focusedIndex = Number(event.target?.dataset?.slotIndex)
  const chosen = slots.findIndex(slot => String(slot.value) === String(form.time))
  const origin = Number.isInteger(focusedIndex) ? focusedIndex : chosen >= 0 ? chosen : 0
  const target = nextFocusableIndex(origin, step)
  const slot = slots[target]
  if (!slot) return

  event.preventDefault()
  activeTimeBlock.value = slot.block
  focusSlot(target)
}

watch([availabilityProfessionals, availabilityChairs], () => {
  teamPages.professionals = 0
  teamPages.chairs = 0
})

const closePatientResults = () => {
  showPatientResults.value = false
}

const moveActivePatient = step => {
  if (patientResults.value.length === 0) return
  showPatientResults.value = true
  const total = patientResults.value.length
  activePatientIndex.value = (activePatientIndex.value + step + total) % total
}

const chooseActivePatient = () => {
  if (!showPatientResults.value) return
  const patient = patientResults.value[activePatientIndex.value]
  if (patient) selectPatient(patient)
}

const runPatientSearch = async term => {
  const query = String(term || '').trim()
  if (query.length < 2) return

  const request = ++patientSearchRequest
  patientSearchState.value = 'loading'

  try {
    const response = await get('/api/patients/search', { params: { q: query } })
    if (request !== patientSearchRequest) return

    const rows = Array.isArray(response?.data) ? response.data : []
    patientResults.value = rows
    activePatientIndex.value = 0
    showPatientResults.value = rows.length > 0
    patientSearchState.value = 'ready'
    patientSearchError.value = ''
    patientNote.value =
      rows.length === 0 ? 'No encontramos pacientes con ese nombre o documento.' : ''
  } catch {
    if (request !== patientSearchRequest) return

    patientResults.value = []
    showPatientResults.value = false
    patientSearchState.value = 'error'
    patientSearchError.value =
      'No se pudo buscar pacientes. Revisa la conexión e inténtalo otra vez.'
  }
}

const onPatientQuery = value => {
  patientQuery.value = value
  patientSearchError.value = ''
  patientNote.value = ''
  showPatientResults.value = String(value || '').trim().length >= 2

  clearTimeout(patientSearchTimer)
  patientSearchTimer = setTimeout(() => {
    void runPatientSearch(value)
  }, 300)

  if (String(value || '').trim().length < 2) {
    patientResults.value = []
    patientSearchState.value = 'idle'
  }
}

const selectPatient = patient => {
  if (!patient) return
  form.patient_id = patient.id
  form.patient = { ...patient }
  patientQuery.value = ''
  patientResults.value = []
  showPatientResults.value = false
  patientSearchError.value = ''
  patientNote.value = ''
  validateField('patient_id')
}

const changePatient = () => {
  form.patient = null
  form.patient_id = ''
  patientQuery.value = ''
  patientResults.value = []
  showPatientResults.value = false
  patientSearchError.value = ''
  patientNote.value = ''
  nextTick(() => {
    const input = document.getElementById(PATIENT_SEARCH_ID)
    if (input) input.focus()
  })
}

const splitDateTimeValue = value => {
  if (!value) return null

  const naive = String(value).match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/)
  if (naive) {
    return { date: `${naive[1]}-${naive[2]}-${naive[3]}`, time: `${naive[4]}:${naive[5]}` }
  }

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return null

  return {
    date: `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}`,
    time: `${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`
  }
}

const populateFormFromAppointment = appointment => {
  if (!appointment) return

  const start = splitDateTimeValue(appointment.scheduled_at)
  const { patient } = appointment

  form.patient_id = appointment.patient_id ?? patient?.id ?? ''
  form.patient = patient
    ? {
        id: patient.id,
        first_name: patient.first_name,
        last_name: patient.last_name,
        name: patient.full_name || '',
        document_number: patient.document_number,
        email: patient.email
      }
    : null
  form.user_id = appointment.user_id ?? appointment.user?.id ?? ''
  form.date = start?.date || ''
  form.time = start?.time || ''
  form.duration_minutes = appointment.duration_minutes ?? 30
  form.appointment_type_id =
    appointment.appointment_type_id ?? appointment.appointment_type?.id ?? ''
  form.procedure_id = appointment.procedure_id ?? null
  form.selected_procedure = appointment.procedure ?? null
  form.final_amount = appointment.final_amount ?? null
  form.dental_chair_id = appointment.dental_chair_id ?? appointment.dental_chair?.id ?? ''
  form.status = appointment.status || 'scheduled'
  form.cancellation_reason = appointment.cancellation_reason || ''
  form.notes = appointment.notes || ''

  // La duración guardada es una decisión tomada: un cambio de tipo no la pisa.
  durationTouched = true
  activeTimeBlock.value = blockOfTime(form.time) || activeTimeBlock.value
}

const closeModal = () => {
  emit('update:modelValue', false)
}

const resetForm = () => {
  error.value = null
  scheduleError.value = ''
  durationTouched = false
  showAllTypes.value = false
  moreOpen.value = false
  activeTimeBlock.value = 'morning'
  patientQuery.value = ''
  patientResults.value = []
  patientSearchState.value = 'idle'
  patientSearchError.value = ''
  patientNote.value = ''
  showPatientResults.value = false
  activePatientIndex.value = 0

  if (props.appointment) {
    populateFormFromAppointment(props.appointment)
    return
  }

  Object.assign(form, {
    patient_id: '',
    patient: null,
    user_id: '',
    date: '',
    time: '',
    duration_minutes: 30,
    appointment_type_id: '',
    procedure_id: null,
    selected_procedure: null,
    final_amount: null,
    dental_chair_id: '',
    status: 'scheduled',
    cancellation_reason: '',
    notes: ''
  })

  const prefill = splitDateTimeValue(props.initialDate)
  if (prefill) {
    form.date = prefill.date
    form.time = prefill.time
    activeTimeBlock.value = blockOfTime(prefill.time) || 'morning'
  }
}

const hasOption = (rows, id) => (rows || []).some(row => String(row?.id) === String(id))

// La precarga del recurso se aplica una sola vez y después del catálogo: un
// ambiente que el formulario no ofrece (inactivo, de otra sede) se ignora sin
// ruido, y lo que el usuario elija luego no se revierte porque nadie vuelve a
// escribir el campo.
const applyInitialTeam = () => {
  if (props.appointment) return

  const { initialChairId, initialProfessionalId } = props

  if (initialChairId && !form.dental_chair_id && hasOption(dentalChairs.value, initialChairId)) {
    form.dental_chair_id = initialChairId
  }

  if (
    initialProfessionalId &&
    !form.user_id &&
    hasOption(professionals.value, initialProfessionalId)
  ) {
    form.user_id = initialProfessionalId
  }
}

const loadData = async () => {
  loadingData.value = true
  try {
    // El paciente ya no se precarga: se busca por nombre o documento contra
    // `GET /api/patients/search` (mínimo 2 caracteres, máximo 20 filas).
    const [professionalsRes, typesRes, chairsRes] = await Promise.all([
      get('/api/users/active'),
      get('/api/appointment-types/active'),
      get('/api/dental-chairs/active?status=active')
    ])

    professionals.value = professionalsRes?.data || []
    appointmentTypes.value = typesRes?.data || []
    dentalChairs.value = chairsRes?.data || []

    applyInitialTeam()

    if (professionals.value.length === 0) {
      toast.warning('No se encontraron profesionales activos')
    }
    if (appointmentTypes.value.length === 0) {
      toast.warning('No se encontraron tipos de cita activos')
    }
    if (dentalChairs.value.length === 0) {
      toast.warning('No se encontraron sillas dentales activas')
    }
  } catch (error) {
    toast.error('Error al cargar los datos. Por favor, recarga la página.')
  } finally {
    loadingData.value = false
  }
}

const formatScheduledAtForApi = () => {
  const value = composeLocalDate(form.date, form.time)
  if (!value) return null
  return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}T${pad(
    value.getHours()
  )}:${pad(value.getMinutes())}:00`
}

/**
 * Lo que el padre necesita para confirmar la creación una sola vez: el id, el
 * día al que saltar y el resumen que el usuario acaba de leer.
 */
const createdPayload = (response, appointmentData) => {
  const created = response?.data || {}
  const id = created.id ?? created.data?.id ?? null

  return {
    ...appointmentData,
    id,
    date: form.date,
    time: form.time,
    ends_at: endsAtLabel.value,
    patient_name: selectedPatientName.value,
    professional_name: selectedProfessional.value?.name || '',
    chair_name: selectedChair.value?.name || '',
    summary: summaryLine.value
  }
}

const saveAppointment = async () => {
  creating.value = true
  scheduleError.value = ''
  error.value = null

  try {
    revealAllErrors()

    if (!validateAll()) return

    const scheduledAt = formatScheduledAtForApi()
    if (!scheduledAt) {
      scheduleError.value = 'La fecha y hora seleccionada no es válida.'
      return
    }

    if (!isEditMode.value) {
      const start = composeLocalDate(form.date, form.time)
      const minutesAhead = start ? Math.round((start.getTime() - Date.now()) / 60000) : 0
      if (minutesAhead < 1) {
        scheduleError.value = 'La cita debe empezar al menos un minuto después de ahora.'
        return
      }
    }

    const cancellationReason = String(form.cancellation_reason || '').trim()
    const appointmentData = {
      patient_id: form.patient_id,
      user_id: form.user_id,
      scheduled_at: scheduledAt,
      duration_minutes: Number(form.duration_minutes),
      appointment_type_id: form.appointment_type_id,
      dental_chair_id: form.dental_chair_id,
      notes: form.notes || null,
      cancellation_reason: form.status === 'cancelled' ? cancellationReason : null
    }

    // `status` solo viaja cuando es un estado escribible: uno de custodia lo
    // posee el flujo de atención y el PUT lo rechazaría.
    if (!isEditMode.value || statusEditable.value) {
      appointmentData.status = form.status
    }
    if (form.procedure_id) appointmentData.procedure_id = form.procedure_id
    if (form.final_amount) appointmentData.final_amount = form.final_amount

    let response
    if (isEditMode.value) {
      response = await put(`/api/appointments/${props.appointment.id}`, appointmentData)
      toast.success('Cita actualizada exitosamente')
      emit('updated', response?.data || appointmentData)
    } else {
      response = await post('/api/appointments', appointmentData)
      // La creación no confirma aquí: el padre es el único que habla, con una
      // sola línea y su acción a la agenda. Dos toasts por el mismo hecho es
      // justamente lo que esta entrega retira.
      emit('created', createdPayload(response, appointmentData))
    }

    resetForm()
    closeModal()
  } catch (error) {
    // CITAS-CONF-001 — expose the error to the template so the
    // `<UiStatusBadge variant="error">` duplicate-key mapping can render
    // the friendly "Otra mesa ya reservó este horario" badge. The toast
    // feedback below is the canonical UX path and is preserved.
    error.value = error
    const validation = error?.response?.data?.errors
    applyServerErrors(validation || {})
    scheduleError.value = firstServerMessage(validation?.scheduled_at)
    const validationMessages = validation ? Object.values(validation).flat() : []
    if (validationMessages.length > 0) {
      revealAllErrors()
      toast.error(`Errores de validación:\n${validationMessages.join('\n')}`)
    } else {
      toast.error(
        error?.response?.data?.message ||
          (isEditMode.value ? 'Error al actualizar la cita' : 'Error al crear la cita')
      )
    }
  } finally {
    creating.value = false
  }
}

const firstServerMessage = value => {
  if (!value) return ''
  if (Array.isArray(value)) return String(value[0] || '')
  return String(value)
}

// Cargar datos cuando el modal se abre
watch(
  () => props.modelValue,
  isOpen => {
    if (isOpen) {
      resetForm()
      loadData()
      loadDayAgenda(form.date)
    }
  }
)

watch(
  () => props.appointment,
  () => {
    if (props.modelValue && props.appointment) {
      populateFormFromAppointment(props.appointment)
      loadDayAgenda(form.date)
    }
  }
)

// La agenda del día es la fuente de la disponibilidad: cambiar de fecha
// invalida la lectura anterior en vez de dejar etiquetas de otro día.
watch(
  () => form.date,
  date => {
    if (props.modelValue) loadDayAgenda(date)
  }
)

// Una hora elegida fuera de la rejilla (prefill, edición) abre su bloque.
watch(
  () => form.time,
  value => {
    const block = blockOfTime(value)
    if (block) activeTimeBlock.value = block
  }
)

// Prellenar fecha inicial si se proporciona
watch(
  () => props.initialDate,
  newDate => {
    const prefill = splitDateTimeValue(newDate)
    if (prefill && props.modelValue) {
      form.date = prefill.date
      form.time = prefill.time
      activeTimeBlock.value = blockOfTime(prefill.time) || 'morning'
    }
  },
  { immediate: true }
)

onMounted(() => {
  if (props.modelValue) {
    resetForm()
    loadData()
    loadDayAgenda(form.date)
  }
})

onUnmounted(() => {
  clearTimeout(patientSearchTimer)
  patientSearchRequest++
})
</script>
