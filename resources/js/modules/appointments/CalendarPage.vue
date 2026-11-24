<template>
  <AppLayout>
    <!-- Marco único de la página (T5a): una sola banda compacta —cejilla de
         11 px, título de 24/32 y contexto de 12,5 px— con el segmentado, el
         stepper y las dos puertas de la agenda a la derecha. La fila de
         herramientas vive dentro de la misma banda y la banda cierra con
         hairline, sin tarjeta ni sombra: el contenido empieza por debajo de
         240 px medidos desde el chrome de AppLayout. -->
    <div data-calendar-frame class="border-b border-hairline pt-1 pb-2">
      <header
        data-calendar-header
        class="flex flex-col gap-2 md:flex-row md:flex-wrap md:items-start md:justify-between"
      >
        <div class="min-w-0 flex-1 md:min-w-[300px]">
          <p
            data-page-header-kicker
            data-calendar-kicker
            class="truncate text-[11px] font-medium uppercase leading-3 tracking-wide text-theme-secondary"
          >
            {{ headerKicker }}
          </p>
          <!-- T5b: el título nunca se corta. La fila del h1 conserva el ancho
               que necesita; los filtros ya no viven aquí. T6: en móvil
               envuelve (un título de 24 px no cabe en 390) y desde `md`
               vuelve a una sola línea. -->
          <h1
            data-page-header-title
            data-calendar-title
            class="whitespace-normal text-2xl font-semibold leading-8 text-label md:whitespace-nowrap"
          >
            {{ headerTitle }}
          </h1>
          <p
            data-page-header-subtitle
            data-calendar-context
            class="mt-1.5 whitespace-normal text-[12.5px] leading-4 text-theme-secondary"
            style="font-feature-settings: 'tnum' 1"
          >
            {{ headerContext }}
          </p>
        </div>
        <div
          class="flex shrink-0 flex-wrap items-center gap-2 md:justify-end"
          data-calendar-actions
        >
          <div
            class="inline-flex items-center gap-0.5 rounded-ios border border-hairline bg-theme-surface-elevated p-0.5"
            role="group"
            aria-label="Vista del calendario"
            data-calendar-view-switcher
          >
            <UiButton
              size="xs"
              class="min-h-[44px] lg:min-h-[28px]"
              :variant="currentView === 'day' ? 'primary' : 'ghost'"
              :aria-pressed="currentView === 'day'"
              data-calendar-view="day"
              @click="changeView('day')"
            >
              Día
            </UiButton>
            <UiButton
              size="xs"
              class="min-h-[44px] lg:min-h-[28px]"
              :variant="currentView === 'week' ? 'primary' : 'ghost'"
              :aria-pressed="currentView === 'week'"
              data-calendar-view="week"
              @click="changeView('week')"
            >
              Semana
            </UiButton>
            <UiButton
              size="xs"
              class="min-h-[44px] lg:min-h-[28px]"
              :variant="currentView === 'month' ? 'primary' : 'ghost'"
              :aria-pressed="currentView === 'month'"
              data-calendar-view="month"
              @click="changeView('month')"
            >
              Mes
            </UiButton>
          </div>
          <div class="inline-flex items-center gap-1">
            <UiButton
              variant="secondary"
              size="sm"
              aria-label="Periodo anterior"
              data-calendar-nav="prev"
              @click="previousPeriod"
            >
              <template #icon-left>
                <ChevronLeftIcon class="w-4 h-4" aria-hidden="true" />
              </template>
            </UiButton>
            <UiButton variant="secondary" size="sm" data-calendar-nav="today" @click="goToToday">
              Hoy
            </UiButton>
            <UiButton
              variant="secondary"
              size="sm"
              aria-label="Periodo siguiente"
              data-calendar-nav="next"
              @click="nextPeriod"
            >
              <template #icon-left>
                <ChevronRightIcon class="w-4 h-4" aria-hidden="true" />
              </template>
            </UiButton>
          </div>
          <UiButton
            variant="secondary"
            size="sm"
            aria-label="Actualizar la agenda"
            data-calendar-action="refresh"
            :disabled="loading"
            @click="refreshAgenda"
          >
            <template #icon-left>
              <ArrowPathIcon class="w-4 h-4" aria-hidden="true" />
            </template>
          </UiButton>
          <UiButton
            v-if="can.createAppointment?.value"
            size="sm"
            data-calendar-action="new-appointment"
            @click="openNewAppointmentModal"
          >
            <template #icon-left>
              <PlusIcon class="w-4 h-4" aria-hidden="true" />
            </template>
            Nueva cita
          </UiButton>
        </div>
      </header>

      <!-- Marco de herramientas (T5b): la franja de días va en su propia fila
           con celdas de 96 px como mínimo; debajo, una sola fila con los
           filtros compactos, las pastillas de profesional y la agrupación; al
           pie, la leyenda de cinco estados en una línea. -->
      <!-- T8/A: cada banda se separa con su margen —12 px de la franja a la fila
           de herramientas y 8 de ésta a la leyenda—, no con un hueco vacío. -->
      <div class="mt-5 flex flex-col" data-calendar-toolbar data-calendar-tools>
        <!-- T6 (ronda 2): siete columnas iguales desde `md`. En móvil la franja
             conserva su propio scroll horizontal con celdas de 96 px como
             mínimo; desde `md` es una rejilla `repeat(7, 1fr)` sin scroll y el
             contenido de cada celda ocupa su columna de borde a borde (día y
             número a la izquierda, recuento a la derecha), así la franja se lee
             como siete columnas y no como siete textos sueltos. -->
        <div v-if="currentView === 'day'" class="relative mb-3">
          <nav
            ref="stripScrollViewport"
            class="grid h-14 min-w-0 grid-flow-col auto-cols-[minmax(96px,1fr)] gap-1 overflow-x-auto md:grid-flow-row md:auto-cols-auto md:grid-cols-7 md:overflow-visible"
            aria-label="Días de la semana"
            data-calendar-week-strip
            @scroll.passive="readStripScrollState"
          >
            <button
              v-for="day in weekStripDays"
              :key="day.date"
              type="button"
              class="flex h-full min-w-[96px] flex-col items-center justify-center gap-1 overflow-hidden rounded-ios border px-3 py-2 text-center transition-colors [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
              :class="
                day.isSelected
                  ? 'border-accent-200 bg-theme-surface-elevated'
                  : 'border-transparent hover:border-hairline hover:bg-theme-surface-elevated'
              "
              :aria-current="day.isSelected ? 'date' : undefined"
              :aria-label="day.ariaLabel"
              :data-calendar-strip-day="day.date"
              :data-calendar-strip-selected="day.isSelected ? '' : null"
              :data-calendar-strip-count-value="day.count"
              @click="selectStripDay(day.date)"
            >
              <!-- Línea 1: sólo el día de la semana, centrado. -->
              <span class="flex min-w-0 items-center justify-center whitespace-nowrap leading-3">
                <span class="text-[11px] uppercase tracking-wide text-theme-secondary">
                  {{ day.name }}
                </span>
              </span>
              <!-- Línea 2: el número es el dato (17/650) y a su lado —pegados, en
                 la misma línea— la píldora HOY y el recuento de citas. El bloque
                 entero se centra en la celda, así que en una columna ancha no
                 queda pegado al borde izquierdo. -->
              <span class="flex min-w-0 items-center justify-center gap-1.5 leading-4">
                <span
                  class="text-[17px] font-[650] leading-none tabular-nums"
                  :class="day.isSelected ? 'text-accent-600' : 'text-theme-primary'"
                >
                  {{ day.number }}
                </span>
                <span
                  v-if="day.isToday"
                  class="whitespace-nowrap rounded-full bg-accent-500 px-1 py-0.5 text-[10px] font-semibold uppercase leading-none text-white"
                  data-calendar-strip-today
                >
                  hoy
                </span>
                <span
                  v-if="day.count > 0"
                  class="inline-flex min-w-[16px] items-center justify-center rounded-full px-1 text-[10px] font-semibold leading-4 tabular-nums"
                  :class="
                    day.isSelected
                      ? 'bg-accent-100 text-accent-700'
                      : 'bg-systemGray-100 text-theme-primary'
                  "
                  data-calendar-strip-count
                >
                  {{ day.count }}
                </span>
              </span>
            </button>
          </nav>
          <!-- Señal de «hay más días»: el degradado vive fuera del scroll para
               no desplazarse con las celdas. -->
          <span
            v-if="stripScrollLeft"
            class="pointer-events-none absolute inset-y-0 left-0 w-6 rounded-ios"
            data-calendar-strip-scroll-edge="left"
            aria-hidden="true"
            style="background: linear-gradient(to right, var(--color-canvas), transparent)"
          />
          <span
            v-if="stripScrollRight"
            class="pointer-events-none absolute inset-y-0 right-0 w-6 rounded-ios"
            data-calendar-strip-scroll-edge="right"
            aria-hidden="true"
            style="background: linear-gradient(to left, var(--color-canvas), transparent)"
          />
        </div>

        <!-- T6: por debajo de 1024 las herramientas ocupan dos filas —filtros,
             agrupación y limpiar arriba; las pastillas de profesional abajo,
             con su propio scroll— y desde `lg` vuelven a una sola. -->
        <div
          class="mb-2 flex min-w-0 flex-wrap items-center gap-3 lg:h-8 lg:flex-nowrap"
          data-calendar-filter-row
        >
          <div
            v-if="showChairFilter || showBranchFilter"
            class="flex shrink-0 items-center gap-2"
            role="group"
            aria-label="Filtros de la agenda"
            data-calendar-filters
          >
            <UiSelect
              v-if="showChairFilter"
              id="calendar-filter-chair"
              data-calendar-filter="chair"
              size="sm"
              class="w-[148px]"
              aria-label="Filtrar por ambiente"
              placeholder="Ambientes"
              :model-value="filters.dental_chair_id"
              :options="chairOptions"
              @update:model-value="value => setFilter('dental_chair_id', value)"
            />
            <UiSelect
              v-if="showBranchFilter"
              id="calendar-filter-branch"
              data-calendar-filter="branch"
              size="sm"
              class="w-[132px]"
              aria-label="Filtrar por sede"
              placeholder="Sedes"
              :model-value="filters.branch_id"
              :options="branchOptions"
              @update:model-value="value => setFilter('branch_id', value)"
            />
          </div>

          <!-- T2 — privacidad estricta: un escritorio clínico sólo opera sus
               citas, así que el filtro de profesional no tiene nada que
               recortar. Administración y recepción conservan el censo. -->
          <div
            v-if="seesWholeAgenda"
            class="flex w-full min-w-0 flex-nowrap items-center gap-2 overflow-x-auto lg:w-auto lg:flex-1"
            role="group"
            aria-label="Filtrar por profesional"
            data-calendar-professional-pills
          >
            <UiButton
              size="xs"
              class="h-8 px-3"
              :variant="filters.user_id ? 'secondary' : 'primary'"
              :aria-pressed="!filters.user_id"
              data-calendar-professional-pill="all"
              @click="setFilter('user_id', '')"
            >
              Todos
              <span class="tabular-nums" data-calendar-professional-count>
                {{ professionalPillTotal }}
              </span>
            </UiButton>
            <UiButton
              v-for="pill in professionalPills"
              :key="pill.value"
              size="xs"
              class="h-8 px-3"
              :variant="String(filters.user_id) === pill.value ? 'primary' : 'secondary'"
              :aria-pressed="String(filters.user_id) === pill.value"
              :data-calendar-professional-pill="pill.value"
              @click="setFilter('user_id', pill.value)"
            >
              {{ pill.label }}
              <span class="tabular-nums" data-calendar-professional-count>{{ pill.count }}</span>
            </UiButton>
          </div>

          <div
            v-if="currentView === 'day'"
            class="flex shrink-0 items-center gap-2"
            data-calendar-group-by-control
          >
            <span id="calendar-group-by-label" class="text-[11px] text-theme-secondary">
              Agrupar por
            </span>
            <div
              class="inline-flex items-center gap-0.5"
              role="group"
              aria-labelledby="calendar-group-by-label"
              data-calendar-group-by
            >
              <UiButton
                size="xs"
                :variant="groupBy === 'chair' ? 'primary' : 'ghost'"
                :aria-pressed="groupBy === 'chair'"
                data-calendar-group-by-option="chair"
                @click="setGroupBy('chair')"
              >
                Ambiente
              </UiButton>
              <UiButton
                size="xs"
                :variant="groupBy === 'professional' ? 'primary' : 'ghost'"
                :aria-pressed="groupBy === 'professional'"
                data-calendar-group-by-option="professional"
                @click="setGroupBy('professional')"
              >
                Profesional
              </UiButton>
            </div>
          </div>

          <UiButton
            v-if="hasActiveFilters"
            size="xs"
            variant="ghost"
            class="shrink-0"
            data-calendar-clear-filters
            @click="clearFilters"
          >
            <template #icon-left>
              <XMarkIcon class="w-3.5 h-3.5" aria-hidden="true" />
            </template>
            Limpiar filtros
          </UiButton>
        </div>

        <div class="relative min-w-0" data-calendar-legend-frame>
          <div
            ref="legendScrollViewport"
            class="flex h-[14px] min-w-0 items-center justify-start gap-4 overflow-x-auto lg:overflow-hidden"
            data-calendar-legend
            aria-label="Estados de la cita"
            @scroll.passive="readLegendScrollState"
          >
            <span
              class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-[10px] font-medium leading-[14px] text-systemGreen-700"
              aria-label="Actualizaciones en tiempo real activas"
              title="Actualizaciones en tiempo real activas"
              data-calendar-live
            >
              <span
                class="h-1.5 w-1.5 rounded-full bg-systemGreen-500 animate-pulse-subtle"
                aria-hidden="true"
              />
              En vivo
            </span>
            <span
              v-for="status in legendStatuses"
              :key="status"
              class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap text-[10px] leading-[14px] text-theme-secondary"
              :data-status="status"
            >
              <span
                class="h-[7px] w-[7px] rounded-full"
                :class="statusRailClasses(status).dot"
                aria-hidden="true"
              />
              {{ statusLabel(status) }}
            </span>
          </div>
          <span
            v-if="legendScrollLeft"
            class="pointer-events-none absolute inset-y-0 left-0 w-6"
            data-calendar-legend-scroll-edge="left"
            aria-hidden="true"
            style="background: linear-gradient(to right, var(--color-canvas), transparent)"
          />
          <span
            v-if="legendScrollRight"
            class="pointer-events-none absolute inset-y-0 right-0 w-6"
            data-calendar-legend-scroll-edge="right"
            aria-hidden="true"
            style="background: linear-gradient(to left, var(--color-canvas), transparent)"
          />
        </div>
      </div>
    </div>

    <!-- Una sola confirmación por cita creada. El modal no confirma, el aviso
         de difusión de la propia mesa no confirma: esta línea es la única voz,
         con el resumen de lo que se acaba de escribir y su acción. -->
    <div
      v-if="createdConfirmation"
      class="mt-4 flex flex-wrap items-center gap-3 rounded-ios border border-hairline bg-systemGreen-50 px-4 py-3"
      role="status"
      data-appointment-confirmation
      :data-appointment-confirmation-id="createdConfirmation.id"
    >
      <CheckCircleIcon class="h-5 w-5 shrink-0 text-systemGreen-700" aria-hidden="true" />
      <p class="min-w-0 flex-1 text-sm text-theme-primary">
        <span class="font-semibold">Cita agendada</span>
        <span v-if="createdConfirmation.summary">· {{ createdConfirmation.summary }}</span>
      </p>
      <UiButton
        variant="secondary"
        size="sm"
        data-appointment-confirmation-action
        @click="revealCreatedAppointment"
      >
        Ver en la agenda
      </UiButton>
      <UiButton
        variant="ghost"
        size="sm"
        data-appointment-confirmation-dismiss
        @click="dismissCreatedConfirmation"
      >
        Cerrar
      </UiButton>
    </div>

    <!-- Calendar Content: quiet surface (canvas + hairline + card radius +
         elevation-2). La banda compacta de arriba ya no deja una leyenda
         propia dentro del contenido: el único chrome de la superficie es su
         hairline.

         T6 — un solo lenguaje de esquinas: esta tarjeta es la de 16 px
         (`--radius-card-lg`), recorta con `overflow-hidden` la rejilla y su
         franja de cabecera, y todo lo que vive dentro (bloques, huecos, chips)
         usa el radio de control de 10 px. Antes convivían 22, una clase
         inexistente (`rounded-card`, que no genera CSS) y ángulos rectos. -->
    <section
      data-calendar-shell="grid"
      class="mt-5 overflow-hidden bg-canvas"
      style="
        border: 1px solid var(--color-hairline);
        border-radius: var(--radius-card-lg);
        box-shadow: var(--elevation-2);
      "
    >
      <!-- Loading State: the first frame is a skeleton of the surface it is
           waiting for (strip + board), never an empty agenda. -->
      <div v-if="loading" class="p-4 sm:p-6" role="status" data-calendar-loading>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-7" aria-hidden="true">
          <UiSkeleton
            v-for="index in 7"
            :key="`strip-${index}`"
            variant="rectangular"
            height="76px"
          />
        </div>
        <p class="mt-4 text-sm text-theme-secondary">Cargando agenda...</p>
        <div class="mt-3 grid gap-3" aria-hidden="true">
          <UiSkeleton variant="rectangular" height="96px" />
          <UiSkeleton variant="rectangular" height="320px" />
        </div>
      </div>

      <!-- Load error: a failed range read must never masquerade as a free agenda -->
      <div
        v-else-if="loadError"
        data-calendar-error
        role="alert"
        class="flex flex-wrap items-center gap-4 p-4 rounded-ios bg-systemRed-50 border border-hairline"
      >
        <ExclamationTriangleIcon
          class="h-8 w-8 flex-shrink-0 text-systemRed-600"
          aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium text-theme-primary">No pudimos cargar las citas</p>
          <p class="mt-1 text-sm text-theme-secondary" data-calendar-error-message>
            {{ loadError }}
          </p>
        </div>
        <UiButton variant="primary" size="sm" data-calendar-retry @click="loadAppointments">
          Reintentar
        </UiButton>
      </div>

      <!-- Empty State (D1/D9) -->
      <!-- T8: pieza de la superficie. Su marco es la tarjeta que la aloja, así
           que aquí no se pinta otra: un solo borde redondeado por superficie. -->
      <div
        v-else-if="appointments.length === 0"
        data-calendar-empty
        :data-calendar-empty-scope="hasActiveFilters ? 'filtered' : 'period'"
        class="py-6"
      >
        <CalendarEmptyBlock :title="emptyStateCopy.title" :description="emptyStateCopy.description">
          <UiButton
            v-if="hasActiveFilters"
            size="sm"
            variant="secondary"
            data-calendar-clear-filters
            data-calendar-empty-clear-filters
            @click="clearFilters"
          >
            Limpiar filtros
          </UiButton>
          <UiButton
            v-if="can.createAppointment?.value"
            size="sm"
            data-calendar-action="empty-new-appointment"
            @click="openNewAppointmentModal"
          >
            Nueva cita
          </UiButton>
        </CalendarEmptyBlock>
      </div>

      <!-- Day View -->
      <!-- Tablero por ambiente o profesional: la franja de días, las pastillas
           y la agrupación viven en la banda de herramientas de la página; aquí
           sólo queda el tablero y su panel de pendientes. -->
      <div v-else-if="currentView === 'day'" class="p-4 sm:p-6" data-calendar-day-view>
        <!-- T6: el tablero se queda con todo el ancho disponible. La columna del
             panel sólo existe cuando hay algo que leer en él —sin secciones, el
             `aside` no monta y la rejilla se queda en una sola columna— y, con
             panel, aparece a un lado desde 1600 px; por debajo el panel va
             debajo del tablero. -->
        <div
          class="grid gap-4"
          :class="hasPendingPanel ? 'min-[1600px]:grid-cols-[minmax(0,1fr)_288px]' : ''"
          :data-calendar-board-layout="hasPendingPanel ? 'split-from-1600' : 'single'"
        >
          <section
            class="min-w-0 overflow-hidden border border-hairline bg-theme-surface-elevated"
            style="border-radius: var(--radius-card-lg)"
            aria-label="Tablero del día"
            data-calendar-board
          >
            <!-- Día libre: UN bloque centrado con su mensaje y su acción. Las
                 columnas no se pintan, así que ninguna declara vacío por su
                 cuenta y la superficie no repite la frase. -->
            <template v-if="dayEmpty">
              <CalendarEmptyBlock
                data-calendar-day-empty
                class="my-3 sm:my-4"
                :title="emptyStateCopy.title"
                :description="emptyStateCopy.description"
              >
                <UiButton
                  v-if="can.createAppointment?.value"
                  size="sm"
                  data-calendar-action="empty-day-new-appointment"
                  @click="openNewAppointmentModal"
                >
                  Nueva cita
                </UiButton>
                <UiButton
                  v-if="hasActiveFilters"
                  size="sm"
                  variant="secondary"
                  data-calendar-clear-filters
                  @click="clearFilters"
                >
                  Limpiar filtros
                </UiButton>
              </CalendarEmptyBlock>
            </template>
            <template v-else>
              <!-- Scroll propio del tablero: la cabecera de columnas y el gutter de
                   horas quedan fijos mientras el cuerpo se desplaza. -->
              <div class="relative">
                <div
                  ref="boardScrollViewport"
                  class="overflow-auto"
                  data-calendar-board-scroll
                  :data-calendar-board-columns="boardColumns.length"
                  :data-calendar-board-column-min="boardColumnMinPx"
                  :data-calendar-board-gutter="boardGutterPx"
                  :data-calendar-board-min-width="boardGridMinWidth"
                  :style="calendarScrollMaxHeight"
                  @scroll.passive="readBoardScrollState"
                >
                  <div class="relative" :style="{ minWidth: `${boardGridMinWidth}px` }">
                    <div
                      class="sticky top-0 z-30 grid border-b border-hairline bg-theme-surface-elevated"
                      :style="boardGridStyle"
                    >
                      <div
                        class="sticky left-0 z-40 grid place-items-end bg-theme-surface-elevated px-2 pb-1.5 pt-2"
                        aria-hidden="true"
                      >
                        <span
                          class="text-xs font-medium uppercase tracking-wide text-theme-secondary"
                        >
                          Hora
                        </span>
                      </div>
                      <div
                        v-for="column in boardColumns"
                        :key="column.key"
                        class="relative border-l border-hairline px-3.5 py-3"
                        :data-calendar-board-column="column.key"
                        :data-calendar-column-code="column.meta || null"
                        :title="columnHeaderTitle(column)"
                      >
                        <!-- Dos líneas y nada más: el nombre en 13/650 y el estado
                           en 11 con su punto. El código del ambiente ya no
                           compite por el ancho: vive en el `title` de la celda y
                           en `data-calendar-column-code`. La ocupación queda como
                           arista de 3 px al pie, sin robarle ancho al texto. -->
                        <p
                          class="min-w-0 break-words text-[13px] font-[650] leading-tight text-theme-primary"
                          :data-calendar-column-name="column.key"
                        >
                          {{ column.name }}
                        </p>
                        <p
                          class="mt-0.5 flex min-w-0 items-center gap-1 text-[11px] font-medium leading-tight"
                          :class="column.stateTone.label"
                        >
                          <span
                            class="h-1.5 w-1.5 shrink-0 rounded-full"
                            :class="column.stateTone.dot"
                            aria-hidden="true"
                          />
                          <span
                            class="min-w-0"
                            :data-calendar-column-state="column.stateTone.tone"
                            :title="column.stateLabel"
                          >
                            {{ column.stateLabel }}
                          </span>
                        </p>
                        <span
                          class="absolute inset-x-0 bottom-0 h-[3px] overflow-hidden bg-systemGray-100"
                          role="progressbar"
                          :aria-valuenow="column.occupancyPercent"
                          aria-valuemin="0"
                          aria-valuemax="100"
                          :aria-label="
                            column.meta
                              ? `Ocupación de ${column.name} (${column.meta})`
                              : `Ocupación de ${column.name}`
                          "
                          :title="`Ocupación: ${column.occupancyPercent}%`"
                        >
                          <span
                            class="block h-full bg-accent-500"
                            :style="{ width: `${column.occupancyPercent}%` }"
                          />
                        </span>
                      </div>
                    </div>

                    <div class="relative grid" :style="boardGridStyle">
                      <div
                        class="sticky left-0 z-20 border-r border-hairline bg-theme-surface-elevated"
                        aria-hidden="true"
                        :style="{ height: `${boardBodyHeight}px` }"
                      >
                        <!-- La etiqueta de la hora que la píldora de «ahora»
                           puede tapar no se pinta: cero solape medido. -->
                        <template v-for="(hour, index) in boardHourLabels" :key="hour">
                          <span
                            v-if="!hourLabelHidden(index)"
                            class="absolute right-2 text-xs tabular-nums text-theme-secondary"
                            :style="boardHourLabelStyle(index)"
                            :data-calendar-hour-label="hour"
                          >
                            {{ formatHour(hour) }}
                          </span>
                        </template>
                      </div>
                      <div
                        v-for="column in boardColumns"
                        :key="column.key"
                        class="relative border-l border-hairline"
                        :data-calendar-board-column-body="column.key"
                        :style="{ height: `${boardBodyHeight}px` }"
                      >
                        <span
                          v-for="(hour, index) in boardHourLabels"
                          :key="`line-${hour}`"
                          class="pointer-events-none absolute inset-x-0 border-t border-hairline"
                          :style="{ top: `${index * boardHourPx}px` }"
                          aria-hidden="true"
                        />

                        <!-- Regla de huecos (T5a): sólo un hueco de 30 a 120 min
                           se ofrece como botón; uno mayor no pinta texto ni el
                           gancho de inicio agendable. -->
                        <template v-for="slot in column.freeGaps" :key="`gap-${slot.startMinutes}`">
                          <button
                            v-if="can.createAppointment?.value && gapIsBookable(slot)"
                            type="button"
                            class="absolute inset-x-1 flex items-center justify-between gap-2 overflow-hidden rounded-ios border border-dashed border-systemGray-300 bg-theme-surface px-2 text-left text-xs font-semibold text-theme-secondary transition-colors hover:border-accent-200 hover:bg-accent-50 hover:text-accent-700 [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                            :style="{ top: `${slot.top}px`, height: `${slot.height}px` }"
                            :data-calendar-slot="`${dayDateString}-${slot.hour}`"
                            :data-calendar-free-slot="`${slot.startMinutes}`"
                            :data-calendar-free-slot-start="timeAtMinutes(slot.startMinutes)"
                            :data-calendar-free-slot-minutes="`${slot.minutes}`"
                            :aria-label="freeSlotLabel(column, slot)"
                            @click="openSlotCreate(dayDateString, slot.hour, slot.minute, column)"
                          >
                            <span class="whitespace-nowrap text-[11px] font-semibold">
                              Libre · {{ slot.minutes }} min
                            </span>
                            <span
                              class="shrink-0 text-[11px] font-semibold text-accent-600"
                              aria-hidden="true"
                            >
                              +
                            </span>
                          </button>
                          <!-- Un hueco que no se ofrece no imprime nada: la
                             columna no declara vacío por su cuenta. El nodo se
                             queda sólo como medida del hueco. -->
                          <span
                            v-else-if="!gapIsBookable(slot)"
                            class="pointer-events-none hidden"
                            aria-hidden="true"
                            :data-calendar-free-gap="`${slot.startMinutes}`"
                            :data-calendar-free-gap-minutes="`${slot.minutes}`"
                          />
                        </template>

                        <button
                          v-for="appointment in column.visibleAppointments"
                          :key="appointment.id"
                          type="button"
                          class="absolute overflow-hidden rounded-ios text-left shadow-subtle transition-shadow hover:shadow-soft [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                          :class="getAppointmentClasses(appointment)"
                          :style="
                            boardBlockStyle(appointment, column.blockSlots.get(appointment.id))
                          "
                          :title="chipTitle(appointment)"
                          :aria-label="boardBlockLabel(appointment)"
                          :data-calendar-appointment="appointment.id"
                          :data-appointment-created="
                            createdHighlightId === appointment.id ? '' : null
                          "
                          :data-calendar-block-tier="boardBlockTier(appointment)"
                          @click="selectAppointment(appointment)"
                        >
                          <span
                            class="absolute inset-y-0 left-0 w-1"
                            :class="statusRailClasses(appointment.status).dot"
                            aria-hidden="true"
                          />
                          <span
                            class="flex h-full min-h-0 flex-col gap-0.5 py-1 pl-2 pr-1"
                            :class="
                              boardBlockTier(appointment) === 'compact'
                                ? 'justify-center'
                                : 'justify-start'
                            "
                          >
                            <!-- Anatomía del bloque (compartida con la semana):
                               hora de 11, paciente 12.5/650 y el tipo de cita en
                               11 cuando el alto lo permite. Un bloque compacto
                               imprime hora y paciente en una sola línea. -->
                            <span
                              v-if="boardBlockTier(appointment) === 'compact'"
                              class="flex min-w-0 items-center gap-1.5"
                              data-calendar-block-line
                            >
                              <span
                                class="shrink-0 text-[11px] leading-3 tabular-nums text-theme-secondary"
                                data-calendar-block-time
                              >
                                {{ boardBlockTimeLabel(appointment) }}
                              </span>
                              <span
                                class="truncate text-[12.5px] font-[650] leading-tight"
                                :class="statusBlockTitleClasses(appointment.status)"
                                data-calendar-block-patient
                              >
                                {{ appointmentPatientName(appointment) }}
                              </span>
                            </span>
                            <template v-else>
                              <span
                                class="truncate text-[11px] leading-3 tabular-nums text-theme-secondary"
                                data-calendar-block-time
                              >
                                {{ boardBlockTimeLabel(appointment) }}
                              </span>
                              <span
                                class="text-[12.5px] font-[650] leading-tight"
                                :class="[
                                  statusBlockTitleClasses(appointment.status),
                                  blockAllowsTwoLines(appointment) ? 'line-clamp-2' : 'truncate'
                                ]"
                                data-calendar-block-patient
                              >
                                {{ appointmentPatientName(appointment) }}
                              </span>
                              <span
                                v-if="boardBlockMetaVisible(appointment)"
                                class="truncate text-[11px] leading-4"
                                :class="statusBlockMetaClasses(appointment.status)"
                                data-calendar-block-meta
                              >
                                {{ boardBlockMeta(appointment) }}
                              </span>
                              <span
                                v-if="boardBlockFooter(appointment)"
                                class="mt-auto truncate text-[11px] text-theme-secondary"
                                data-calendar-block-footer
                              >
                                {{ boardBlockFooter(appointment) }}
                              </span>
                            </template>
                          </span>
                        </button>

                        <!-- T8: lo que no cabe en los tres carriles se agrupa
                             aqui, en el carril reservado, en vez de apilarse. -->
                        <span
                          v-for="indicator in column.overlapIndicators"
                          :key="`overlap-${indicator.slot}-${indicator.startMinutes}`"
                          class="absolute z-20 flex items-center justify-center overflow-hidden rounded-ios bg-theme-surface-elevated text-[11px] font-semibold tabular-nums text-theme-secondary shadow-subtle"
                          :style="overlapIndicatorStyle(indicator, boardWindowRange)"
                          :title="`${indicator.count} citas más a esa hora`"
                          :aria-label="`${indicator.count} citas más en este ambiente a esa hora`"
                          data-calendar-overlap-more
                        >
                          +{{ indicator.count }}
                        </span>
                      </div>

                      <div
                        v-if="showNowLine"
                        class="pointer-events-none absolute right-0 z-10 border-t-2 border-accent-500"
                        :style="{ left: `${boardGutterPx}px`, top: `${nowLineTop}px` }"
                        data-calendar-now-line
                        aria-hidden="true"
                      >
                        <span
                          class="absolute -top-2.5 left-1 rounded-ios bg-accent-500 px-1.5 py-0.5 text-xs font-semibold tabular-nums text-white"
                          data-calendar-now-pill
                        >
                          {{ nowLabel }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- El degradado vive fuera del scroll: señala que hay más
                     recursos y se retira al llegar al final. -->
                <span
                  v-if="boardScrollLeft"
                  class="pointer-events-none absolute inset-y-0 left-0 w-8"
                  data-calendar-board-scroll-edge="left"
                  aria-hidden="true"
                  style="background: linear-gradient(to right, var(--color-canvas), transparent)"
                />
                <span
                  v-if="boardScrollRight"
                  class="pointer-events-none absolute inset-y-0 right-0 w-8"
                  data-calendar-board-scroll-edge="right"
                  aria-hidden="true"
                  style="background: linear-gradient(to left, var(--color-canvas), transparent)"
                />
              </div>
              <p
                v-if="boardScrollRight"
                class="flex items-center justify-end gap-1 border-t border-hairline px-4 py-2 text-xs font-medium text-theme-secondary"
                data-calendar-board-scroll-affordance
                aria-hidden="true"
              >
                <ChevronRightIcon class="h-4 w-4" aria-hidden="true" />
                Desliza para ver todos los recursos
              </p>
            </template>
          </section>

          <aside
            v-if="hasPendingPanel"
            class="flex min-w-0 flex-col gap-3"
            aria-label="Pendientes del turno"
            data-calendar-pending
          >
            <section
              v-if="arrivedToday.length > 0"
              class="border border-hairline bg-theme-surface-elevated p-3.5"
              style="border-radius: var(--radius-card-lg)"
              aria-label="En sala ahora"
              data-calendar-pending-section="arrived"
            >
              <div class="flex items-center gap-2 border-b border-hairline pb-2.5">
                <h2 class="text-sm font-semibold text-theme-primary">En sala ahora</h2>
                <UiBadge
                  :variant="panelCountVariant('arrived')"
                  shape="pill"
                  size="sm"
                  class="ml-auto"
                >
                  {{ arrivedToday.length }}
                </UiBadge>
              </div>
              <ul>
                <li
                  v-for="appointment in arrivedToday"
                  :key="appointment.id"
                  class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-2 gap-y-1.5 border-b border-hairline py-2.5 last:border-b-0 last:pb-0"
                >
                  <UiAvatar
                    :initials="patientInitials(appointment)"
                    size="sm"
                    class="row-span-2 self-start"
                    aria-hidden="true"
                  />
                  <div class="min-w-0">
                    <p class="text-sm font-semibold leading-tight text-theme-primary">
                      {{ appointmentPatientName(appointment) }}
                    </p>
                    <p class="mt-0.5 truncate text-[11px] tabular-nums text-systemIndigo-700">
                      {{ waitingLine(appointment) }}
                    </p>
                  </div>
                  <UiBadge
                    v-if="appointment.announced_at"
                    variant="success"
                    shape="pill"
                    size="sm"
                    class="col-start-2 justify-self-start"
                    data-calendar-announced
                  >
                    Anunciado
                  </UiBadge>
                  <UiButton
                    v-else-if="can.announcePatient?.value"
                    size="xs"
                    class="col-start-2 min-h-[44px] justify-self-start lg:min-h-[28px]"
                    :data-calendar-announce="appointment.id"
                    @click="announcePatient(appointment)"
                  >
                    Anunciar
                  </UiButton>
                </li>
              </ul>
            </section>

            <section
              v-if="unconfirmedToday.length > 0"
              class="border border-hairline bg-theme-surface-elevated p-3.5"
              style="border-radius: var(--radius-card-lg)"
              aria-label="Sin confirmar"
              data-calendar-pending-section="scheduled"
            >
              <div class="flex items-center gap-2 border-b border-hairline pb-2.5">
                <h2 class="text-sm font-semibold text-theme-primary">Sin confirmar</h2>
                <UiBadge
                  :variant="panelCountVariant('scheduled')"
                  shape="pill"
                  size="sm"
                  class="ml-auto"
                >
                  {{ unconfirmedToday.length }}
                </UiBadge>
              </div>
              <ul>
                <li
                  v-for="appointment in unconfirmedToday"
                  :key="appointment.id"
                  class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-2 gap-y-1.5 border-b border-hairline py-2.5 last:border-b-0 last:pb-0"
                >
                  <UiAvatar
                    :initials="patientInitials(appointment)"
                    size="sm"
                    class="row-span-2 self-start"
                    aria-hidden="true"
                  />
                  <div class="min-w-0">
                    <p class="text-sm font-semibold leading-tight text-theme-primary">
                      {{ appointmentPatientName(appointment) }}
                    </p>
                    <p class="mt-0.5 truncate text-[11px] tabular-nums text-theme-secondary">
                      {{ formatTime(appointment.scheduled_at) }} · sin confirmar
                    </p>
                  </div>
                  <div
                    v-if="canConfirmAppointment"
                    class="col-start-2 flex flex-wrap items-center gap-1.5 justify-self-start"
                  >
                    <UiButton
                      size="xs"
                      class="min-h-[44px] lg:min-h-[28px]"
                      :loading="confirmingId === appointment.id"
                      :data-calendar-confirm="appointment.id"
                      @click="confirmAppointment(appointment)"
                    >
                      Confirmar
                    </UiButton>
                    <UiButton
                      size="xs"
                      variant="secondary"
                      class="min-h-[44px] lg:min-h-[28px]"
                      :data-calendar-open-appointment="appointment.id"
                      @click="openAppointmentFromPanel(appointment)"
                    >
                      Ver
                    </UiButton>
                  </div>
                  <UiButton
                    v-else
                    size="xs"
                    variant="secondary"
                    class="col-start-2 min-h-[44px] justify-self-start lg:min-h-[28px]"
                    :data-calendar-open-appointment="appointment.id"
                    @click="openAppointmentFromPanel(appointment)"
                  >
                    Ver
                  </UiButton>
                </li>
              </ul>
              <p
                v-if="confirmError"
                class="mt-2 text-[11px] font-medium text-error-600"
                role="alert"
                data-calendar-confirm-error
              >
                {{ confirmError }}
              </p>
            </section>

            <section
              v-if="labReadyToday.length > 0"
              class="border border-hairline bg-theme-surface-elevated p-3.5"
              style="border-radius: var(--radius-card-lg)"
              aria-label="Laboratorio listo"
              data-calendar-pending-section="lab"
            >
              <div class="flex items-center gap-2 border-b border-hairline pb-2.5">
                <h2 class="text-sm font-semibold text-theme-primary">Laboratorio listo</h2>
                <UiBadge :variant="panelCountVariant('lab')" shape="pill" size="sm" class="ml-auto">
                  {{ labReadyToday.length }}
                </UiBadge>
              </div>
              <ul>
                <li
                  v-for="labCase in labReadyToday"
                  :key="labCase.id"
                  class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-2 gap-y-1.5 border-b border-hairline py-2.5 last:border-b-0 last:pb-0"
                >
                  <UiAvatar
                    :initials="labCaseInitials(labCase)"
                    size="sm"
                    class="row-span-2 self-start"
                    aria-hidden="true"
                  />
                  <div class="min-w-0">
                    <p class="text-sm font-semibold leading-tight text-theme-primary">
                      {{ labCasePatientName(labCase) }}
                    </p>
                    <p class="mt-0.5 truncate text-[11px] text-theme-secondary">
                      {{ labCaseLine(labCase) }}
                    </p>
                  </div>
                  <RouterLink
                    to="/lab-cases"
                    class="col-start-2 justify-self-start rounded-ios px-1.5 py-0.5 text-[11px] font-semibold text-theme-accent transition-colors hover:bg-theme-surface [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                    data-calendar-lab-link
                  >
                    Ver
                  </RouterLink>
                </li>
              </ul>
            </section>
          </aside>
        </div>

        <div
          v-if="can.collectPendingPayments?.value"
          class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-ios border border-hairline bg-theme-surface px-4 py-3"
          data-calendar-day-footer
        >
          <p class="text-sm text-theme-secondary">Cierra el turno cobrando lo atendido del día.</p>
          <RouterLink
            to="/cash-register/ready-to-bill"
            class="inline-flex items-center gap-1 rounded-ios px-1 py-1 text-sm font-semibold text-theme-accent transition-colors hover:underline [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
            data-calendar-ready-to-bill
          >
            Listos para facturar
            <ChevronRightIcon class="h-4 w-4" aria-hidden="true" />
          </RouterLink>
        </div>
      </div>

      <!-- Semana y mes: el filtro por profesional ya no viaja al servidor ni
           vive en una barra de selects; son las mismas pastillas del tablero
           sobre las filas leídas. Cuando la selección no deja ninguna cita se
           declara aquí, sin inventar un vacío de período. -->
      <div
        v-else-if="rangeFilteredEmpty && currentView !== 'day'"
        class="p-4 sm:p-6"
        data-calendar-filtered-empty
      >
        <CalendarEmptyBlock
          data-calendar-filtered-empty-block
          title="Sin citas para estos filtros"
          :description="`Ninguna cita de ${appliedFilterSummary} en el rango visible. Limpia los filtros para ver la agenda completa.`"
        >
          <UiButton size="sm" variant="secondary" data-calendar-clear-filters @click="clearFilters">
            Limpiar filtros
          </UiButton>
        </CalendarEmptyBlock>
      </div>

      <!-- Week View -->
      <div v-else-if="currentView === 'week'" class="p-4 sm:p-6">
        <div
          class="overflow-hidden bg-canvas"
          style="border: 1px solid var(--color-hairline); border-radius: var(--radius-card-lg)"
        >
          <!-- #397 S7: en pantalla angosta la rejilla no se comprime. La hora
               conserva su columna y cada día su ancho mínimo, así que la
               semana vive en un scroll propio (horizontal y vertical): la
               página nunca desborda y la cabecera de días y el gutter de horas
               quedan fijos. -->
          <div class="relative">
            <div
              ref="weekScrollViewport"
              class="overflow-x-auto overflow-y-auto"
              data-calendar-week-scroll
              :style="calendarScrollMaxHeight"
              @scroll.passive="readWeekScrollState"
            >
              <div
                class="grid"
                data-calendar-week-grid
                :data-calendar-week-hour-width="weekHourColumnPx"
                :data-calendar-week-day-min-width="weekDayColumnMinPx"
                :data-calendar-week-hour-height="weekHourPx"
                :data-calendar-week-start-hour="weekWindowRange.startHour"
                :style="weekGridStyle"
              >
                <!-- Header row: fija arriba y, en su primera celda, a la izquierda -->
                <div
                  class="sticky left-0 top-0 z-30 border-b border-r border-hairline bg-theme-surface"
                />
                <div
                  v-for="day in weekDays"
                  :key="day.date"
                  class="sticky top-0 z-20 border-b border-r border-hairline bg-theme-surface px-2 py-2 text-center"
                >
                  <div class="text-xs font-semibold uppercase tracking-widest text-theme-secondary">
                    {{ day.name }}
                  </div>
                  <div
                    class="text-sm font-medium text-theme-primary"
                    style="font-feature-settings: 'tnum' 1"
                  >
                    {{ day.number }}
                  </div>
                </div>

                <!-- Gutter de horas, fijo a la izquierda -->
                <div
                  class="sticky left-0 z-10 border-r border-hairline bg-theme-surface"
                  aria-hidden="true"
                  :style="{ height: `${weekBodyHeight}px` }"
                >
                  <span
                    v-for="(hour, index) in weekHours"
                    :key="`gutter-${hour}`"
                    class="absolute right-2 text-[11px] tabular-nums text-theme-secondary"
                    :style="{
                      top: weekHourTop(index),
                      transform: index === 0 ? 'none' : 'translateY(-50%)'
                    }"
                  >
                    {{ formatHour(hour) }}
                  </span>
                </div>

                <!-- Una columna por día: la retícula marca las horas y cada cita
                     se posiciona por minuto, con el mismo bloque del tablero
                     (arista de estado de 4 px, hora de 11 y paciente 12.5/650). -->
                <div
                  v-for="day in weekDays"
                  :key="day.date"
                  class="relative border-r border-hairline"
                  :style="{ height: `${weekBodyHeight}px` }"
                  :data-calendar-week-column="day.date"
                >
                  <span
                    v-for="(hour, index) in weekHours"
                    :key="`line-${day.date}-${hour}`"
                    class="pointer-events-none absolute inset-x-0 border-t border-hairline"
                    :style="{ top: weekHourTop(index) }"
                    aria-hidden="true"
                  />

                  <div
                    v-for="(hour, index) in weekHours"
                    :key="`slot-${day.date}-${hour}`"
                    class="absolute inset-x-0"
                    :class="dropTargetClasses(`${day.date}-${hour}`)"
                    :style="{ top: weekHourTop(index), height: `${weekHourPx}px` }"
                    :data-calendar-slot-day="day.date"
                    :data-calendar-drop-target="`${day.date}-${hour}`"
                    :data-calendar-drop-active="dropHoverSlot === `${day.date}-${hour}` ? '' : null"
                  >
                    <button
                      v-if="can.createAppointment?.value"
                      type="button"
                      class="absolute inset-0 w-full transition-colors hover:bg-theme-surface [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                      :data-calendar-slot="`${day.date}-${hour}`"
                      :aria-label="`Nueva cita el ${day.date} a las ${formatHour(hour)}`"
                      @click="openSlotCreate(day.date, hour)"
                    ></button>
                  </div>

                  <button
                    v-for="appointment in weekDayLayout(day.date).appointments"
                    :key="appointment.id"
                    type="button"
                    class="absolute z-10 overflow-hidden rounded-ios text-left shadow-subtle transition-shadow hover:shadow-soft [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                    :class="getAppointmentClasses(appointment)"
                    :style="
                      weekBlockStyle(appointment, weekDayLayout(day.date).slots.get(appointment.id))
                    "
                    :title="chipTitle(appointment)"
                    :data-calendar-appointment="appointment.id"
                    :data-appointment-created="createdHighlightId === appointment.id ? '' : null"
                    :data-calendar-draggable="appointment.id"
                    :data-calendar-drag-state="
                      draggingAppointmentId === appointment.id ? 'active' : null
                    "
                    @pointerdown="onChipPointerDown($event, appointment)"
                    @click="selectAppointment(appointment)"
                  >
                    <span
                      class="absolute inset-y-0 left-0 w-1"
                      :class="statusRailClasses(appointment.status).dot"
                      aria-hidden="true"
                    />
                    <span class="flex h-full min-h-0 flex-col gap-0.5 py-0.5 pl-2 pr-1">
                      <span
                        class="truncate text-[11px] leading-3 tabular-nums text-theme-secondary"
                        data-calendar-chip-time
                      >
                        {{ chipTimeRange(appointment) }}
                      </span>
                      <span
                        class="text-[12.5px] font-[650] leading-tight"
                        :class="[
                          statusBlockTitleClasses(appointment.status),
                          blockAllowsTwoLines(appointment) ? 'line-clamp-2' : 'truncate'
                        ]"
                        data-calendar-chip-patient
                      >
                        {{ appointmentPatientName(appointment) }}
                      </span>
                      <span
                        v-if="weekBlockMeta(appointment)"
                        class="truncate text-[11px] leading-4"
                        :class="statusBlockMetaClasses(appointment.status)"
                        data-calendar-block-meta
                      >
                        {{ weekBlockMeta(appointment) }}
                      </span>
                    </span>
                  </button>

                  <!-- T8: los solapes que no caben se agrupan en su carril. -->
                  <span
                    v-for="indicator in weekDayLayout(day.date).indicators"
                    :key="`overlap-${day.date}-${indicator.slot}-${indicator.startMinutes}`"
                    class="absolute z-20 flex items-center justify-center overflow-hidden rounded-ios bg-theme-surface-elevated text-[11px] font-semibold tabular-nums text-theme-secondary shadow-subtle"
                    :style="weekOverlapIndicatorStyle(indicator)"
                    :title="`${indicator.count} citas más a esa hora`"
                    :aria-label="`${indicator.count} citas más a esa hora el ${day.date}`"
                    data-calendar-overlap-more
                  >
                    +{{ indicator.count }}
                  </span>
                </div>
              </div>
            </div>
            <!-- Affordance: the same edge gradient telegraphed by DataTable -->
            <span
              v-if="weekScrollLeft"
              class="pointer-events-none absolute inset-y-0 left-0 w-8"
              data-calendar-week-scroll-edge="left"
              aria-hidden="true"
              style="background: linear-gradient(to right, var(--color-canvas), transparent)"
            />
            <span
              v-if="weekScrollRight"
              class="pointer-events-none absolute inset-y-0 right-0 w-8"
              data-calendar-week-scroll-edge="right"
              aria-hidden="true"
              style="background: linear-gradient(to left, var(--color-canvas), transparent)"
            />
          </div>
          <p
            v-if="weekScrollRight"
            class="flex items-center justify-end gap-1 px-4 py-2 text-xs font-medium text-theme-secondary"
            data-calendar-week-scroll-affordance
            aria-hidden="true"
          >
            <ChevronRightIcon class="h-4 w-4" aria-hidden="true" />
            Desliza para ver la semana completa
          </p>
        </div>
      </div>

      <!-- Month View -->
      <div v-else-if="currentView === 'month'" class="p-4 sm:p-6">
        <div
          data-calendar-month-grid
          class="overflow-hidden bg-canvas"
          style="border: 1px solid var(--color-hairline); border-radius: var(--radius-card-lg)"
        >
          <!-- La rejilla del mes se desplaza dentro de su panel: la fila de
               días de la semana queda fija arriba y el documento no crece. -->
          <div class="overflow-auto" data-calendar-month-scroll :style="calendarScrollMaxHeight">
            <!-- Weekday header row: same Spanish short names as the week view -->
            <div
              class="sticky top-0 z-10 grid grid-cols-7 border-b border-hairline bg-theme-surface"
            >
              <div
                v-for="name in weekDayNames"
                :key="name"
                data-calendar-month-weekday
                class="border-r border-hairline px-2 py-2 text-center text-xs font-semibold uppercase tracking-widest text-theme-secondary last:border-r-0"
              >
                {{ name }}
              </div>
            </div>

            <!-- 42 day cells (6 rows): celdas más bajas y con más aire, hasta
                 tres bloques compactos por celda (arista, hora y paciente) y el
                 «+N más» que abre el día. T6: la rejilla conserva un ancho
                 legible en móvil y se desplaza dentro de su tarjeta. -->
            <div class="grid min-w-[560px] grid-cols-7">
              <div
                v-for="day in monthDays"
                :key="day.date"
                class="flex min-h-[100px] flex-col gap-1 overflow-hidden border-b border-r border-hairline p-1.5"
                :class="monthCellClasses(day)"
                :data-calendar-month-day="day.date"
              >
                <div class="flex items-center justify-between gap-1">
                  <!-- Objetivo táctil de 32 px con el círculo visual de 24: el
                       aspecto no cambia y el control deja de estar por debajo
                       del mínimo. -->
                  <button
                    type="button"
                    class="flex h-8 min-w-8 items-center justify-center transition-colors [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                    :data-calendar-day-link="day.date"
                    :aria-label="`Ver ${day.date} en la vista día`"
                    @click="drillDownToDate(day.date)"
                  >
                    <span
                      class="flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs font-medium tabular-nums"
                      :class="monthDayNumberClasses(day)"
                    >
                      {{ day.number }}
                    </span>
                  </button>
                </div>

                <div class="flex min-h-0 flex-1 flex-col gap-1">
                  <button
                    v-for="appointment in day.appointments"
                    :key="appointment.id"
                    type="button"
                    class="relative flex min-h-8 w-full items-center gap-1.5 overflow-hidden rounded-ios px-1 py-0.5 pl-2 text-left transition-shadow hover:shadow-medium [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                    :class="getAppointmentClasses(appointment)"
                    :title="chipTitle(appointment)"
                    :data-calendar-appointment="appointment.id"
                    :data-appointment-created="createdHighlightId === appointment.id ? '' : null"
                    @click="selectAppointment(appointment)"
                  >
                    <span
                      class="absolute inset-y-0 left-0 w-[3px]"
                      :class="statusRailClasses(appointment.status).dot"
                      aria-hidden="true"
                    />
                    <span
                      class="shrink-0 text-[10px] font-semibold tabular-nums text-theme-primary"
                      data-calendar-month-time
                    >
                      {{ formatTime(appointment.scheduled_at) }}
                    </span>
                    <span
                      class="min-w-0 flex-1 truncate text-[11px] font-medium"
                      :class="statusBlockTitleClasses(appointment.status)"
                    >
                      {{ appointment.patient?.first_name }} {{ appointment.patient?.last_name }}
                    </span>
                  </button>

                  <button
                    v-if="day.overflowCount > 0"
                    type="button"
                    class="flex min-h-8 w-full items-center rounded-ios px-1.5 py-0.5 text-left text-[10px] font-medium text-accent-700 transition-colors hover:bg-theme-surface [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
                    :data-calendar-more="day.date"
                    :data-calendar-more-count="day.overflowCount"
                    :aria-label="`Ver las ${day.overflowCount} citas de ${day.date} en la vista día`"
                    @click="drillDownToDate(day.date)"
                  >
                    +{{ day.overflowCount }} más
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Appointment Details Modal -->
    <UiModal
      :model-value="!!selectedAppointment"
      title="Detalles de la Cita"
      size="3xl"
      labelledby="appointment-detail-title"
      @update:model-value="closeDetails"
      @close="closeDetails"
    >
      <template #header>
        <div v-if="selectedAppointment" class="flex w-full items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="mb-1.5 flex flex-wrap items-center gap-2 text-xs text-theme-secondary">
              <time
                :datetime="selectedAppointment.scheduled_at"
                class="text-sm font-semibold text-theme-primary"
                style="font-feature-settings: 'tnum' 1"
              >
                {{ appointmentTimeRange(selectedAppointment) }}
              </time>
              <template v-if="selectedAppointment.dental_chair?.name">
                <span class="h-[3px] w-[3px] rounded-full bg-systemGray-400" aria-hidden="true" />
                <span>{{ ambienteLabel(selectedAppointment.dental_chair) }}</span>
              </template>
              <template v-if="selectedAppointment.appointment_type?.name">
                <span class="h-[3px] w-[3px] rounded-full bg-systemGray-400" aria-hidden="true" />
                <span>{{ selectedAppointment.appointment_type?.name }}</span>
              </template>
            </div>
            <h2
              id="appointment-detail-title"
              class="truncate text-2xl font-semibold text-theme-primary"
            >
              {{ selectedAppointment.patient?.first_name }}
              {{ selectedAppointment.patient?.last_name }}
            </h2>
            <p v-if="patientMetaLine" class="mt-1 text-sm text-theme-secondary">
              {{ patientMetaLine }}
            </p>
          </div>
          <UiButton
            variant="ghost"
            size="sm"
            aria-label="Cerrar detalle de la cita"
            data-appointment-detail-close
            @click="closeDetails"
          >
            <template #icon-left>
              <XMarkIcon class="h-4 w-4" aria-hidden="true" />
            </template>
          </UiButton>
        </div>
      </template>

      <div v-if="selectedAppointment" data-calendar-detail class="space-y-5">
        <section class="border-b border-hairline pb-5" aria-label="Etapas de la cita">
          <AppointmentStageRail :status="selectedAppointment.status" :times="railTimes" />
          <div
            v-if="!isTerminalOutcome(selectedAppointment.status)"
            class="mt-3.5 flex flex-wrap items-center justify-between gap-3"
          >
            <UiStatusBadge
              :variant="getStatusBadgeProps(selectedAppointment.status).variant"
              :label="getStatusText(selectedAppointment.status)"
              :show-dot="getStatusBadgeProps(selectedAppointment.status).showDot"
              :emphasis="statusIsActive(selectedAppointment.status) ? 'strong' : 'soft'"
              :pulse="statusIsActive(selectedAppointment.status)"
              :data-status="selectedAppointment.status"
            />
            <span
              v-if="statusSince"
              class="text-xs text-theme-secondary"
              data-appointment-status-since
              style="font-feature-settings: 'tnum' 1"
            >
              {{ statusSince }}
            </span>
          </div>
        </section>

        <section
          v-if="nowCopy"
          class="rounded-ios border border-hairline bg-theme-surface p-4"
          data-appointment-now
        >
          <p class="text-xs font-semibold uppercase tracking-wide text-theme-secondary">Ahora</p>
          <h3 class="mt-1.5 text-lg font-semibold text-theme-primary">{{ nowCopy.title }}</h3>
          <p class="mt-1 text-sm text-theme-secondary">{{ nowCopy.description }}</p>
          <p
            v-if="selectedAppointment.status === 'arrived' && selectedAppointment.announced_at"
            class="mt-1 text-xs text-theme-secondary"
            data-appointment-announced-at
          >
            Anunciado a las
            <span style="font-feature-settings: 'tnum' 1">
              {{ formatTime(selectedAppointment.announced_at) }}
            </span>
          </p>

          <div
            v-if="primaryAction || (showMyDayLink && can.viewMyDay?.value)"
            class="mt-4 flex flex-wrap items-center gap-2.5"
          >
            <UiButton
              v-if="selectedAppointment.status === 'scheduled'"
              variant="primary"
              data-calendar-action="status-confirmed"
              :data-appointment-primary="primaryAction === 'status-confirmed' ? '' : null"
              @click="changeStatus(selectedAppointment, 'confirmed')"
            >
              Confirmar cita
            </UiButton>
            <UiButton
              v-if="can.checkInAppointment?.value && selectedAppointment.status === 'confirmed'"
              variant="primary"
              data-calendar-action="check-in"
              :data-appointment-primary="primaryAction === 'check-in' ? '' : null"
              @click="markArrival(selectedAppointment)"
            >
              Registrar llegada
            </UiButton>
            <UiButton
              v-if="can.startConsultation?.value && selectedAppointment.status === 'arrived'"
              variant="primary"
              data-calendar-action="start-attention"
              :data-appointment-primary="primaryAction === 'start-attention' ? '' : null"
              @click="startAttention(selectedAppointment)"
            >
              Iniciar atención
            </UiButton>
            <UiButton
              v-if="
                can.announcePatient?.value &&
                selectedAppointment.status === 'arrived' &&
                !can.startConsultation?.value &&
                !selectedAppointment.announced_at
              "
              variant="primary"
              data-calendar-action="announce"
              :data-appointment-primary="primaryAction === 'announce' ? '' : null"
              @click="announceSelectedPatient(selectedAppointment)"
            >
              Anunciar paciente
            </UiButton>
            <!-- El profesional ya puede iniciar la atención, pero el anuncio
                 sigue siendo suyo: es el aviso a quien atiende. Secundario y
                 sólo mientras no exista un anuncio. -->
            <UiButton
              v-if="
                primaryAction === 'start-attention' &&
                can.announcePatient?.value &&
                !selectedAppointment.announced_at
              "
              variant="secondary"
              data-calendar-action="announce"
              @click="announceSelectedPatient(selectedAppointment)"
            >
              Anunciar paciente
            </UiButton>
            <template v-if="showMyDayLink">
              <RouterLink
                v-if="can.viewMyDay?.value"
                to="/my-day"
                data-calendar-action="my-day"
                class="inline-flex items-center rounded-ios px-1 py-1 text-sm font-medium text-theme-accent transition-colors duration-200 hover:underline [&:focus-visible]:[box-shadow:var(--focus-ring-default)]"
              >
                Ir a mi jornada
              </RouterLink>
            </template>
          </div>
        </section>

        <section aria-labelledby="appointment-facts-title" class="space-y-3">
          <h3 id="appointment-facts-title" class="text-sm font-semibold text-theme-primary">
            Datos de la cita
          </h3>
          <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div v-if="selectedAppointment.user?.name">
              <dt class="text-xs text-theme-secondary">Profesional</dt>
              <dd class="text-sm text-theme-primary">{{ selectedAppointment.user?.name }}</dd>
            </div>
            <div v-if="selectedAppointment.appointment_type?.name">
              <dt class="text-xs text-theme-secondary">Tipo de cita</dt>
              <dd class="text-sm text-theme-primary">
                {{ selectedAppointment.appointment_type?.name }}
              </dd>
            </div>
            <div v-if="selectedAppointment.procedure?.name">
              <dt class="text-xs text-theme-secondary">Procedimiento</dt>
              <dd class="text-sm text-theme-primary">
                {{ selectedAppointment.procedure?.name }}
              </dd>
            </div>
            <div v-if="selectedAppointment.dental_chair?.name">
              <dt class="text-xs text-theme-secondary">Ambiente</dt>
              <dd class="text-sm text-theme-primary">
                {{ ambienteLabel(selectedAppointment.dental_chair) }}
              </dd>
            </div>
            <div v-if="selectedAppointment.branch?.name">
              <dt class="text-xs text-theme-secondary">Sede</dt>
              <dd class="text-sm text-theme-primary">{{ selectedAppointment.branch?.name }}</dd>
            </div>
            <div>
              <dt class="text-xs text-theme-secondary">Duración</dt>
              <dd class="text-sm text-theme-primary">
                {{ selectedAppointment.duration_minutes }} minutos
              </dd>
            </div>
          </dl>
          <div v-if="selectedAppointment.notes">
            <p class="text-xs text-theme-secondary">Notas</p>
            <p class="text-sm text-theme-primary">{{ selectedAppointment.notes }}</p>
          </div>
          <div v-if="selectedAppointment.treatment_notes">
            <p class="text-xs text-theme-secondary">Notas de tratamiento</p>
            <p class="text-sm text-theme-primary">{{ selectedAppointment.treatment_notes }}</p>
          </div>
        </section>
      </div>

      <template #footer>
        <div
          v-if="selectedAppointment"
          class="flex w-full flex-wrap items-center justify-between gap-3"
        >
          <p
            v-if="selectedAppointment.created_at"
            class="text-xs text-theme-secondary"
            data-appointment-created-meta
          >
            Creada ·
            <span style="font-feature-settings: 'tnum' 1">
              {{ formatDateTime(selectedAppointment.created_at) }}
            </span>
          </p>
          <div class="ml-auto flex items-center gap-2">
            <UiButton
              v-if="can.editAppointment?.value"
              variant="secondary"
              data-calendar-action="edit"
              @click="editAppointment(selectedAppointment)"
            >
              Editar
            </UiButton>
            <div ref="detailsMenuRef" class="relative" @keydown.esc.stop="closeMenu">
              <UiButton
                variant="icon"
                size="sm"
                aria-label="Más acciones"
                aria-haspopup="menu"
                :aria-expanded="menuOpen"
                data-appointment-menu-trigger
                @click="toggleMenu"
              >
                <template #icon-left>
                  <EllipsisHorizontalIcon class="h-4 w-4" aria-hidden="true" />
                </template>
              </UiButton>
              <div
                v-show="menuOpen"
                role="menu"
                aria-label="Más acciones de la cita"
                data-appointment-menu
                class="absolute bottom-full right-0 z-dropdown mb-2 w-[232px] rounded-control border border-hairline bg-theme-surface-elevated p-1.5 shadow-large"
                @click="closeMenu"
              >
                <UiButton
                  v-if="can.editAppointment?.value"
                  variant="ghost"
                  full-width
                  class="rounded-md"
                  style="justify-content: flex-start"
                  data-appointment-action="reschedule"
                  @click="openReschedule(selectedAppointment)"
                >
                  Reagendar
                </UiButton>
                <p v-if="showCancelAction" class="px-2.5 pt-1.5 pb-1 text-xs text-theme-secondary">
                  Cancelar pide motivo.
                </p>
                <UiButton
                  v-if="showCancelAction"
                  variant="ghost"
                  full-width
                  class="rounded-md"
                  style="justify-content: flex-start; color: var(--color-danger-text)"
                  data-calendar-action="status-cancelled"
                  @click="changeStatus(selectedAppointment, 'cancelled')"
                >
                  Cancelar cita
                </UiButton>
                <UiButton
                  v-if="can.deleteAppointment?.value"
                  variant="ghost"
                  full-width
                  class="rounded-md"
                  style="justify-content: flex-start; color: var(--color-danger-text)"
                  data-calendar-action="delete"
                  @click="deleteAppointment(selectedAppointment)"
                >
                  Eliminar
                </UiButton>
              </div>
            </div>
          </div>
        </div>
      </template>
    </UiModal>

    <!-- #394: cancelar pide confirmación y motivo; el motivo se guarda en la
         cita, no en `notes`, que otra edición pisa. -->
    <UiModal
      :model-value="!!cancellingAppointment"
      title="Cancelar Cita"
      size="sm"
      :persistent="cancellationSubmitting"
      :close-on-backdrop="!cancellationSubmitting"
      :close-on-escape="!cancellationSubmitting"
      @update:model-value="closeCancellationDialog"
      @close="closeCancellationDialog"
    >
      <form
        v-if="cancellingAppointment"
        id="calendar-cancel-form"
        data-calendar-cancel
        class="space-y-4"
        @submit.prevent="submitCancellation"
      >
        <p class="text-sm text-theme-secondary">
          {{ cancellingAppointment.patient?.first_name }}
          {{ cancellingAppointment.patient?.last_name }} ·
          {{ formatDateTime(cancellingAppointment.scheduled_at) }}
        </p>
        <UiInput
          ref="cancellationReasonField"
          v-model="cancellationReason"
          label="Motivo de la cancelación"
          placeholder="Indica el motivo de la cancelación"
          type="textarea"
          required
          data-calendar-cancel-reason
          :error="cancellationError"
        />
      </form>
      <template #footer>
        <div class="flex w-full items-center justify-end gap-3">
          <UiButton
            variant="secondary"
            size="md"
            data-calendar-cancel-back
            :disabled="cancellationSubmitting"
            @click="closeCancellationDialog"
          >
            Volver
          </UiButton>
          <UiButton
            variant="danger"
            size="md"
            type="submit"
            form="calendar-cancel-form"
            data-calendar-cancel-confirm
            :loading="cancellationSubmitting"
          >
            Cancelar la cita
          </UiButton>
        </div>
      </template>
    </UiModal>

    <!-- New Appointment Modal -->
    <NewAppointmentModal
      v-model="showNewAppointmentModal"
      :initial-date="newAppointmentPrefill"
      :initial-chair-id="newAppointmentChairId"
      :initial-professional-id="newAppointmentProfessionalId"
      :lock-professional-to-self="!seesWholeAgenda"
      :appointment="editingAppointment"
      @created="handleAppointmentCreated"
      @updated="handleAppointmentSaved"
    />
  </AppLayout>
</template>

<script>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '../../composables/useApi'
import { useAuth } from '../../composables/useAuth'
import { usePermissions } from '../../composables/usePermissions'
import { useToast } from '../../composables/useToast'
import { useEcho } from '../../composables/useEcho'
import { markLocalAppointmentEcho } from '../../composables/useWebSocketNotifications'
import { useReducedMotion } from '../../composables/useReducedMotion'
import AppLayout from '../../components/layout/AppLayout.vue'
import UiButton from '../../components/ui/Button.vue'
import UiBadge from '../../components/ui/Badge.vue'
import UiStatusBadge from '../../components/ui/StatusBadge.vue'
import UiModal from '../../components/ui/Modal.vue'
import UiInput from '../../components/ui/Input.vue'
import UiSelect from '../../components/ui/Select.vue'
import CalendarEmptyBlock from './CalendarEmptyBlock.vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  EllipsisHorizontalIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'
import NewAppointmentModal from '../../components/appointments/NewAppointmentModal.vue'
import AppointmentStageRail from './AppointmentStageRail.vue'
import { useConsultation } from '../../composables/useConsultation'
import { useConfirm } from '../../composables/useConfirm'
import {
  formatCompactTimeRange,
  formatDateTime as formatCanonicalDateTime,
  formatTime
} from '../../composables/useFormatters'
import {
  APPOINTMENT_CHIP_BASE_CLASSES,
  APPOINTMENT_STAGE_TIME_FIELDS,
  APPOINTMENT_STATUS_KEYS,
  isTerminalOutcome,
  statusIsActive,
  statusLabel,
  statusBadgeProps,
  statusBlockMetaClasses,
  statusBlockTitleClasses,
  statusChipClasses,
  statusRailClasses,
  statusToneClasses
} from './appointmentStatus'
import {
  CALENDAR_VIEW_STORAGE_KEY,
  WEEK_DAY_NAMES,
  dayTitle,
  initialCalendarView,
  isCalendarView,
  nextAppointmentSlot,
  toLocalDateString,
  weekDays as buildWeekDays,
  monthDays as buildMonthDays,
  periodTitle,
  dateRangeForView,
  addPeriod,
  splitMonthCellAppointments
} from './calendarDateMath'
import {
  BOARD_GUTTER_PX,
  BOARD_HOUR_PX,
  BOARD_MINUTE_PX,
  BOARD_COLUMN_MIN_PX,
  WEEK_HOUR_PX,
  WEEK_MINUTE_PX,
  appointmentDurationMinutes,
  appointmentSizeTier,
  formatDurationMinutes,
  layoutOverlapLanes,
  boardBlockHeight,
  boardHours,
  boardOffset,
  boardWindow,
  boardWindowHeight,
  freeGaps,
  gapIsBookable,
  groupByChair,
  groupByProfessional,
  minutesFromMidnight,
  resourceOccupancy,
  resourceState
} from './calendarBoardMath'
import {
  DRAG_THRESHOLD_PX,
  resolveDropSlot,
  slotKeyToScheduledAt,
  addMinutesToLocal,
  canDropOn
} from './dragDropMath'

export default {
  name: 'CalendarPage',
  components: {
    AppLayout,
    UiButton,
    UiBadge,
    UiStatusBadge,
    UiModal,
    UiInput,
    UiSelect,
    CalendarEmptyBlock,
    ArrowPathIcon,
    CheckCircleIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    EllipsisHorizontalIcon,
    ExclamationTriangleIcon,
    PlusIcon,
    XMarkIcon,
    NewAppointmentModal,
    AppointmentStageRail
  },
  setup() {
    const route = useRoute()
    const { get, post, del, patch, normalizeError } = useApi()
    const { can } = usePermissions()
    const { user } = useAuth()
    const toast = useToast()
    const { privateChannel, echo } = useEcho()
    const { confirm } = useConfirm()
    const reducedMotion = useReducedMotion()

    // can ya es un objeto con propiedades computed, usarlo directamente

    // T7: starts true so the D1/D9 empty block never flashes before the
    // first range load (empty state must never appear while loading).
    const loading = ref(true)
    // #396: a failed range read is an explicit surface state, never the
    // empty agenda block.
    const loadError = ref(null)
    // T6: la agenda abre en Día —también en escritorio— y la vista que se elige
    // a mano se recuerda. La lectura de `localStorage` nunca lanza: un modo
    // privado o una cuota llena dejan la vista en la sesión, no rompen la
    // agenda.
    const readStoredView = () => {
      try {
        return window.localStorage.getItem(CALENDAR_VIEW_STORAGE_KEY)
      } catch {
        return null
      }
    }
    const rememberView = view => {
      if (!isCalendarView(view)) return
      try {
        window.localStorage.setItem(CALENDAR_VIEW_STORAGE_KEY, view)
      } catch {
        // sin almacenamiento disponible: la preferencia vive sólo en la sesión
      }
    }
    const currentView = ref(initialCalendarView(readStoredView()))
    const currentDate = ref(new Date())
    const appointments = ref([])
    // #395 S5: los filtros viven sobre la misma lectura del rango — cambiarlos
    // re-lee el período visible, sin navegación ni recarga.
    const filters = ref({ user_id: '', dental_chair_id: '', branch_id: '' })
    const professionalOptions = ref([])
    const chairOptions = ref([])
    const branchOptions = ref([])
    // Day board state: the resource the columns group by, the raw catalogues
    // (names come from the rows, never from a guess), the delivered prosthetic
    // cases of the panel and the clock the board reads for its state.
    const groupBy = ref('chair')
    const chairCatalogue = ref([])
    const branchCatalogue = ref([])
    const labCases = ref([])
    const clockNow = ref(new Date())
    const filterAvailability = ref({ professional: false, chair: false, branch: false })
    const selectedAppointment = ref(null)
    const showNewAppointmentModal = ref(false)
    const { markArrival: requestArrival, announcePatient: requestAnnouncement } = useConsultation()

    const runCustodyAction = async (request, appointment) => {
      try {
        await request(appointment)
        await loadAppointments()
        selectedAppointment.value = null
      } catch (e) {
        // toast ya se mostró en el composable
      }
    }

    const markArrival = appointment => runCustodyAction(requestArrival, appointment)
    const announcePatient = appointment => runCustodyAction(requestAnnouncement, appointment)

    // Anunciar desde el detalle no cierra la cita: el acto no cambia el estado,
    // así que cerrar el modal parecía no haber hecho nada. El payload devuelto
    // ya trae `announced_at`/`announced_by`; se reemplaza la selección con él,
    // se re-lee el día y el detalle publica el estado anunciado sin repetir la
    // acción. El aviso lo sigue dando el composable (ya se emitió).
    const announceSelectedPatient = async appointment => {
      if (!appointment?.id) return

      try {
        const announced = await requestAnnouncement(appointment)
        if (announced?.id) {
          selectedAppointment.value = { ...appointment, ...announced }
        }
        await loadAppointments()
      } catch (e) {
        // toast ya se mostró en el composable
      }
    }

    // S4/S5 — el acto se inicia por su propia puerta: el arranque no pasa por
    // el composable de la consulta, sólo por el endpoint existente.
    const startAttention = async appointment => {
      try {
        await post(`/api/appointments/${appointment.id}/start-attention`, {})
        toast.success('Atención iniciada.')
        await loadAppointments()
        selectedAppointment.value = null
      } catch (error) {
        toast.error(
          error?.response?.data?.error?.message ||
            error?.response?.data?.message ||
            'No se pudo iniciar la atención'
        )
      }
    }

    const editingAppointment = ref(null)
    const newAppointmentPrefill = ref(null)
    // #531: el recurso de la columna del hueco que abrió el alta. Son una
    // propuesta para el formulario —él valida su catálogo— y desaparecen al
    // cerrar, así que «Nueva cita» nunca hereda la columna de la vez anterior.
    const newAppointmentChairId = ref(null)
    const newAppointmentProfessionalId = ref(null)

    const getAppointmentLocalDate = appointment => {
      if (!appointment || !appointment.scheduled_at) return null
      const d = new Date(appointment.scheduled_at)
      if (isNaN(d.getTime())) return null
      return {
        dateStr: toLocalDateString(d),
        hour: d.getHours(),
        date: d
      }
    }

    const getInitialDateForModal = (dateStr = null, hour = null, minute = 0) => {
      const pad = n => String(n).padStart(2, '0')
      if (dateStr && Number.isInteger(hour)) {
        const minutes = Number.isInteger(minute) ? minute : 0
        return `${dateStr}T${pad(hour)}:${pad(minutes)}`
      }

      // La propuesta nace válida: hoy empieza en el siguiente cuarto de hora y
      // una vista pasada cae en hoy. El formulario además corrige la hora si
      // el hueco ya pasó mientras se llenaba.
      return nextAppointmentSlot(currentDate.value)
    }

    const openNewAppointmentModal = () => {
      editingAppointment.value = null
      newAppointmentPrefill.value = getInitialDateForModal()
      newAppointmentChairId.value = null
      newAppointmentProfessionalId.value = null
      showNewAppointmentModal.value = true
    }

    // El hueco sabe de qué columna salió: agrupando por ambiente propone ese
    // ambiente; agrupando por profesional, ese profesional.
    const openSlotCreate = (dateStr, hour, minute = 0, column = null) => {
      editingAppointment.value = null
      newAppointmentPrefill.value = getInitialDateForModal(dateStr, hour, minute)
      newAppointmentChairId.value = groupBy.value === 'chair' ? (column?.id ?? null) : null
      newAppointmentProfessionalId.value =
        groupBy.value === 'professional' ? (column?.id ?? null) : null
      showNewAppointmentModal.value = true
    }

    const handleAppointmentSaved = async appointment => {
      // La edición ya se confirmó en el formulario: su difusión no vuelve a
      // anunciarse en esta pestaña.
      markLocalAppointmentEcho(appointment?.id ?? appointment?.data?.id ?? null)
      editingAppointment.value = null
      await loadAppointments()
    }

    // --- Confirmación única de la cita creada -------------------------------
    //
    // Crear una cita tiene una sola voz en esta pestaña: la línea con su
    // acción. El modal no confirma, la difusión no tosta (sólo sincroniza la
    // fila) y el shell no anuncia el eco propio porque el id queda marcado
    // aquí. Así el mismo hecho no aparece dos veces.
    const createdConfirmation = ref(null)
    const createdHighlightId = ref(null)

    const localDayOf = value => {
      const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/)
      if (!match) return null
      const parsed = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
      return Number.isNaN(parsed.getTime()) ? null : parsed
    }

    const summaryOfCreated = payload => {
      if (payload?.summary) return payload.summary
      const parts = []
      if (payload?.patient_name) parts.push(payload.patient_name)
      const when = [payload?.date, payload?.time].filter(Boolean).join(' ')
      if (when) parts.push(when)
      const who = [payload?.professional_name, payload?.chair_name].filter(Boolean).join(' · ')
      if (who) parts.push(who)
      return parts.join(' · ')
    }

    const jumpToCreatedDay = payload => {
      const day = localDayOf(payload?.date || payload?.scheduled_at)
      if (!day) return
      currentDate.value = day
    }

    const handleAppointmentCreated = async payload => {
      const id = payload?.id ?? payload?.data?.id ?? null
      // El shell también escucha la difusión de esta cita: su eco se marca para
      // que no publique una segunda confirmación en el panel.
      markLocalAppointmentEcho(id)

      jumpToCreatedDay(payload)

      // La cita que esta pestaña acaba de escribir entra por su propia lectura
      // puntual: el alta no vuelve a pedir el rango visible. La recarga explícita
      // sólo queda para el día creado fuera del período que se está mirando (o
      // para una lectura fallida), porque es una acción del creador y no el flujo
      // masivo de recepción.
      let created = null
      if (id !== null && id !== undefined) {
        try {
          created = await fetchAppointment(id)
        } catch (error) {
          created = null
        }
      }

      if (created && isWithinVisibleRange(created)) {
        upsertAppointment(created)
      } else {
        await loadAppointments()
      }

      editingAppointment.value = null
      createdHighlightId.value = id ?? null
      createdConfirmation.value = {
        id,
        summary: summaryOfCreated(payload),
        date: payload?.date || ''
      }
    }

    const dismissCreatedConfirmation = () => {
      createdConfirmation.value = null
      createdHighlightId.value = null
    }

    // «Ver en la agenda» lleva la vista a la cita: el chip ya está en su día,
    // así que la acción lo trae al centro y le da el foco.
    const revealCreatedAppointment = () => {
      if (createdHighlightId.value === null || createdHighlightId.value === undefined) return
      nextTick(() => {
        const node = document.querySelector(
          `[data-calendar-appointment="${createdHighlightId.value}"]`
        )
        if (!node) return
        if (typeof node.scrollIntoView === 'function') {
          node.scrollIntoView({
            block: 'center',
            behavior: reducedMotion.value ? 'auto' : 'smooth'
          })
        }
        if (typeof node.focus === 'function') node.focus({ preventScroll: true })
      })
    }

    // T5b: la semana comparte la ventana del tablero (07:00–20:00, ampliada por
    // cualquier cita fuera de ella) y la hora mide 84 px. El bloque de la cita
    // se posiciona por minuto dentro de su columna de día, igual que en el
    // tablero: nada de píldoras apiladas dentro de celdas de una hora.
    const weekWindowRange = computed(() => boardWindow(rangeAppointments.value))
    const weekHours = computed(() => boardHours(weekWindowRange.value))
    const weekBodyHeight = computed(() => boardWindowHeight(weekWindowRange.value, WEEK_HOUR_PX))

    const weekDayAppointments = dateStr =>
      rangeAppointments.value
        .filter(appointment => getAppointmentLocalDate(appointment)?.dateStr === dateStr)
        .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at))

    // T8: la columna de un día reparte sus solapes igual que el tablero del Día.
    const EMPTY_WEEK_LAYOUT = { appointments: [], slots: new Map(), indicators: [] }
    const weekLayouts = computed(() => {
      const layouts = new Map()
      for (const day of weekDays.value) {
        const layout = layoutOverlapLanes(weekDayAppointments(day.date))
        const visible = layout.blocks.filter(block => !block.hidden)
        layouts.set(day.date, {
          appointments: visible.map(block => block.appointment),
          slots: new Map(visible.map(block => [block.appointment.id, block])),
          indicators: layout.indicators
        })
      }
      return layouts
    })
    const weekDayLayout = dateStr => weekLayouts.value.get(dateStr) ?? EMPTY_WEEK_LAYOUT

    const weekBlockStyle = (appointment, lane) => ({
      top: `${boardOffset(appointment.scheduled_at, weekWindowRange.value, WEEK_MINUTE_PX) ?? 0}px`,
      height: `${boardBlockHeight(appointmentDurationMinutes(appointment), WEEK_MINUTE_PX)}px`,
      ...(lane?.slots ? overlapSlotStyle(lane.slot, lane.slots) : {})
    })

    const weekOverlapIndicatorStyle = indicator =>
      overlapIndicatorStyle(indicator, weekWindowRange.value, WEEK_MINUTE_PX)

    const weekHourTop = index => `${index * WEEK_HOUR_PX}px`

    const weekDayNames = WEEK_DAY_NAMES

    // #397 S7: la rejilla semanal conserva una columna de hora y un ancho mínimo
    // por día para que hora y paciente se lean; cuando no cabe, se desplaza en
    // horizontal dentro de su propio contenedor.
    const weekHourColumnPx = 64
    const weekDayColumnMinPx = 112
    const WEEK_DAY_COLUMNS = 7
    const weekGridStyle = {
      gridTemplateColumns: `${weekHourColumnPx}px repeat(${WEEK_DAY_COLUMNS}, minmax(${weekDayColumnMinPx}px, 1fr))`,
      minWidth: `${weekHourColumnPx + weekDayColumnMinPx * WEEK_DAY_COLUMNS}px`
    }

    // Medido, no supuesto: un contenedor que se desplaza sólo anuncia «hay más»
    // cuando su contenido desborda de verdad, y el aviso se retira al llegar al
    // final. Una sola definición para la semana, el tablero, la franja y la
    // leyenda.
    const readOverflowState = element => {
      if (!element) return { left: false, right: false }
      const maxScroll = Math.max(0, element.scrollWidth - element.clientWidth)
      const hasOverflow = maxScroll > 1
      return {
        left: hasOverflow && element.scrollLeft > 1,
        right: hasOverflow && element.scrollLeft < maxScroll - 1
      }
    }

    // Franja de días: en móvil y tableta vive en su propio scroll horizontal.
    const stripScrollViewport = ref(null)
    const stripScrollLeft = ref(false)
    const stripScrollRight = ref(false)

    const readStripScrollState = () => {
      const state = readOverflowState(stripScrollViewport.value)
      stripScrollLeft.value = state.left
      stripScrollRight.value = state.right
    }

    // Leyenda de estados: en pantalla angosta se desplaza en su propia línea.
    const legendScrollViewport = ref(null)
    const legendScrollLeft = ref(false)
    const legendScrollRight = ref(false)

    const readLegendScrollState = () => {
      const state = readOverflowState(legendScrollViewport.value)
      legendScrollLeft.value = state.left
      legendScrollRight.value = state.right
    }

    const weekScrollViewport = ref(null)
    const weekScrollLeft = ref(false)
    const weekScrollRight = ref(false)

    // Medido, no supuesto: sin desborde real no hay gradiente ni aviso. jsdom no
    // calcula layout, así que ahí ambas medidas son 0 y el affordance no aparece.
    const readWeekScrollState = () => {
      const state = readOverflowState(weekScrollViewport.value)
      weekScrollLeft.value = state.left
      weekScrollRight.value = state.right
    }

    // La rejilla sólo existe cuando la vista es semana y hay citas que pintar:
    // se mide al aparecer y al girar el equipo, nunca sobre un nodo ausente.
    const weekGridRendered = computed(
      () =>
        currentView.value === 'week' &&
        !loading.value &&
        !loadError.value &&
        appointments.value.length > 0
    )

    watch(weekGridRendered, readWeekScrollState, { flush: 'post' })

    // El tablero del día vive en su propio scroll horizontal (una columna por
    // recurso): se mide igual que la semana, con la misma regla de «medido, no
    // supuesto».
    const boardScrollViewport = ref(null)
    const boardScrollLeft = ref(false)
    const boardScrollRight = ref(false)

    const readBoardScrollState = () => {
      const state = readOverflowState(boardScrollViewport.value)
      boardScrollLeft.value = state.left
      boardScrollRight.value = state.right
    }

    const currentPeriodTitle = computed(() => {
      return periodTitle(currentDate.value, currentView.value)
    })

    // T5b: la leyenda se queda con los cinco estados que se leen en el bloque
    // (programada, confirmada, en espera, en consulta, completada). Cancelada,
    // no se presentó y reprogramada se explican solas en el bloque y salen de
    // la leyenda para no convertirla en una banda densa.
    const LEGEND_STATUS_KEYS = ['scheduled', 'confirmed', 'arrived', 'in_progress', 'completed']
    const legendStatuses = APPOINTMENT_STATUS_KEYS.filter(key => LEGEND_STATUS_KEYS.includes(key))

    // La superficie del día desplaza su tablero dentro de su propio marco, y
    // la semana y el mes hacen lo mismo con su rejilla. La altura máxima deja
    // el documento por debajo de 1.150 px a 1440×1000.
    //
    // T6: el alto se mide en `dvh` además de `vh` —el viewport del móvil cambia
    // con la barra del navegador y un `100vh` fijo deja el tablero bajo el
    // pliegue—. Las dos declaraciones viajan en el mismo atributo: el navegador
    // se queda con la que entiende, así que donde `dvh` no existe sigue
    // valiendo el `vh`.
    const calendarScrollMaxHeight =
      'max-height: calc(100vh - 350px); max-height: calc(100dvh - 350px)'

    // T2 — la matriz de alcance de la agenda. Sólo administración y recepción
    // leen la sede entera; el resto de los roles opera sus propias citas (el
    // índice ya acota), así que la superficie no ofrece el filtro de
    // profesional ni deja elegir a otro en el alta.
    const seesWholeAgenda = computed(
      () => user.value?.role === 'administrador' || user.value?.role === 'recepcionista'
    )

    // T5a: los filtros compactos sustituyen a la barra de tres selects. El
    // ambiente sólo se filtra fuera del tablero (en Día las columnas ya son los
    // ambientes) y la sede sólo se ofrece a una sesión con alcance de grupo:
    // una sesión con sede no se compara consigo misma.
    const showChairFilter = computed(
      () =>
        currentView.value !== 'day' &&
        filterAvailability.value.chair &&
        chairOptions.value.length > 1
    )
    const showBranchFilter = computed(
      () =>
        filterAvailability.value.branch &&
        (user.value?.branch_id === null || user.value?.branch_id === undefined) &&
        branchOptions.value.length > 2
    )

    const hasActiveFilters = computed(() =>
      Boolean(filters.value.user_id || filters.value.dental_chair_id || filters.value.branch_id)
    )

    // Los valores llegan como número desde el catálogo y como cadena desde una
    // pastilla (`data-` atributo): la comparación es por valor, no por tipo.
    const optionLabel = (options, value) =>
      options.find(option => String(option.value) === String(value))?.label || ''

    const appliedFilterSummary = computed(() => {
      const parts = []
      if (filters.value.user_id) {
        // El profesional puede venir de una pastilla del tablero y no del
        // catálogo de la barra: su nombre también está en la pastilla.
        const label =
          optionLabel(professionalOptions.value, filters.value.user_id) ||
          professionalPills.value.find(pill => pill.value === String(filters.value.user_id))
            ?.label ||
          ''
        parts.push(`el profesional «${label}»`)
      }
      if (filters.value.dental_chair_id) {
        parts.push(
          `el ambiente «${optionLabel(chairOptions.value, filters.value.dental_chair_id)}»`
        )
      }
      if (filters.value.branch_id) {
        parts.push(`la sede «${optionLabel(branchOptions.value, filters.value.branch_id)}»`)
      }
      return parts.join(' y ')
    })

    const loadedAppointmentsCaption = computed(() => {
      if (loadError.value) return 'No se pudieron cargar las citas del rango actual'
      // The board reads the visible week, so its caption counts the day it is
      // painting, never the week behind the strip.
      if (currentView.value === 'day') {
        const dayCount = dayAppointments.value.length
        const scope = hasActiveFilters.value ? ' con los filtros aplicados' : ''
        // Cero se cuenta, no se niega: la única frase de vacío de la superficie
        // es la del bloque que ofrece crear la cita.
        if (dayCount === 0) return `0 citas cargadas en el día${scope}`
        return dayCount === 1
          ? `1 cita cargada en el día${scope}`
          : `${dayCount} citas cargadas en el día${scope}`
      }
      const count = rangeAppointments.value.length
      const scope = hasActiveFilters.value ? ' con los filtros aplicados' : ''
      if (count === 0) return `0 citas cargadas en el rango actual${scope}`
      return count === 1
        ? `1 cita cargada en el rango actual${scope}`
        : `${count} citas cargadas en el rango actual${scope}`
    })

    // La línea de contexto de la banda es la única casa del censo del día:
    // citas cargadas, ambientes operativos y ocupación, en una sola línea.
    const headerContext = computed(() => {
      if (loadError.value) return loadedAppointmentsCaption.value
      if (currentView.value !== 'day') return loadedAppointmentsCaption.value
      return `${loadedAppointmentsCaption.value} · ${ambienteCountCaption.value} · ocupación ${dayOccupancyPercent.value}%`
    })

    const dayDateString = computed(() => toLocalDateString(currentDate.value))

    // --- Tablero del día (T4) ----------------------------------------------
    //
    // La vista Día lee la semana visible: la franja de días, el censo de la
    // pastilla de cada profesional y el tablero salen de la misma lectura.
    const dayAppointments = computed(() =>
      appointments.value
        .filter(apt => getAppointmentLocalDate(apt)?.dateStr === dayDateString.value)
        .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at))
    )

    const matchesProfessional = appointment =>
      !filters.value.user_id ||
      String(appointment?.user_id ?? appointment?.user?.id ?? '') === String(filters.value.user_id)

    const visibleDayAppointments = computed(() => dayAppointments.value.filter(matchesProfessional))

    // T5a: las pastillas de profesional son las mismas en las tres vistas y el
    // filtro se aplica sobre las filas ya leídas, no sobre la consulta: pedir
    // el censo recortado al servidor vaciaría las demás pastillas.
    const rangeAppointments = computed(() => appointments.value.filter(matchesProfessional))

    const rangeFilteredEmpty = computed(
      () => hasActiveFilters.value && rangeAppointments.value.length === 0
    )

    const pillScopeAppointments = computed(() =>
      currentView.value === 'day' ? dayAppointments.value : appointments.value
    )

    const professionalPillTotal = computed(() => pillScopeAppointments.value.length)

    const professionalPills = computed(() => {
      const rows = new Map()
      pillScopeAppointments.value.forEach(appointment => {
        const id = appointment?.user_id ?? appointment?.user?.id
        if (!id) return
        const key = String(id)
        const entry = rows.get(key) || {
          value: key,
          label:
            appointment?.user?.name || optionLabel(professionalOptions.value, id) || 'Profesional',
          count: 0
        }
        entry.count += 1
        rows.set(key, entry)
      })
      return [...rows.values()].sort((a, b) => b.count - a.count || a.label.localeCompare(b.label))
    })

    const availableChairs = computed(() => {
      const selected = filters.value.dental_chair_id
      if (!selected) return chairCatalogue.value
      return chairCatalogue.value.filter(chair => String(chair.id) === String(selected))
    })

    const weekStripDays = computed(() => {
      const counts = new Map()
      rangeAppointments.value.forEach(appointment => {
        const info = getAppointmentLocalDate(appointment)
        if (!info) return
        counts.set(info.dateStr, (counts.get(info.dateStr) || 0) + 1)
      })
      const today = toLocalDateString(clockNow.value)
      return buildWeekDays(currentDate.value).map(day => {
        const count = counts.get(day.date) || 0
        const isToday = day.date === today
        const isSelected = day.date === dayDateString.value
        // La celda no escribe una frase: el número es el dato y el recuento
        // sólo aparece cuando hay algo que contar. La etiqueta accesible dice
        // lo mismo sin repetir «Sin citas» siete veces en la pantalla.
        const countPhrase = count === 0 ? 'agenda libre' : count === 1 ? '1 cita' : `${count} citas`
        return {
          ...day,
          count,
          isToday,
          isSelected,
          ariaLabel: `${dayTitle(day.date)}, ${countPhrase}${isToday ? ', hoy' : ''}`
        }
      })
    })

    // La franja se mide cuando aparece (y al cambiar de tamaño): su aviso de
    // «hay más días» no puede depender de que el usuario la desplace.
    const stripRendered = computed(
      () => currentView.value === 'day' && weekStripDays.value.length > 0
    )
    watch(stripRendered, readStripScrollState, { flush: 'post' })

    // Cambiar de día en la franja no vuelve a pedir el rango: la lectura ya
    // cubre la semana entera.
    const selectStripDay = dateStr => {
      if (!dateStr || dateStr === dayDateString.value) return
      const [year, month, day] = dateStr.split('-').map(Number)
      if (!year || !month || !day) return
      currentDate.value = new Date(year, month - 1, day)
      // Mismo rango de semana, distinto día: sólo el taller se vuelve a leer.
      loadLabCases()
    }

    const setGroupBy = value => {
      if (value !== 'chair' && value !== 'professional') return
      groupBy.value = value
    }

    const nowMinutes = computed(() => minutesFromMidnight(clockNow.value))

    const timeAtMinutes = minutes => {
      if (minutes === null || minutes === undefined) return ''
      const date = new Date(2026, 0, 1, 0, 0, 0, 0)
      date.setMinutes(minutes)
      return formatTime(date)
    }

    const patientNameOf = appointment =>
      `${appointment?.patient?.first_name || ''} ${appointment?.patient?.last_name || ''}`.trim() ||
      'Paciente'

    // T5b: el estado de la columna se lee completo, sin nombres de paciente que
    // se recorten. Se compone de estado + cifra corta: minutos de espera, de
    // consulta o la hora en que el ambiente vuelve a estar libre.
    const boardStateLabel = state => {
      if (state.tone === 'wait') {
        return `En espera · ${formatDurationMinutes(waitingMinutesOf(state.appointment))}`
      }
      if (state.tone === 'busy') {
        const minutes = Math.max(0, (state.untilMinutes ?? nowMinutes.value) - nowMinutes.value)
        const inConsultation =
          state.appointment?.status === 'in_consultation' ||
          state.appointment?.status === 'in_progress'
        return `${inConsultation ? 'En consulta' : 'Ocupado'} · ${formatDurationMinutes(minutes)}`
      }
      if (state.nextMinutes !== null && state.nextMinutes !== undefined) {
        return `Libre hasta las ${timeAtMinutes(state.nextMinutes)}`
      }
      return 'Libre'
    }

    const boardStateTone = state => {
      const rail = state.status ? statusRailClasses(state.status) : null
      return {
        tone: state.tone,
        dot: rail?.dot || 'bg-systemGreen-500',
        label: rail?.label || 'text-systemGreen-700'
      }
    }

    const boardWindowRange = computed(() => boardWindow(visibleDayAppointments.value))
    const boardHourLabels = computed(() => boardHours(boardWindowRange.value))
    const boardBodyHeight = computed(() => boardWindowRange.value.height)

    const boardColumns = computed(() => {
      const window = boardWindowRange.value
      const current = clockNow.value
      const base =
        groupBy.value === 'chair'
          ? groupByChair(visibleDayAppointments.value, availableChairs.value)
          : groupByProfessional(visibleDayAppointments.value)

      return base.map(column => {
        const occupancy = resourceOccupancy(column.appointments, window)
        const state = resourceState(column.appointments, current)
        // T8: las citas que se solapan se reparten lado a lado; las que no caben
        // no se apilan, se agrupan en el indicador «+N».
        const layout = layoutOverlapLanes(column.appointments)
        const visible = layout.blocks.filter(block => !block.hidden)
        return {
          ...column,
          occupancyPercent: occupancy.percent,
          occupancyMinutes: occupancy.minutes,
          freeGaps: freeGaps(column.appointments, window),
          visibleAppointments: visible.map(block => block.appointment),
          blockSlots: new Map(visible.map(block => [block.appointment.id, block])),
          overlapIndicators: layout.indicators,
          state,
          stateLabel: boardStateLabel(state),
          stateTone: boardStateTone(state)
        }
      })
    })

    // El código del ambiente deja de competir por el ancho de la cabecera: vive
    // en su `title` (y en el hook `data-calendar-column-code`), así que la
    // cabecera se queda con el nombre y el estado, sin empujar nada al extremo.
    const columnHeaderTitle = column =>
      [column?.name, column?.meta, column?.stateLabel].filter(Boolean).join(' · ')

    const boardGridMinWidth = computed(
      () => BOARD_GUTTER_PX + boardColumns.value.length * BOARD_COLUMN_MIN_PX
    )

    // El tablero sólo existe cuando llegan las citas: sin esta medida el aviso
    // dependía de que el usuario desplazara (o cambiara el tamaño de la
    // ventana), así que un tablero desbordado se veía sin degradado ni aviso.
    const boardGridRendered = computed(
      () =>
        currentView.value === 'day' &&
        !loading.value &&
        !loadError.value &&
        !dayEmpty.value &&
        boardColumns.value.length > 0
    )
    watch(boardGridRendered, readBoardScrollState, { flush: 'post' })

    const boardGridStyle = computed(() => ({
      gridTemplateColumns: `${BOARD_GUTTER_PX}px repeat(${Math.max(
        boardColumns.value.length,
        1
      )}, minmax(${BOARD_COLUMN_MIN_PX}px, 1fr))`,
      minWidth: `${boardGridMinWidth.value}px`
    }))

    const dayOccupancyPercent = computed(() => {
      const columns = boardColumns.value
      if (!columns.length) return 0
      const capacity =
        columns.length *
        Math.max(1, boardWindowRange.value.endMinutes - boardWindowRange.value.startMinutes)
      const occupied = columns.reduce((sum, column) => sum + column.occupancyMinutes, 0)
      return Math.min(100, Math.round((occupied / capacity) * 100))
    })

    const ambienteCountCaption = computed(() => {
      const count = availableChairs.value.length || boardColumns.value.filter(c => c.id).length
      if (count === 0) return 'Sin ambientes en el catálogo'
      return count === 1 ? '1 ambiente operativo' : `${count} ambientes operativos`
    })

    // Con el día libre —con filtros o sin ellos— el tablero pinta UN bloque
    // centrado con su acción, en vez de siete columnas declarando vacío cada
    // una. `emptyStateCopy` elige el mensaje (filtros o agenda).
    const dayEmpty = computed(() => visibleDayAppointments.value.length === 0)

    const showNowLine = computed(() => {
      if (dayDateString.value !== toLocalDateString(clockNow.value)) return false
      if (nowMinutes.value === null) return false
      return (
        nowMinutes.value >= boardWindowRange.value.startMinutes &&
        nowMinutes.value <= boardWindowRange.value.endMinutes
      )
    })

    const nowLineTop = computed(
      () => (nowMinutes.value - boardWindowRange.value.startMinutes) * BOARD_MINUTE_PX
    )

    const nowLabel = computed(() => formatTime(clockNow.value))

    // La píldora de «ahora» se pinta sobre la línea y mide 20 px; la etiqueta de
    // la hora mide 16. Cuando la línea cae a menos de sus dos mitades de una
    // hora en punto, las cajas se tocan: se oculta esa etiqueta (la píldora ya
    // dice la hora). La regla es geométrica y no «la hora del reloj», porque el
    // globo también se desliza bajo el gutter al desplazar el tablero en
    // horizontal: cualquier etiqueta que pueda quedar debajo se retira.
    const NOW_PILL_HALF_PX = 10
    const HOUR_LABEL_HALF_PX = 8
    const NOW_LABEL_CLEARANCE_PX = NOW_PILL_HALF_PX + HOUR_LABEL_HALF_PX

    const hourLabelHidden = index => {
      if (!showNowLine.value) return false
      return Math.abs(nowLineTop.value - index * BOARD_HOUR_PX) <= NOW_LABEL_CLEARANCE_PX
    }

    const boardHourLabelStyle = index => ({
      top: `${index * BOARD_HOUR_PX}px`,
      transform: index === 0 ? 'none' : 'translateY(-50%)'
    })

    const boardBlockTier = appointment =>
      appointmentSizeTier(appointmentDurationMinutes(appointment))

    // Un bloque de 60 min mide 68 px: da para la hora y dos líneas de nombre.
    // Por debajo de eso el nombre va en una sola línea (con elipsis) antes que
    // cortarse a media línea.
    const blockAllowsTwoLines = appointment =>
      boardBlockTier(appointment) === 'standard' || boardBlockTier(appointment) === 'regular'

    // Una caja por carril: `slots` huecos iguales y 2 px de aire a cada lado,
    // así dos citas simultáneas se ven una junto a otra sin taparse ni salirse
    // de la columna.
    const overlapSlotStyle = (slot, slots) => {
      const size = 100 / Math.max(1, slots)
      return {
        left: `calc(${slot * size}% + 2px)`,
        width: `calc(${size}% - 4px)`
      }
    }

    const boardBlockStyle = (appointment, lane) => ({
      top: `${boardOffset(appointment.scheduled_at, boardWindowRange.value) ?? 0}px`,
      height: `${boardBlockHeight(appointmentDurationMinutes(appointment))}px`,
      ...(lane?.slots ? overlapSlotStyle(lane.slot, lane.slots) : {})
    })

    // El indicador «+N» ocupa el carril reservado y la ventana de lo agrupado.
    const overlapIndicatorStyle = (indicator, window, minutePx = BOARD_MINUTE_PX) => ({
      top: `${(indicator.startMinutes - window.startMinutes) * minutePx}px`,
      height: `${boardBlockHeight(indicator.endMinutes - indicator.startMinutes, minutePx)}px`,
      ...overlapSlotStyle(indicator.slot, indicator.slots)
    })

    const boardBlockTimeLabel = appointment =>
      boardBlockTier(appointment) === 'compact'
        ? formatTime(appointment.scheduled_at)
        : chipTimeRange(appointment)

    const boardBlockMeta = appointment =>
      appointment?.appointment_type?.name || appointment?.procedure?.name || ''

    // El tipo se imprime sólo cuando el alto del bloque lo permite: a partir de
    // 60 min hay sitio para una tercera línea sin recortar el paciente.
    const metaFitsBlock = appointment => {
      const tier = boardBlockTier(appointment)
      return tier === 'standard' || tier === 'regular'
    }

    const boardBlockMetaVisible = appointment =>
      metaFitsBlock(appointment) ? boardBlockMeta(appointment) : ''

    const weekBlockMeta = appointment =>
      metaFitsBlock(appointment) ? boardBlockMeta(appointment) : ''

    const boardBlockFooter = appointment => {
      if (boardBlockTier(appointment) !== 'regular') return ''
      const parts = []
      if (groupBy.value !== 'professional' && appointment?.user?.name) {
        parts.push(appointment.user.name)
      }
      if (groupBy.value !== 'chair' && appointment?.dental_chair?.name) {
        parts.push(appointment.dental_chair.name)
      }
      return parts.join(' · ')
    }

    const boardBlockLabel = appointment =>
      [
        statusLabel(appointment.status),
        `${formatTime(appointment.scheduled_at)} a ${formatTime(
          appointment.ends_at || appointment.scheduled_at
        )}`,
        patientNameOf(appointment),
        boardBlockMeta(appointment)
      ]
        .filter(Boolean)
        .join(', ')

    const freeSlotLabel = (column, slot) =>
      `${column.name} libre de ${timeAtMinutes(slot.startMinutes)} a ${timeAtMinutes(
        slot.endMinutes
      )}, agendar cita`

    const arrivedToday = computed(() =>
      visibleDayAppointments.value.filter(appointment => appointment.status === 'arrived')
    )

    const unconfirmedToday = computed(() =>
      visibleDayAppointments.value.filter(appointment => appointment.status === 'scheduled')
    )

    const waitingMinutesOf = appointment => {
      const checkedIn = minutesFromMidnight(appointment?.checked_in_at)
      if (checkedIn === null || nowMinutes.value === null) return 0
      return Math.max(0, nowMinutes.value - checkedIn)
    }

    const waitingLine = appointment => {
      const parts = []
      if (appointment?.dental_chair?.name) parts.push(appointment.dental_chair.name)
      if (appointment?.checked_in_at)
        parts.push(`espera ${formatDurationMinutes(waitingMinutesOf(appointment))}`)
      return parts.join(' · ') || 'En sala de espera'
    }

    const initialsOf = (first, last) => {
      const initials = `${String(first || '').charAt(0)}${String(last || '').charAt(0)}`
      return initials.trim().toUpperCase() || 'PA'
    }

    const patientInitials = appointment =>
      initialsOf(appointment?.patient?.first_name, appointment?.patient?.last_name)

    const localDateOf = value => {
      if (!value) return null
      const date = new Date(value)
      return Number.isNaN(date.getTime()) ? null : toLocalDateString(date)
    }

    const labReadyToday = computed(() =>
      labCases.value.filter(
        labCase =>
          labCase?.status === 'delivered' &&
          localDateOf(labCase.delivered_at) === dayDateString.value
      )
    )

    // T6 (ronda 2) — sin secciones, el panel no existe y NO reserva su columna:
    // el tablero se queda con todo el ancho en vez de dejar 288 px muertos a su
    // lado. La columna sólo aparece cuando hay algo que leer en ella.
    const hasPendingPanel = computed(
      () =>
        arrivedToday.value.length > 0 ||
        unconfirmedToday.value.length > 0 ||
        labReadyToday.value.length > 0
    )

    const labCasePatientName = labCase =>
      `${labCase?.patient?.first_name || ''} ${labCase?.patient?.last_name || ''}`.trim() ||
      'Paciente'

    const labCaseLine = labCase => {
      const parts = []
      if (labCase?.work) parts.push(labCase.work)
      if (labCase?.dental_piece?.fdi_number) parts.push(labCase.dental_piece.fdi_number)
      return parts.join(' · ') || 'Caso entregado'
    }

    // Los contadores del panel son píldoras neutras; el de la sala usa el
    // índigo de su estado para leerse como el bloque que espera.
    const PANEL_COUNT_VARIANTS = { arrived: 'indigo', scheduled: 'neutral', lab: 'neutral' }

    const panelCountVariant = section => PANEL_COUNT_VARIANTS[section] || 'neutral'

    const labCaseInitials = labCase =>
      initialsOf(labCase?.patient?.first_name, labCase?.patient?.last_name)

    const activeBranchName = computed(() => {
      if (filters.value.branch_id) {
        return optionLabel(branchOptions.value, filters.value.branch_id)
      }
      const affiliation = user.value?.branch_id
      if (affiliation !== null && affiliation !== undefined) {
        const match = branchCatalogue.value.find(
          branch => String(branch.id) === String(affiliation)
        )
        if (match) return match.name
      }
      if (branchCatalogue.value.length === 1) return branchCatalogue.value[0].name
      if (branchCatalogue.value.length > 1) return 'Todas las sedes'
      const names = [...new Set(appointments.value.map(apt => apt.branch?.name).filter(Boolean))]
      return names.length === 1 ? names[0] : ''
    })

    const headerKicker = computed(() =>
      activeBranchName.value ? `Calendario · ${activeBranchName.value}` : 'Calendario'
    )

    const headerTitle = computed(() =>
      currentView.value === 'day' ? dayTitle(currentDate.value) : currentPeriodTitle.value
    )

    const weekDays = computed(() => {
      return buildWeekDays(currentDate.value)
    })

    const monthDays = computed(() => {
      const now = new Date()
      return buildMonthDays(currentDate.value, now).map(cell => {
        const dayAppointments = rangeAppointments.value
          .filter(apt => getAppointmentLocalDate(apt)?.dateStr === cell.date)
          .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at))
        const { visible, overflowCount } = splitMonthCellAppointments(dayAppointments)

        return {
          ...cell,
          appointments: visible,
          overflowCount
        }
      })
    })

    // La vista Día lee la semana visible: la franja y el censo de las pastillas
    // necesitan los siete días y el tablero sólo pinta el elegido.
    const getDateRangeForCurrentView = () =>
      dateRangeForView(currentDate.value, currentView.value === 'day' ? 'week' : currentView.value)

    const activeFilterParams = () => {
      const params = {}
      if (filters.value.dental_chair_id) params.dental_chair_id = filters.value.dental_chair_id
      if (filters.value.branch_id) params.branch_id = filters.value.branch_id
      return params
    }

    // --- Sincronización puntual de la difusión -----------------------------
    //
    // El evento de la agenda trae el `id` de la cita, no la cita. Volver a leer
    // el rango visible por cada cita creada o movida encendía el esqueleto y
    // costaba lo mismo que abrir la agenda entera; el profesional veía la
    // recarga crecer con cada cita de la sede. Aquí la difusión se resuelve
    // con una sola lectura puntual y un upsert sobre el arreglo ya leído.
    const currentUserId = computed(() => user.value?.id ?? null)

    const fetchAppointment = async id => {
      const response = await get(`/api/appointments/${id}`)
      return response?.data ?? null
    }

    // La cita se fecha en el reloj local, igual que el rango visible; el día
    // final del período es parte de la agenda.
    const isWithinVisibleRange = appointment => {
      if (!appointment?.scheduled_at) return false
      const scheduled = new Date(appointment.scheduled_at)
      if (Number.isNaN(scheduled.getTime())) return false

      const range = getDateRangeForCurrentView()
      if (!range?.start_date || !range?.end_date) return false

      const day = toLocalDateString(scheduled)
      return day >= range.start_date && day <= range.end_date
    }

    const scheduledAtValue = appointment => {
      const value = new Date(appointment?.scheduled_at ?? '')
      return Number.isNaN(value.getTime()) ? Number.POSITIVE_INFINITY : value.getTime()
    }

    // El detalle abierto lee el mismo registro: el estado, la píldora y el rail
    // se refrescan con la fila leída, sin recargar la agenda.
    const syncSelectedAppointment = appointment => {
      if (!appointment?.id || !selectedAppointment.value) return
      if (String(selectedAppointment.value.id) !== String(appointment.id)) return
      selectedAppointment.value = appointment
    }

    const upsertAppointment = appointment => {
      if (!appointment?.id) return

      const key = String(appointment.id)
      const rows = appointments.value.filter(row => String(row.id) !== key)
      rows.push(appointment)
      rows.sort((left, right) => scheduledAtValue(left) - scheduledAtValue(right))
      appointments.value = rows
      syncSelectedAppointment(appointment)
    }

    const removeAppointment = id => {
      if (id === null || id === undefined || id === '') return
      const key = String(id)
      if (!appointments.value.some(row => String(row.id) === key)) return
      appointments.value = appointments.value.filter(row => String(row.id) !== key)
    }

    const closeSelectedAppointment = id => {
      if (id === null || id === undefined || id === '') return
      if (!selectedAppointment.value) return
      if (String(selectedAppointment.value.id) !== String(id)) return
      selectedAppointment.value = null
    }

    // Administración y recepción leen la sede entera; un escritorio clínico
    // sólo opera sus propias citas.
    const appointmentBelongsToSession = appointment => {
      if (seesWholeAgenda.value) return true
      const owner = appointment?.user_id ?? appointment?.user?.id ?? null
      if (owner === null || owner === undefined || owner === '') return false
      return String(owner) === String(currentUserId.value ?? '')
    }

    // Un 403/404 de la lectura puntual dice que la cita dejó de ser visible
    // para esta sesión (o ya no existe): es una fila que sale, no un error de
    // la página.
    const isUnreadableAppointment = error => {
      const status = error?.status ?? error?.response?.status ?? null
      return status === 403 || status === 404
    }

    const syncAppointmentFromEvent = async appointment => {
      const id = appointment?.id ?? null
      if (id === null || id === undefined || id === '') return

      if (!appointmentBelongsToSession(appointment)) {
        // Reasignada a otro escritorio: la fila ya no pertenece a esta agenda y
        // no se lee.
        removeAppointment(id)
        closeSelectedAppointment(id)
        return
      }

      let fresh = null
      try {
        fresh = await fetchAppointment(id)
      } catch (error) {
        if (isUnreadableAppointment(error)) {
          removeAppointment(id)
          closeSelectedAppointment(id)
        }
        return
      }

      if (!fresh) {
        removeAppointment(id)
        closeSelectedAppointment(id)
        return
      }

      if (isWithinVisibleRange(fresh)) {
        upsertAppointment(fresh)
        return
      }

      removeAppointment(id)
      // La cita sigue existiendo aunque haya salido del período visible: el
      // detalle abierto conserva el registro leído, no la copia anterior.
      syncSelectedAppointment(fresh)
    }

    const loadAppointments = async () => {
      loading.value = true
      try {
        const dateRange = getDateRangeForCurrentView()

        const params = {
          start_date: dateRange.start_date,
          end_date: dateRange.end_date,
          per_page: 1000, // Obtener todas las citas del rango sin paginación
          // Sólo viaja el filtro elegido: un filtro limpio no es "ninguno".
          ...activeFilterParams()
        }
        // El profesional se filtra sobre las filas ya leídas: la lectura del
        // rango nunca se recorta por él (las pastillas conservan su censo).
        const response = await get('/api/appointments', { params })

        // La API devuelve { data: [...], meta: {...} }
        // useApi ya parsea el JSON, por lo que response.data es el array directamente
        appointments.value = response?.data || []
        loadError.value = null
      } catch (error) {
        toast.error('Error al cargar las citas')
        loadError.value = normalizeError(error, 'Revisa tu conexión y vuelve a intentarlo.')
        appointments.value = []
      } finally {
        loading.value = false
      }
    }

    // Los casos protésicos ya entregados del día son la tercera sección del
    // panel. La lectura es aditiva y opcional: sin `view.lab_case` la sección
    // no se pide, y un fallo del taller nunca tumba la agenda.
    const loadLabCases = async () => {
      if (!can.viewLabCase?.value) {
        labCases.value = []
        return
      }
      try {
        const response = await get('/api/lab-cases', {
          params: { status: 'delivered', per_page: 100 }
        })
        const rows = Array.isArray(response?.data) ? response.data : []
        labCases.value = rows.filter(row => row?.status === 'delivered')
      } catch (error) {
        labCases.value = []
      }
    }

    // El taller sólo se lee cuando la sección puede existir: el panel es parte
    // de la vista Día, así que la semana y el mes no piden esa población.
    const refreshAgenda = async () => {
      await loadAppointments()
      if (currentView.value === 'day') await loadLabCases()
    }

    const loadFilterOptions = async () => {
      // #396 lección: cada lookup se liquida por su lado. Uno caído oculta su
      // filtro y devuelve una lista vacía — nunca una promesa rechazada.
      const readSource = async (url, toOptions) => {
        try {
          const response = await get(url)
          const rows = Array.isArray(response?.data) ? response.data : []
          return { readable: rows.length > 0, rows, options: toOptions(rows) }
        } catch (error) {
          return { readable: false, rows: [], options: [] }
        }
      }

      const withAllOption = (label, options) =>
        options.length > 0 ? [{ value: '', label }, ...options] : []

      const [professionals, chairs, branches] = await Promise.all([
        can.viewUser?.value
          ? readSource('/api/users/active', rows =>
              rows.map(row => ({ value: row.id, label: row.name }))
            )
          : { readable: false, rows: [], options: [] },
        can.viewDentalChair?.value
          ? readSource('/api/dental-chairs/active?status=active', rows =>
              rows.map(row => ({ value: row.id, label: row.name }))
            )
          : { readable: false, rows: [], options: [] },
        can.viewBranch?.value
          ? readSource('/api/branches/active', rows =>
              rows.map(row => ({ value: row.id, label: row.name }))
            )
          : { readable: false, rows: [], options: [] }
      ])

      professionalOptions.value = withAllOption('Todos los profesionales', professionals.options)
      chairOptions.value = withAllOption('Ambientes', chairs.options)
      branchOptions.value = withAllOption('Sedes', branches.options)
      // El tablero pinta una columna por ambiente del catálogo y nombra la sede
      // de la cejilla: ambos salen de estas mismas lecturas, sin pedir más.
      chairCatalogue.value = chairs.rows.map(row => ({
        id: row.id,
        name: row.name,
        code: row.code || ''
      }))
      branchCatalogue.value = branches.rows.map(row => ({ id: row.id, name: row.name }))
      filterAvailability.value = {
        professional: professionals.readable,
        chair: chairs.readable,
        branch: branches.readable
      }
    }

    // Cambiar un filtro re-lee el período visible de la vista actual: no
    // navega ni recarga la página. El profesional es la excepción: filtra las
    // filas ya leídas (las mismas pastillas en las tres vistas), así que no
    // vuelve a pedir nada.
    const setFilter = (key, value) => {
      const nextValue = value === null || value === undefined ? '' : value
      if (filters.value[key] === nextValue) return
      filters.value = { ...filters.value, [key]: nextValue }
      if (key === 'user_id') return
      loadAppointments()
    }

    const clearFilters = () => {
      if (!hasActiveFilters.value) return
      filters.value = { user_id: '', dental_chair_id: '', branch_id: '' }
      loadAppointments()
    }

    const formatTimeRange = (start, end) => {
      return `${formatTime(start)}-${formatTime(end)}`
    }

    const appointmentTimeRange = appointment => {
      return formatTimeRange(
        appointment.scheduled_at,
        appointment.ends_at || appointment.scheduled_at
      )
    }

    // T8b defect 3 — chips render the compact `HH:mm - HH:mm` (all three
    // views) with a `title` carrying the full patient + locale range; the
    // details modal keeps the full locale format through
    // `appointmentTimeRange` above.
    const chipTimeRange = appointment =>
      formatCompactTimeRange(
        appointment.scheduled_at,
        appointment.ends_at || appointment.scheduled_at
      )

    const chipTitle = appointment => {
      const name =
        `${appointment.patient?.first_name || ''} ${appointment.patient?.last_name || ''}`.trim()
      return `${name || 'Cita'} · ${appointmentTimeRange(appointment)}`
    }

    // El nombre del recurso llega ya escrito desde la fila («Ambiente 2»); la
    // copia no le antepone un sustantivo que lo repita.
    const ambienteLabel = dentalChair => (dentalChair?.name ? dentalChair.name : 'Sin ambiente')

    const getAppointmentLabel = apt => {
      const type = apt?.appointment_type?.name || 'Cita'
      return `${type} ${appointmentTimeRange(apt)}`
    }

    const changeView = view => {
      if (!isCalendarView(view)) return
      currentView.value = view
      rememberView(view)
      loadVisibleRange()
    }

    // D8 month drill-down: "N más" (and the day-number header) jump straight
    // to the already-redesigned Day view for that date.
    const drillDownToDate = dateStr => {
      const [year, month, day] = dateStr.split('-').map(Number)
      currentDate.value = new Date(year, month - 1, day)
      changeView('day')
    }

    const monthCellClasses = day => {
      if (day.isToday) return 'bg-accent-100'
      if (!day.isCurrentMonth) return 'bg-theme-surface'
      return ''
    }

    const monthDayNumberClasses = day => {
      if (day.isToday) return 'bg-accent-500 text-white font-semibold'
      if (!day.isCurrentMonth) return 'text-theme-secondary hover:bg-theme-surface'
      return 'text-theme-primary hover:bg-theme-surface'
    }

    // Las puertas de navegación recargan el rango visible y, en la vista Día,
    // también el taller del día que se acaba de elegir.
    const loadVisibleRange = () => {
      loadAppointments()
      if (currentView.value === 'day') loadLabCases()
    }

    const previousPeriod = () => {
      currentDate.value = addPeriod(currentDate.value, currentView.value, -1)
      loadVisibleRange()
    }

    const nextPeriod = () => {
      currentDate.value = addPeriod(currentDate.value, currentView.value, 1)
      loadVisibleRange()
    }

    const goToToday = () => {
      currentDate.value = new Date()
      loadVisibleRange()
    }

    const getAppointmentClasses = appointment => {
      const created =
        createdHighlightId.value !== null &&
        String(createdHighlightId.value) === String(appointment.id)
          ? 'ring-2 ring-systemGreen-500'
          : ''

      return `${APPOINTMENT_CHIP_BASE_CLASSES} ${statusChipClasses(appointment.status)} ${dragChipClasses(appointment)} ${created}`.trim()
    }

    const getStatusClasses = status => {
      return statusToneClasses(status)
    }

    const getStatusText = status => {
      return statusLabel(status)
    }

    const getStatusBadgeProps = status => {
      return statusBadgeProps(status)
    }

    const selectAppointment = appointment => {
      if (suppressNextClick) {
        suppressNextClick = false
        return
      }
      selectedAppointment.value = appointment
    }

    // T7: UiModal owns the details chrome (backdrop, Escape, focus trap);
    // every close path funnels through clearing the selection.
    const closeDetails = () => {
      selectedAppointment.value = null
    }

    // --- Detalle: rail, siguiente paso y menú de secundarias -------------
    //
    // S1/S5: el detalle publica UNA acción primaria derivada del estado. El
    // resto vive en el menú «⋯» o como enlace de texto; cancelar y eliminar
    // nunca se sientan al lado del primario.
    const menuOpen = ref(false)
    const detailsMenuRef = ref(null)

    const closeMenu = () => {
      menuOpen.value = false
    }

    const toggleMenu = () => {
      menuOpen.value = !menuOpen.value
    }

    const handleDetailsPointerDown = event => {
      if (!menuOpen.value) return
      if (detailsMenuRef.value?.contains(event.target)) return
      closeMenu()
    }

    watch(selectedAppointment, closeMenu)

    // S5: la agenda no inicia el acto clínico; el enlace a Mi jornada es el
    // único puente y existe una sola vez en el marcado.
    const showMyDayLink = computed(() =>
      ['arrived', 'in_consultation', 'in_progress'].includes(selectedAppointment.value?.status)
    )

    // S2: una etapa muestra hora sólo si la API publica el campo que la
    // respalda. Nada se deduce ni se inventa.
    const railTimes = computed(() => {
      const appointment = selectedAppointment.value
      if (!appointment) return {}

      return Object.fromEntries(
        Object.entries(APPOINTMENT_STAGE_TIME_FIELDS).map(([stage, field]) => [
          stage,
          field ? (appointment[field] ?? null) : null
        ])
      )
    })

    // S5: el primario por estado, siempre una acción que la agenda puede
    // cerrar. Cada puerta de permiso decide si existe; sin primario no hay
    // botón, sólo el enlace a Mi jornada cuando corresponde.
    const primaryAction = computed(() => {
      const status = selectedAppointment.value?.status
      if (status === 'scheduled') return 'status-confirmed'
      if (status === 'confirmed') return can.checkInAppointment?.value ? 'check-in' : null
      if (status === 'arrived') {
        if (can.startConsultation?.value) return 'start-attention'
        // Una vez anunciada, la agenda ya no tiene nada que hacer: la
        // atención se inicia desde Mi jornada, no desde este detalle.
        if (selectedAppointment.value?.announced_at) return null
        return can.announcePatient?.value ? 'announce' : null
      }
      return null
    })

    const nowCopy = computed(() => {
      const status = selectedAppointment.value?.status
      if (status === 'scheduled') {
        return {
          title: 'La cita espera confirmación',
          description: 'Confirma con el paciente; la llegada se registra en el paso siguiente.'
        }
      }
      if (status === 'confirmed') {
        return can.checkInAppointment?.value
          ? {
              title: 'El paciente está citado',
              description: 'Registra la llegada cuando aparezca por recepción.'
            }
          : {
              title: 'El paciente está citado',
              description: 'Recepción registra la llegada cuando el paciente aparezca.'
            }
      }
      if (status === 'arrived') {
        // El anuncio deja rastro (T1): si ya ocurrió, el detalle lo dice en vez
        // de ofrecer otra vez la acción que el usuario acaba de ejecutar.
        if (selectedAppointment.value?.announced_at) {
          return {
            title: 'Paciente anunciado',
            description: 'El profesional ya fue avisado; la atención se inicia desde Mi jornada.'
          }
        }
        return {
          title: 'El paciente está en sala de espera',
          description:
            primaryAction.value === 'start-attention'
              ? 'Inicia la atención cuando pase al ambiente; el cierre se documenta en Mi jornada.'
              : 'Anuncia al profesional responsable; la atención se inicia desde Mi jornada.'
        }
      }
      if (status === 'in_consultation' || status === 'in_progress') {
        return {
          title: 'La atención está en curso',
          description: 'El cierre clínico se documenta en Mi jornada.'
        }
      }
      return null
    })

    // Sólo campos publicados: sin edad, historia ni teléfono inventados.
    const patientMetaLine = computed(() => {
      const patient = selectedAppointment.value?.patient
      if (!patient) return ''
      const parts = []
      if (patient.document_number) parts.push(`Doc. ${patient.document_number}`)
      if (patient.phone) parts.push(patient.phone)
      if (patient.email) parts.push(patient.email)
      return parts.join(' · ')
    })

    const statusSince = computed(() => {
      const appointment = selectedAppointment.value
      if (!appointment) return ''
      if (appointment.status === 'arrived' && appointment.checked_in_at) {
        return `Llegó a las ${formatTime(appointment.checked_in_at)}`
      }
      if (appointment.status === 'completed' && appointment.completed_at) {
        return `Cerrada a las ${formatTime(appointment.completed_at)}`
      }
      return ''
    })

    // El panel abre el mismo detalle que un chip, pero su fila no es un bloque
    // de la rejilla: no comparte el gancho de los chips.
    const openAppointmentFromPanel = appointment => {
      selectedAppointment.value = appointment
    }

    // T2 — «Sin confirmar» gana la acción que resuelve la fila. Confirmar es
    // una transición de estado; su rechazo se queda en el aviso de la sección,
    // y una confirmación limpia la selección y re-lee el día.
    const confirmingId = ref(null)
    const confirmError = ref('')
    const canConfirmAppointment = computed(() =>
      Boolean(can.editAppointment?.value || can.updateAppointmentStatus?.value)
    )

    const confirmAppointment = async appointment => {
      if (!appointment?.id || confirmingId.value) return

      confirmingId.value = appointment.id
      confirmError.value = ''
      try {
        await patch(`/api/appointments/${appointment.id}/status`, { status: 'confirmed' })
        markLocalAppointmentEcho(appointment.id)
        toast.success('Cita confirmada.')
        selectedAppointment.value = null
        await loadAppointments()
      } catch (error) {
        confirmError.value = normalizeError(error, 'No se pudo confirmar la cita')
      } finally {
        confirmingId.value = null
      }
    }

    const showCancelAction = computed(
      () =>
        !!selectedAppointment.value &&
        !['cancelled', 'completed'].includes(selectedAppointment.value.status)
    )

    const openReschedule = appointment => editAppointment(appointment)

    // T7 D1/D9 — quiet empty-state copy per view (one short title + one
    // precise sentence), rendered instead of the grids when the loaded range
    // has zero appointments. #395 S5: with active filters the empty block
    // describes the filter, not the agenda, and offers to clear them.
    const emptyStateCopy = computed(() => {
      if (hasActiveFilters.value) {
        return {
          title: 'Sin citas para estos filtros',
          description: `Ninguna cita del rango cargado coincide con ${appliedFilterSummary.value}. Limpia los filtros para ver toda la agenda.`
        }
      }

      const copy = {
        day: {
          title: 'Sin citas este día',
          description: 'La agenda de este día está libre. Crea una cita para empezar.'
        },
        week: {
          title: 'Sin citas esta semana',
          description: 'Ninguna cita en la semana cargada. Crea una cita para empezar.'
        },
        month: {
          title: 'Sin citas este mes',
          description: 'Ninguna cita en el mes cargado. Crea una cita para empezar.'
        }
      }
      return copy[currentView.value]
    })

    // T5 drag-and-drop reschedule: press + DRAG_THRESHOLD_PX of travel on a
    // Day/Week chip starts a drag; below the threshold the native click still
    // opens the details modal. Slot buttons are never drag sources.
    const DRAG_CHIP_MOTION_CLASSES =
      'cursor-grabbing opacity-70 -translate-y-0.5 transition-[transform,opacity] duration-normal'
    const DRAG_CHIP_REDUCED_MOTION_CLASSES =
      'cursor-grabbing opacity-70 ring-2 ring-inset ring-accent-500'
    const DRAG_CHIP_IDLE_CLASSES = 'cursor-grab'
    const DROP_TARGET_HOVER_CLASSES = 'bg-accent-50 ring-2 ring-inset ring-accent-500'

    const draggingAppointmentId = ref(null)
    const dropHoverSlot = ref(null)
    let dragSession = null
    let suppressNextClick = false

    const dragChipClasses = appointment => {
      if (currentView.value === 'month' || !can.editAppointment?.value) return ''
      if (draggingAppointmentId.value === appointment.id) {
        return reducedMotion.value ? DRAG_CHIP_REDUCED_MOTION_CLASSES : DRAG_CHIP_MOTION_CLASSES
      }
      return DRAG_CHIP_IDLE_CLASSES
    }

    const dropTargetClasses = slotKey =>
      dropHoverSlot.value === slotKey ? DROP_TARGET_HOVER_CLASSES : ''

    const collectDropTargets = () =>
      Array.from(document.querySelectorAll('[data-calendar-drop-target]')).map(element => ({
        key: element.getAttribute('data-calendar-drop-target'),
        rect: element.getBoundingClientRect()
      }))

    const attachDragListeners = () => {
      window.addEventListener('pointermove', onDragPointerMove)
      window.addEventListener('pointerup', onDragPointerUp)
      window.addEventListener('pointercancel', onDragPointerCancel)
      window.addEventListener('keydown', onDragKeyDown, true)
    }

    const detachDragListeners = () => {
      window.removeEventListener('pointermove', onDragPointerMove)
      window.removeEventListener('pointerup', onDragPointerUp)
      window.removeEventListener('pointercancel', onDragPointerCancel)
      window.removeEventListener('keydown', onDragKeyDown, true)
    }

    const onChipPointerDown = (event, appointment) => {
      if (!can.editAppointment?.value) return
      if (typeof event.button === 'number' && event.button !== 0) return
      suppressNextClick = false
      dragSession = {
        appointment,
        startX: event.clientX,
        startY: event.clientY,
        active: false,
        dropTargets: []
      }
      attachDragListeners()
    }

    const startDrag = session => {
      session.active = true
      session.dropTargets = collectDropTargets()
      draggingAppointmentId.value = session.appointment.id
      document.body.style.userSelect = 'none'
    }

    const endDrag = ({ suppressClick = false } = {}) => {
      const session = dragSession
      dragSession = null
      detachDragListeners()
      draggingAppointmentId.value = null
      dropHoverSlot.value = null
      document.body.style.userSelect = ''
      if (suppressClick) {
        suppressNextClick = true
        setTimeout(() => {
          suppressNextClick = false
        }, 0)
      }
      return session
    }

    const onDragPointerMove = event => {
      const session = dragSession
      if (!session) return
      if (!session.active) {
        const moved = Math.hypot(event.clientX - session.startX, event.clientY - session.startY)
        if (moved < DRAG_THRESHOLD_PX) return
        startDrag(session)
      }
      if (event.cancelable) event.preventDefault()
      dropHoverSlot.value = resolveDropSlot(session.dropTargets, event.clientX, event.clientY)
    }

    const onDragPointerUp = event => {
      const session = dragSession
      if (!session) return
      if (!session.active) {
        endDrag()
        return
      }
      const slotKey = resolveDropSlot(session.dropTargets, event.clientX, event.clientY)
      endDrag({ suppressClick: true })
      if (!slotKey) return
      // Own-slot drop is a no-op and stays silent (T5 contract: no request,
      // no feedback).
      const current = getAppointmentLocalDate(session.appointment)
      if (current && slotKey === `${current.dateStr}-${current.hour}`) return
      if (!canDropOn(slotKey, session.appointment)) {
        // T8b investigation: drops onto past slots are rejected CLIENT-side
        // by `canDropOn` before any request — in the real-browser pass that
        // read as a silent cancel (armed drag, no PATCH, no toast). Announce
        // the rule as a quiet info toast; other rejections (defensive
        // unresolvable slots) keep the silent rollback.
        const target = slotKeyToScheduledAt(slotKey)
        const targetTime = target ? new Date(target).getTime() : NaN
        if (Number.isFinite(targetTime) && targetTime < Date.now() + 60 * 1000) {
          toast.info('No se puede mover a un horario pasado')
        }
        return
      }
      rescheduleAppointment(session.appointment, slotKey)
    }

    const onDragPointerCancel = () => {
      const session = dragSession
      if (!session) return
      endDrag({ suppressClick: session.active })
    }

    const onDragKeyDown = event => {
      if (event.key !== 'Escape') return
      const session = dragSession
      if (!session) return
      endDrag({ suppressClick: session.active })
    }

    const conflictToastMessage = conflict => {
      const other = conflict.conflicting_appointment
      const blocker =
        conflict.type === 'chair_conflict'
          ? `El ambiente ${other?.dental_chair?.name || 'asignado'} ya está ocupado`
          : 'El profesional ya tiene una cita en ese horario'
      const detail =
        other?.patient?.full_name && other?.scheduled_at
          ? `${other.patient.full_name} (${formatTimeRange(other.scheduled_at, other.ends_at || other.scheduled_at)})`
          : other?.patient?.full_name || ''
      return detail ? `${blocker}. Conflicto con ${detail}.` : `${blocker}.`
    }

    const rescheduleAppointment = async (appointment, slotKey) => {
      const scheduledAt = slotKeyToScheduledAt(slotKey)
      if (!scheduledAt) return
      const durationMinutes = Number(appointment.duration_minutes) || 60
      const original = {
        scheduled_at: appointment.scheduled_at,
        ends_at: appointment.ends_at
      }
      appointment.scheduled_at = scheduledAt
      appointment.ends_at = addMinutesToLocal(scheduledAt, durationMinutes)
      try {
        await patch(`/api/appointments/${appointment.id}/reschedule`, {
          scheduled_at: scheduledAt,
          duration_minutes: durationMinutes
        })
        toast.success('Cita reprogramada')
      } catch (error) {
        appointment.scheduled_at = original.scheduled_at
        appointment.ends_at = original.ends_at
        const conflict = error?.response?.data?.conflict
        if (conflict) {
          toast.error(conflictToastMessage(conflict))
        } else {
          toast.error(normalizeError(error, 'No se pudo reprogramar la cita'))
        }
      }
    }

    const editAppointment = appointment => {
      editingAppointment.value = appointment
      newAppointmentPrefill.value = null
      showNewAppointmentModal.value = true
      selectedAppointment.value = null
    }

    const changeStatus = async (appointment, newStatus) => {
      // #394: cancelar es un acto deliberado con motivo documentado. El resto
      // de estados conserva el camino directo.
      if (newStatus === 'cancelled') {
        openCancellationDialog(appointment)
        return
      }

      try {
        // Slice 01 / T-01.5 (API-018): use the dedicated PATCH route for
        // status updates instead of PUT to /appointments/{id} (which only
        // persists `notes` on completed appointments and silently ignores
        // `status`).
        const { patch } = useApi()
        await patch(`/api/appointments/${appointment.id}/status`, { status: newStatus })
        // T2: `PATCH /status` emite `AppointmentUpdated`; el mismo cambio no se
        // anuncia dos veces.
        markLocalAppointmentEcho(appointment.id)
        toast.success('Estado actualizado')
        await loadAppointments()
        selectedAppointment.value = null
      } catch (error) {
        const errorMessage = error?.response?.data?.message || 'Error al cambiar el estado'
        toast.error(errorMessage)
      }
    }

    // #394 — la cancelación pide motivo y lo manda como parte del mismo acto de
    // la cita. El aviso de éxito nombra los recordatorios que se cancelaron.
    const cancellingAppointment = ref(null)
    const cancellationReason = ref('')
    const cancellationError = ref('')
    const cancellationSubmitting = ref(false)
    const cancellationReasonField = ref(null)

    const openCancellationDialog = appointment => {
      cancellingAppointment.value = appointment
      cancellationReason.value = ''
      cancellationError.value = ''
    }

    const resetCancellationDialog = () => {
      cancellingAppointment.value = null
      cancellationReason.value = ''
      cancellationError.value = ''
    }

    const closeCancellationDialog = () => {
      if (cancellationSubmitting.value) return
      resetCancellationDialog()
    }

    // El foco aterriza en el motivo: la confirmación se resuelve con teclado.
    watch(
      cancellingAppointment,
      appointment => {
        if (!appointment) return
        nextTick(() => {
          const field = cancellationReasonField.value?.$el?.querySelector('textarea')
          field?.focus()
        })
      },
      { flush: 'post' }
    )

    const submitCancellation = async () => {
      const appointment = cancellingAppointment.value
      if (!appointment || cancellationSubmitting.value) return

      const reason = String(cancellationReason.value || '').trim()
      if (!reason) {
        cancellationError.value = 'Indica el motivo de la cancelación.'
        return
      }

      cancellationSubmitting.value = true
      cancellationError.value = ''

      try {
        const response = await patch(`/api/appointments/${appointment.id}/status`, {
          status: 'cancelled',
          cancellation_reason: reason
        })
        markLocalAppointmentEcho(appointment.id)
        const cancelledReminders = Number(response?.meta?.reminders_cancelled || 0)
        toast.success(
          cancelledReminders > 0
            ? `Cita cancelada. Se cancelaron ${cancelledReminders} recordatorios pendientes.`
            : 'Cita cancelada.'
        )
        resetCancellationDialog()
        selectedAppointment.value = null
        await loadAppointments()
      } catch (error) {
        // Un 422 se queda en el campo y el diálogo sigue abierto.
        cancellationError.value = normalizeError(error, 'No se pudo cancelar la cita')
      } finally {
        cancellationSubmitting.value = false
      }
    }

    const deleteAppointment = async appointment => {
      const ok = await confirm({
        title: 'Eliminar cita',
        message: '¿Estás seguro de que quieres eliminar esta cita?',
        confirmText: 'Eliminar',
        variant: 'danger'
      })
      if (ok) {
        try {
          await del(`/api/appointments/${appointment.id}`)
          markLocalAppointmentEcho(appointment.id)
          toast.success('Cita eliminada exitosamente')
          await loadAppointments()
          selectedAppointment.value = null
        } catch (error) {
          const errorMessage = error?.response?.data?.message || 'Error al eliminar la cita'
          toast.error(errorMessage)
        }
      }
    }

    const formatHour = hour => {
      return `${hour.toString().padStart(2, '0')}:00`
    }

    const formatDateTime = dateTime => {
      return formatCanonicalDateTime(dateTime, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    }

    // Método para manejar evento de cita creada
    // WebSocket subscriptions
    let appointmentsChannel = null

    watch(showNewAppointmentModal, isOpen => {
      if (!isOpen) {
        editingAppointment.value = null
        newAppointmentChairId.value = null
        newAppointmentProfessionalId.value = null
      }
    })

    // El reloj del tablero: la línea de «ahora», el estado de cada columna y la
    // espera de la sala se mueven solos cada minuto, sin otra lectura.
    let nowTimer = null

    onMounted(() => {
      // #397 S7: el affordance del scroll horizontal se remide con el viewport.
      window.addEventListener('resize', readWeekScrollState)
      window.addEventListener('resize', readBoardScrollState)
      window.addEventListener('resize', readStripScrollState)
      window.addEventListener('resize', readLegendScrollState)
      document.addEventListener('pointerdown', handleDetailsPointerDown)
      nextTick(readWeekScrollState)
      nextTick(readBoardScrollState)
      nextTick(readStripScrollState)
      nextTick(readLegendScrollState)
      nowTimer = setInterval(() => {
        clockNow.value = new Date()
      }, 60000)

      // `?date=YYYY-MM-DD` abre la agenda en ese día: es el destino de la
      // confirmación del dashboard y del aviso de la notificación.
      const fromQuery = localDayOf(route.query?.date)
      if (fromQuery) currentDate.value = fromQuery

      // Las poblaciones de los filtros son una lectura aparte: se lanzan sin
      // esperar y la agenda no depende de ellas (un lookup caído sólo oculta
      // su filtro). La lectura del rango sale a continuación, nunca encadenada.
      loadFilterOptions()
      loadVisibleRange()

      // Suscribirse a canales WebSocket para actualizaciones en tiempo real
      try {
        appointmentsChannel = privateChannel('appointments')
        if (appointmentsChannel) {
          appointmentsChannel
            .listen('.appointment.created', async event => {
              // La difusión sólo sincroniza la fila. El aviso del hecho es del
              // shell (NotificationService), y el eco de la propia creación ya
              // se confirmó con la línea de esta página.
              await syncAppointmentFromEvent(event?.appointment || {})
            })
            .listen('.appointment.updated', async event => {
              // El cambio escrito aquí ya avisó con su toast local; el de otra
              // mesa lo anuncia el shell. La agenda no duplica ninguno.
              await syncAppointmentFromEvent(event?.appointment || {})
            })
            // T2 — llegada y cierre mueven el tablero (la sala, las columnas y
            // el estado de cada bloque): la difusión refresca esa cita y no
            // tosta, porque el hecho ya tiene su aviso en el escritorio que lo
            // registró.
            .listen('.appointment.checked_in', async event => {
              await syncAppointmentFromEvent(event?.appointment || {})
            })
            .listen('.appointment.completed', async event => {
              await syncAppointmentFromEvent(event?.appointment || {})
            })
            .listen('.appointment.deleted', async e => {
              // La fila sale sin otra lectura: el sobre ya nombra la cita. El
              // aviso de la anulación lo publica el shell.
              removeAppointment(e?.appointment_id)
              closeSelectedAppointment(e?.appointment_id)
            })
        }
      } catch (error) {
        // WebSocket no disponible, se sigue con polling manual
      }
    })

    onUnmounted(() => {
      window.removeEventListener('resize', readWeekScrollState)
      window.removeEventListener('resize', readBoardScrollState)
      window.removeEventListener('resize', readStripScrollState)
      window.removeEventListener('resize', readLegendScrollState)
      document.removeEventListener('pointerdown', handleDetailsPointerDown)
      if (nowTimer) {
        clearInterval(nowTimer)
        nowTimer = null
      }

      // Drag en curso: no dejar listeners de ventana huérfanos al desmontar.
      if (dragSession) {
        endDrag()
      } else {
        detachDragListeners()
      }

      // Limpiar suscripciones WebSocket
      if (echo) {
        try {
          echo.leave('private-appointments')
        } catch (e) {
          // Error al limpiar suscripción, se ignora en desmontaje
        }
      }
    })

    return {
      can,
      loading,
      loadError,
      currentView,
      currentDate,
      appointments,
      selectedAppointment,
      showNewAppointmentModal,
      editingAppointment,
      openNewAppointmentModal,
      markArrival,
      announcePatient,
      announceSelectedPatient,
      weekHours,
      weekWindowRange,
      weekHourPx: WEEK_HOUR_PX,
      weekBodyHeight,
      weekHourTop,
      weekBlockStyle,
      weekDayAppointments,
      weekDayLayout,
      weekOverlapIndicatorStyle,
      weekBlockMeta,
      weekDayNames,
      weekHourColumnPx,
      weekDayColumnMinPx,
      weekGridStyle,
      weekScrollViewport,
      weekScrollLeft,
      weekScrollRight,
      readWeekScrollState,
      weekStripDays,
      selectStripDay,
      groupBy,
      setGroupBy,
      professionalPills,
      professionalPillTotal,
      dayAppointments,
      visibleDayAppointments,
      rangeAppointments,
      rangeFilteredEmpty,
      boardColumns,
      columnHeaderTitle,
      boardGridStyle,
      boardGridMinWidth,
      boardColumnMinPx: BOARD_COLUMN_MIN_PX,
      boardBodyHeight,
      boardWindowRange,
      boardHourLabels,
      boardHourPx: BOARD_HOUR_PX,
      boardGutterPx: BOARD_GUTTER_PX,
      boardHourLabelStyle,
      boardScrollViewport,
      boardScrollRight,
      readBoardScrollState,
      boardScrollLeft,
      boardGridRendered,
      stripScrollViewport,
      stripScrollLeft,
      stripScrollRight,
      readStripScrollState,
      legendScrollViewport,
      legendScrollLeft,
      legendScrollRight,
      readLegendScrollState,
      blockAllowsTwoLines,
      boardBlockStyle,
      overlapIndicatorStyle,
      boardBlockTier,
      boardBlockTimeLabel,
      boardBlockMeta,
      boardBlockMetaVisible,
      boardBlockFooter,
      boardBlockLabel,
      appointmentPatientName: patientNameOf,
      freeSlotLabel,
      gapIsBookable,
      calendarScrollMaxHeight,
      timeAtMinutes,
      showNowLine,
      nowLineTop,
      nowLabel,
      dayOccupancyPercent,
      ambienteCountCaption,
      dayEmpty,
      arrivedToday,
      unconfirmedToday,
      hasPendingPanel,
      hourLabelHidden,
      waitingLine,
      patientInitials,
      labReadyToday,
      panelCountVariant,
      labCasePatientName,
      labCaseLine,
      labCaseInitials,
      refreshAgenda,
      currentPeriodTitle,
      headerKicker,
      headerTitle,
      headerContext,
      legendStatuses,
      loadedAppointmentsCaption,
      filters,
      professionalOptions,
      chairOptions,
      branchOptions,
      appliedFilterSummary,
      seesWholeAgenda,
      showChairFilter,
      showBranchFilter,
      hasActiveFilters,
      setFilter,
      clearFilters,
      statusLabel,
      statusRailClasses,
      statusBlockTitleClasses,
      statusBlockMetaClasses,
      weekDays,
      monthDays,
      monthCellClasses,
      monthDayNumberClasses,
      drillDownToDate,
      loadAppointments,
      changeView,
      previousPeriod,
      nextPeriod,
      goToToday,
      dayDateString,
      appointmentTimeRange,
      chipTimeRange,
      chipTitle,
      ambienteLabel,
      openSlotCreate,
      newAppointmentPrefill,
      newAppointmentChairId,
      newAppointmentProfessionalId,
      getAppointmentClasses,
      getStatusClasses,
      getStatusText,
      getStatusBadgeProps,
      isTerminalOutcome,
      statusIsActive,
      railTimes,
      primaryAction,
      nowCopy,
      patientMetaLine,
      statusSince,
      showMyDayLink,
      startAttention,
      menuOpen,
      detailsMenuRef,
      toggleMenu,
      closeMenu,
      showCancelAction,
      openReschedule,
      selectAppointment,
      openAppointmentFromPanel,
      confirmingId,
      confirmError,
      canConfirmAppointment,
      confirmAppointment,
      closeDetails,
      emptyStateCopy,
      draggingAppointmentId,
      dropHoverSlot,
      dropTargetClasses,
      onChipPointerDown,
      editAppointment,
      changeStatus,
      cancellingAppointment,
      cancellationReason,
      cancellationError,
      cancellationSubmitting,
      cancellationReasonField,
      closeCancellationDialog,
      submitCancellation,
      deleteAppointment,
      getAppointmentLabel,
      formatHour,
      formatDateTime,
      formatTime,
      getInitialDateForModal,
      handleAppointmentSaved,
      handleAppointmentCreated,
      createdConfirmation,
      createdHighlightId,
      dismissCreatedConfirmation,
      revealCreatedAppointment,
      toLocalDateString,
      getAppointmentLocalDate
    }
  }
}
</script>
