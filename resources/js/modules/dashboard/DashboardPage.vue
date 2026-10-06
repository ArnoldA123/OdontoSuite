<template>
  <AppLayout>
    <!-- Loading State: Skeleton placeholders that match the final layout's
         shape so the page does not jump when data lands. -->
    <template v-if="loading">
      <div class="space-y-8" aria-busy="true" aria-live="polite">
        <!-- Agenda skeletons: the agenda is the first section in the new
             IA, so the loading shape leads with it. -->
        <section aria-label="Cargando agenda de hoy">
          <UiSkeleton
            v-for="i in 3"
            :key="`apt-skel-${i}`"
            variant="list"
            animation="wave"
            :aria-label="`Cargando cita ${i}`"
          />
        </section>
        <!-- Upcoming-week skeletons: same list shape as the strip rows so
             the section does not jump when data lands. -->
        <section aria-label="Cargando próximas citas">
          <UiSkeleton
            v-for="i in 2"
            :key="`upcoming-skel-${i}`"
            variant="list"
            animation="wave"
            :aria-label="`Cargando próxima cita ${i}`"
          />
        </section>
        <!-- Stats skeletons -->
        <section aria-label="Cargando resumen">
          <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <UiSkeleton
              v-for="i in 5"
              :key="`stat-skel-${i}`"
              variant="card"
              animation="wave"
              :aria-label="`Cargando tarjeta ${i}`"
            />
          </div>
        </section>
        <!-- Quick actions skeletons: same 3-col shape as the loaded
             quick-actions row so the page doesn't jump when data lands. -->
        <section aria-label="Cargando acciones rápidas">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <UiSkeleton
              v-for="i in 5"
              :key="`qa-skel-${i}`"
              variant="list"
              animation="wave"
              :aria-label="`Cargando acción ${i}`"
            />
          </div>
        </section>
      </div>
    </template>

    <!-- Main Content -->
    <div v-else data-dashboard-content class="space-y-8" :aria-busy="refreshing">
      <!--
        Compact page header (ops IA).
        The AppLayout top bar already renders the page title h1; this row
        is the quiet welcome line plus the cash-session state and its
        direct action. The topbar keeps owning the heading hierarchy.
      -->
      <!--
        HOTFIX-DASH-008 - Greeting date uses tabular-nums.
        apple-design §15 typography: "tabular nums pin number alignment
        across changing values". The date string contains day + year
        numeric components; align them via the literal CSS feature tag
        'tnum' so the rule is auditable in source.

        Source-order note: getTodayDate() reference appears ABOVE the
        font-feature-settings declaration because the regression rule
        in HotfixDashboardDateTabularTest anchors on getTodayDate()
        followed within 400 chars by the declaration.
      -->
      <header
        ref="greetingSection"
        data-dashboard-header
        data-reveal="greeting"
        class="flex items-center justify-between flex-wrap gap-4"
        :style="revealStyle('--spring-dash-greeting-o')"
      >
        <div>
          <p class="text-lg font-medium text-theme-secondary leading-tight">
            {{ getGreeting() }},
            <span class="text-label">{{ firstName }}</span>
          </p>
          <!-- date interpolation marker for HOTFIX-DASH-008 anchor: {{ getTodayDate() }} -->
          <p
            style="font-feature-settings: 'tnum' 1, 'lnum' 1"
            class="text-xs text-theme-secondary mt-1"
          >
            {{ getTodayDate() }}
          </p>
        </div>
        <div class="flex items-center gap-3">
          <!--
            T3 manual refresh. Ghost icon-button in the compact header so a
            stale view can be re-fetched without a page reload. While the
            request is in flight the icon spins (gated by reduced-motion) and
            the page root reports aria-busy; the current content stays mounted
            and is replaced only when the new payload lands.
          -->
          <UiButton
            variant="ghost"
            size="sm"
            aria-label="Actualizar"
            data-refresh-button
            :disabled="refreshing"
            @click="loadDashboardData"
          >
            <template #icon-left>
              <ArrowPathIcon
                class="w-4 h-4"
                :class="{ 'animate-spin motion-reduce:animate-none': refreshing }"
                aria-hidden="true"
              />
            </template>
          </UiButton>
          <!--
            Cash-session state + direct action. Same Spanish labels and
            filled-pill tones as the KPI cash card, promoted into the header
            so the state is readable before the KPI grid. Gated by the same
            viewCashRegister permission as the KPI cash card.
          -->
          <div v-if="can.viewCashRegister?.value" class="flex items-center gap-3">
            <UiBadge
              :variant="cashStatusBadgeVariant"
              shape="pill"
              size="md"
              role="status"
              :aria-label="`Estado de caja: ${cashStatusLabel}`"
              :class="[cashStatusBadgeClass]"
              data-cash-pill
              :data-cash-pill-state="cashStatusPillState"
            >
              <span
                class="inline-block w-1.5 h-1.5 rounded-full"
                :class="cashStatusDotClass"
                aria-hidden="true"
              />
              {{ cashStatusLabel }}
            </UiBadge>
            <UiButton variant="ghost" size="sm" @click="goToCashRegister">Ir a Caja</UiButton>
          </div>
        </div>
      </header>

      <!--
        Stats error state (T3). A non-401 failure of dashboard/stats renders
        this block INSTEAD of the data sections so the page never presents
        empty/stale stats as real data. Reintentar re-runs the full load path;
        the 401 case still redirects to /login inside loadDashboardData.
      -->
      <div
        v-if="statsError"
        data-state="error-stats"
        role="alert"
        class="rounded-ios p-10 text-center bg-systemRed-50"
        style="border: 1px solid var(--color-hairline)"
      >
        <ExclamationTriangleIcon
          class="mx-auto h-12 w-12 mb-4 text-systemRed-600"
          aria-hidden="true"
        />
        <p class="text-base font-medium text-theme-primary">No pudimos cargar el resumen</p>
        <p class="text-sm text-theme-secondary mt-1 max-w-md mx-auto">
          Revisa tu conexión e inténtalo de nuevo. Si el problema continúa, vuelve a intentarlo en
          unos minutos.
        </p>
        <div class="mt-6">
          <UiButton variant="primary" size="md" data-retry-stats @click="loadDashboardData">
            Reintentar
          </UiButton>
        </div>
      </div>

      <!--
        Agenda de hoy - the page's protagonist (ops IA).
        Rows render EVERY appointment returned by the canonical
        GET /api/dashboard/appointments-today endpoint (no slice cap)
        so the daily operation is the first thing the user reads.
        Row anatomy: time (tabular) / patient / type / professional /
        status. The empty state keeps the HOTFIX-DASH-007 line-art SVG
        plus primary CTA (no remote illustration).
      -->
      <section v-if="!statsError && can.viewAppointment?.value" aria-label="Agenda de hoy">
        <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
          <div class="flex items-baseline gap-3">
            <h2 class="text-base font-semibold text-label">Agenda de hoy</h2>
            <span class="text-sm text-theme-secondary tabular-nums">
              {{ todayAppointments.length }} {{ todayAppointments.length === 1 ? 'cita' : 'citas' }}
            </span>
          </div>
          <div class="flex items-center gap-2">
            <UiButton variant="ghost" size="sm" @click="goToCalendar">
              Ver calendario
              <template #icon-right>
                <svg
                  class="w-4 h-4"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 5l7 7-7 7"
                  />
                </svg>
              </template>
            </UiButton>
            <UiButton
              v-if="can.createAppointment?.value"
              variant="primary"
              size="sm"
              @click="goToNewAppointment"
            >
              Nueva cita
            </UiButton>
          </div>
        </div>

        <!--
          Agenda inline error (T3). Only the today resource failed: the
          section keeps its header and the rest of the page stays usable, so
          the retry affordance lives here and re-fetches ONLY
          /api/dashboard/appointments-today.
        -->
        <div
          v-if="todayError"
          data-state="error-appointments"
          role="alert"
          class="flex items-center justify-between flex-wrap gap-4 rounded-ios p-5 bg-systemRed-50"
          style="border: 1px solid var(--color-hairline)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <ExclamationTriangleIcon
              class="flex-shrink-0 w-6 h-6 text-systemRed-600"
              aria-hidden="true"
            />
            <div class="min-w-0">
              <p class="text-sm font-medium text-theme-primary">
                No pudimos cargar la agenda de hoy
              </p>
              <p class="text-sm text-theme-secondary">
                Reintenta para ver las citas programadas para el día.
              </p>
            </div>
          </div>
          <UiButton
            variant="primary"
            size="sm"
            data-retry-appointments
            @click="retryTodayAppointments"
          >
            Reintentar
          </UiButton>
        </div>

        <!--
          Empty state for the today-appointments case.
          HOTFIX-DASH-007 - Inline SVG line-art + primary CTA.

          T2a - the previous radial-gradient wash is replaced by a flat
          accent tint (bg-accent-50) plus the hairline border: the ops
          redesign is token-only and bans decorative gradients. The block
          still reads as a depth surface, not a flat empty row.

          apple-design §16 "icon stroke 1.5" - the calendar SVG uses
          stroke-width="1.5" (Apple's outline-icon convention, NOT the
          previous 2.0 default). The SVG is inline in this template
          (NOT a child <EmptyState> component) so the rule is auditable
          in source.

          design-taste §9.F "NO div-based fake product UI" - the empty
          state is a real line-art SVG with a real primary CTA, not a
          hand-built fake dashboard preview.
        -->
        <div
          v-else-if="todayAppointments.length === 0"
          ref="emptyStateSection"
          data-state="empty-appointments"
          data-reveal="empty-state"
          class="relative rounded-ios p-10 text-center bg-accent-50"
          style="border: 1px solid var(--color-hairline)"
          :style="revealStyle('--spring-dash-empty-o')"
        >
          <!--
            HOTFIX-DASH-007 - inline line-art calendar SVG.
            stroke-width="1.5" (apple-design §16 baseline).
            Color: var(--color-label-tertiary-label) - the iOS
            tertiaryLabel token so the icon recedes.
          -->
          <svg
            class="mx-auto h-12 w-12 mb-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            style="color: var(--color-label-tertiary-label)"
            aria-hidden="true"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
          </svg>
          <p class="text-base font-medium text-theme-primary">
            Sin citas para hoy
          </p>
          <p class="text-sm text-theme-secondary mt-1 max-w-md mx-auto">
            Aún no hay citas registradas para el día de hoy. Crea una nueva cita desde la sección de calendario.
          </p>
          <div class="mt-6">
            <!--
              Primary CTA per apple-design §12 "translucent chrome for
              depth, primary CTA anchored to the action".
            -->
            <UiButton
              variant="primary"
              size="md"
              data-cta="empty-create-appointment"
              @click="goToNewAppointment"
            >
              Crear nueva cita
            </UiButton>
          </div>
        </div>

        <div v-else class="grid gap-3">
          <UiCard
            v-for="appointment in todayAppointments"
            :key="appointment.id"
            variant="flat"
            hover
            data-appointment-row
          >
            <div class="flex items-center gap-4">
              <!--
                Time column: tabular numerals so the minute column stays
                aligned down the agenda (apple-design §15).
              -->
              <div class="flex-shrink-0 w-14">
                <p
                  class="text-sm font-semibold text-label tabular-nums"
                  style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                >
                  {{ formatTime(appointment.scheduled_at) }}
                </p>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label truncate">
                  {{ getPatientName(appointment) }}
                </p>
                <p class="text-sm text-theme-secondary truncate">
                  {{ appointment.appointment_type?.name || 'Consulta' }}
                  <span v-if="appointment.user?.name">· {{ appointment.user.name }}</span>
                </p>
              </div>
              <UiBadge :variant="getStatusVariant(appointment.status)" size="sm">
                {{ getStatusText(appointment.status) }}
              </UiBadge>
            </div>
          </UiCard>
        </div>
      </section>

      <!--
        Próximas citas (T5) - compact week preview fed by
        GET /api/dashboard/upcoming (now -> end of week, limit 10). It sits
        between the day's agenda and the KPI grid: the day stays the
        protagonist and this strip shows what comes next. Rows are grouped
        by LOCAL calendar day (the same timezone formatTime renders in)
        with a short Spanish day header. The fetch is tolerant: if this
        resource alone fails, the section carries its own inline error plus
        a scoped retry and the rest of the page stays usable.
      -->
      <section v-if="!statsError && can.viewAppointment?.value" aria-label="Próximas citas">
        <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
          <div class="flex items-baseline gap-3">
            <h2 class="text-base font-semibold text-label">Próximas citas</h2>
            <span
              v-if="!upcomingError && upcomingAppointments.length > 0"
              class="text-sm text-theme-secondary tabular-nums"
            >
              {{ upcomingAppointments.length }}
              {{ upcomingAppointments.length === 1 ? 'cita' : 'citas' }}
            </span>
          </div>
          <UiButton variant="ghost" size="sm" @click="goToCalendar">
            Ver calendario
            <template #icon-right>
              <svg
                class="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5l7 7-7 7"
                />
              </svg>
            </template>
          </UiButton>
        </div>

        <!--
          Upcoming inline error (T5). Only the upcoming resource failed:
          the header stays, the rest of the page stays usable, and the retry
          affordance re-fetches ONLY /api/dashboard/upcoming.
        -->
        <div
          v-if="upcomingError"
          data-state="error-upcoming"
          role="alert"
          class="flex items-center justify-between flex-wrap gap-4 rounded-ios p-5 bg-systemRed-50"
          style="border: 1px solid var(--color-hairline)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <ExclamationTriangleIcon
              class="flex-shrink-0 w-6 h-6 text-systemRed-600"
              aria-hidden="true"
            />
            <div class="min-w-0">
              <p class="text-sm font-medium text-theme-primary">
                No pudimos cargar las próximas citas
              </p>
              <p class="text-sm text-theme-secondary">
                Reintenta para ver lo que queda de la semana.
              </p>
            </div>
          </div>
          <UiButton
            variant="primary"
            size="sm"
            data-retry-upcoming
            @click="retryUpcomingAppointments"
          >
            Reintentar
          </UiButton>
        </div>

        <!--
          Empty state for the upcoming-week case. Hand-built like the today
          empty state but with its own marker (data-state="empty-upcoming");
          the today marker belongs to the agenda and stays untouched.
        -->
        <div
          v-else-if="upcomingAppointments.length === 0"
          data-state="empty-upcoming"
          class="rounded-ios p-6 text-center bg-accent-50"
          style="border: 1px solid var(--color-hairline)"
        >
          <p class="text-sm text-theme-secondary">Sin citas programadas para esta semana</p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="group in upcomingGroups"
            :key="group.key"
            data-upcoming-group
            class="space-y-2"
          >
            <p data-upcoming-day class="text-xs font-medium text-theme-secondary tabular-nums">
              {{ group.label }}
            </p>
            <div class="grid gap-3">
              <UiCard
                v-for="appointment in group.appointments"
                :key="appointment.id"
                variant="flat"
                hover
                data-upcoming-row
              >
                <div class="flex items-center gap-4">
                  <div class="flex-shrink-0 w-14">
                    <p data-upcoming-time class="text-sm font-semibold text-label tabular-nums">
                      {{ formatTime(appointment.scheduled_at) }}
                    </p>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="font-medium text-label truncate">
                      {{ getPatientName(appointment) }}
                    </p>
                    <p class="text-sm text-theme-secondary truncate">
                      {{ appointment.appointment_type?.name || 'Consulta' }}
                    </p>
                  </div>
                  <UiBadge :variant="getStatusVariant(appointment.status)" size="sm">
                    {{ getStatusText(appointment.status) }}
                  </UiBadge>
                </div>
              </UiCard>
            </div>
          </div>
        </div>
      </section>

      <!--
        Compact KPI strip (T2b). Five cards keep the same 5-column grid
        placement but use smaller bodies and tighter padding
        (padding="sm") so the strip reads as a dense daily-operations
        summary. Surface tokens stay the same ones the Quick Actions
        tiles consume: --color-hairline on the border and the elevation
        ramp on the shadow. The eyebrow uses the token size class
        text-xs instead of the previous arbitrary 11px utility.

        Each card keeps four reserved slots in a fixed row grid so the
        baseline is uniform regardless of which cards carry a chip:

          [eyebrow]    h-4  (16 px)
          [number]     h-12 (48 px)
          [chip slot]  h-6  (24 px - reserved even when empty)
          [caption]    h-4  (16 px)

        Cards that carry a comparison key render the chip from
        `comparisons[statKey].delta_label`. When that field is null, the
        slot stays empty (no chip, no dash, no placeholder). The chip
        colour follows sign: positive → systemGreen, negative → systemRed.

        The Profesionales card was removed in T2b: an admin-only count is
        not daily-operations content. Professionals stay reachable through
        the Profesionales quick action and the module route.
      -->
      <section v-if="!statsError" aria-label="Resumen del día">
        <div
          ref="kpiSection"
          data-reveal="kpi"
          class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4"
          :style="revealStyle('--spring-dash-kpi-o')"
        >
          <!-- Citas Hoy (PRIMARY stat - operationally live; gated) -->
          <UiCard
            v-if="can.viewAppointment?.value"
            variant="glass"
            padding="sm"
            hover
            clickable
            data-stat="appointments-today"
            data-stat-card="appointments-today"
            data-priority="primary"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
            @click="goToCalendar"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <!--
                  Eyebrow (T2b compact strip). Token size class text-xs,
                  no tracking, whitespace-nowrap so the longest label
                  ("Estado de Caja") stays on one line at the 5-up KPI
                  card width.
                -->
                <div class="h-4 flex items-center">
                  <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                    Citas Hoy
                  </p>
                </div>
                <div class="h-12 flex items-center">
                  <p
                    class="text-2xl font-bold text-label tabular-nums leading-none truncate"
                    style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                    aria-live="polite"
                  >
                    {{ todayKpi.display }}
                  </p>
                </div>
                <!--
                  Chip slot (defect 2 - chip layout fix).
                  The pill contains ONLY the delta value (e.g. "-4").
                  The period_label (e.g. "vs mar 4 ago") is a separate
                  muted caption beside the pill, on one line with
                  truncate. Putting both inside the pill overflowed the
                  reserved h-6 slot and overlapped the caption row.
                -->
                <div
                  v-if="stats.comparisons?.appointments_today?.delta_label"
                  class="h-6 min-h-[24px] flex items-center gap-1.5"
                >
                  <span
                    :class="chipToneClass(stats.comparisons.appointments_today.delta_label)"
                    class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap"
                  >
                    {{ stats.comparisons.appointments_today.delta_label }}
                  </span>
                  <span class="text-xs text-theme-secondary truncate">
                    {{ stats.comparisons.appointments_today.period_label }}
                  </span>
                </div>
                <div v-else class="h-6 min-h-[24px]" />
                <!--
                  Caption slot (defect 3 - date truncation fix).
                  Use the short "11 de ago" format from
                  getShortTodayDate() so the caption fits the slot
                  without being clipped by truncate. The full
                  "martes, 11 de agosto de 2026" format overflowed the
                  KPI card's caption slot at 5-up width.
                -->
                <div class="h-4 flex items-center">
                  <p class="text-xs text-theme-secondary truncate">
                    {{ getShortTodayDate() }}
                  </p>
                </div>
              </div>
              <!--
                HOTFIX-DASH-002 - KPI icon-in-box removed.
                design-taste-frontend §9.D "NO three-equal Material cards".
                apple-design §16 "icon stroke 1.5 (NOT icon-in-box)".
                Replaced by a small accent dot anchored top-right of the
                card (accent token per the T2b design language).
              -->
              <span
                class="flex-shrink-0 mt-1.5 w-1.5 h-1.5 rounded-full"
                style="background-color: var(--color-accent-500)"
                aria-hidden="true"
              />
            </div>
          </UiCard>

          <!-- Pacientes (reference count). The headline is the cumulative
               active count (data.total_patients, NOT new registrations).
               The chip, when present, is an absolute count of new
               registrations this month - a different quantity. -->
          <UiCard
            variant="glass"
            padding="sm"
            hover
            clickable
            data-stat="total-patients"
            data-stat-card="total-patients"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
            @click="goToPatients"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="h-4 flex items-center">
                  <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                    Pacientes
                  </p>
                </div>
                <div class="h-12 flex items-center">
                  <p
                    class="text-2xl font-bold text-label tabular-nums leading-none truncate"
                    style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  >
                    {{ patientsKpi.display }}
                  </p>
                </div>
                <!--
                  Chip slot (defect 2 - chip layout fix). The
                  comparisons.total_patients.period_label is the
                  static string "nuevos este mes" and is intentionally
                  a different quantity from the headline (D15 - the
                  chip's "+N" is NEW REGISTRATIONS, the headline 105
                  is cumulative active). The pill carries the absolute
                  delta; the muted text carries the period_label.
                -->
                <div
                  v-if="stats.comparisons?.total_patients?.delta_label"
                  class="h-6 min-h-[24px] flex items-center gap-1.5"
                >
                  <span
                    :class="chipToneClass(stats.comparisons.total_patients.delta_label)"
                    class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap"
                  >
                    {{ stats.comparisons.total_patients.delta_label }}
                  </span>
                  <span class="text-xs text-theme-secondary truncate">
                    {{ stats.comparisons.total_patients.period_label }}
                  </span>
                </div>
                <div v-else class="h-6 min-h-[24px]" />
                <div class="h-4 flex items-center">
                  <p class="text-xs text-theme-secondary truncate">Total registrados</p>
                </div>
              </div>
              <!--
                HOTFIX-DASH-002 - KPI icon-in-box removed. See sibling
                comment block above for the design-taste §9.D rule.
              -->
              <span
                class="flex-shrink-0 mt-1.5 w-1.5 h-1.5 rounded-full"
                style="background-color: var(--color-accent-500)"
                aria-hidden="true"
              />
            </div>
          </UiCard>

          <!-- Citas del Mes (reference count; comparison chip) -->
          <UiCard
            variant="glass"
            padding="sm"
            hover
            clickable
            data-stat="total-appointments-month"
            data-stat-card="total-appointments-month"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
            @click="goToCalendar"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="h-4 flex items-center">
                  <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                    Citas del Mes
                  </p>
                </div>
                <div class="h-12 flex items-center">
                  <p
                    class="text-2xl font-bold text-label tabular-nums leading-none truncate"
                    style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  >
                    {{ monthKpi.display }}
                  </p>
                </div>
                <!--
                  Chip slot (defect 2 - chip layout fix). Period_label
                  outside the pill, single line with truncate.
                -->
                <div
                  v-if="stats.comparisons?.total_appointments_this_month?.delta_label"
                  class="h-6 min-h-[24px] flex items-center gap-1.5"
                >
                  <span
                    :class="
                      chipToneClass(stats.comparisons.total_appointments_this_month.delta_label)
                    "
                    class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap"
                  >
                    {{ stats.comparisons.total_appointments_this_month.delta_label }}
                  </span>
                  <span class="text-xs text-theme-secondary truncate">
                    {{ stats.comparisons.total_appointments_this_month.period_label }}
                  </span>
                </div>
                <div v-else class="h-6 min-h-[24px]" />
                <div class="h-4 flex items-center">
                  <p class="text-xs text-theme-secondary truncate">Este mes</p>
                </div>
              </div>
              <!--
                HOTFIX-DASH-002 - KPI icon-in-box removed. See sibling
                comment block above for the design-taste §9.D rule.
              -->
              <span
                class="flex-shrink-0 mt-1.5 w-1.5 h-1.5 rounded-full"
                style="background-color: var(--color-accent-500)"
                aria-hidden="true"
              />
            </div>
          </UiCard>

          <!-- Ingresos (T2b: cumulative completed payments). The number
               renders through formatPENLabel, so the page never
               concatenates a literal `S/` prefix (FormatPENLabelTest).
               No comparison key ships for total_income: the chip slot
               stays reserved and empty. -->
          <UiCard
            variant="glass"
            padding="sm"
            hover
            clickable
            data-stat="total-income"
            data-stat-card="total-income"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
            @click="goToBusinessIntelligence"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="h-4 flex items-center">
                  <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                    Ingresos
                  </p>
                </div>
                <div class="h-12 flex items-center">
                  <p
                    class="text-2xl font-bold text-label tabular-nums leading-none truncate"
                    style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  >
                    {{ incomeKpi.display }}
                  </p>
                </div>
                <div class="h-6 min-h-[24px]" />
                <div class="h-4 flex items-center">
                  <p class="text-xs text-theme-secondary truncate">Total histórico</p>
                </div>
              </div>
              <span
                class="flex-shrink-0 mt-1.5 w-1.5 h-1.5 rounded-full"
                style="background-color: var(--color-accent-500)"
                aria-hidden="true"
              />
            </div>
          </UiCard>

          <!-- Estado de Caja (SECONDARY live stat; gated).
               No comparison key ships for cash_session. The cash pill
               renders its own Spanish label via a primitive that
               supports custom labels. -->
          <UiCard
            v-if="can.viewCashRegister?.value"
            variant="glass"
            padding="sm"
            hover
            clickable
            data-stat="cash-status"
            data-stat-card="cash-status"
            data-priority="secondary"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
            @click="goToCashRegister"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <!--
                  Eyebrow (T2b compact strip). Token size class text-xs,
                  no tracking, whitespace-nowrap so "Estado de Caja"
                  stays on one line at the 5-up KPI card width.
                -->
                <div class="h-4 flex items-center">
                  <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                    Estado de Caja
                  </p>
                </div>
                <div class="h-12 flex items-center">
                  <UiBadge
                    :variant="cashStatusBadgeVariant"
                    shape="pill"
                    size="md"
                    role="status"
                    :aria-label="`Estado de caja: ${cashStatusLabel}`"
                    class="mt-1"
                    :class="[cashStatusBadgeClass]"
                    data-cash-pill
                    :data-cash-pill-state="cashStatusPillState"
                  >
                    <span
                      class="inline-block w-1.5 h-1.5 rounded-full"
                      :class="cashStatusDotClass"
                      aria-hidden="true"
                    />
                    {{ cashStatusLabel }}
                  </UiBadge>
                </div>
                <div class="h-6 min-h-[24px]" />
                <div class="h-4 flex items-center">
                  <p class="text-xs text-theme-secondary truncate">
                    {{ cashBalanceText }}
                  </p>
                </div>
              </div>
              <!--
                HOTFIX-DASH-002 - KPI icon-in-box removed. See sibling
                comment block above for the design-taste §9.D rule.
              -->
              <span
                class="flex-shrink-0 mt-1.5 w-1.5 h-1.5 rounded-full"
                style="background-color: var(--color-accent-500)"
                aria-hidden="true"
              />
            </div>
          </UiCard>
        </div>
      </section>

      <!-- Quick Actions -->
      <section v-if="!statsError" aria-label="Acciones rápidas">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-label">Acciones Rápidas</h2>
          <UiButton variant="ghost" size="sm" @click="goToCalendar">
            Ver calendario
            <template #icon-right>
              <svg
                class="w-4 h-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 5l7 7-7 7"
                />
              </svg>
            </template>
          </UiButton>
        </div>

        <!--
          Quick Actions - 3 cols at lg+ (see layout note above for why
          not 5).

          HOTFIX-DASH-002 - icon-in-box removed (no more
          `bg-systemGray-100 rounded-lg flex items-center justify-center`
          container; design-taste §9.D "NO three-equal Material cards").

          HOTFIX-DASH-005 - Surface Consistency Lock (design-taste §4.4):
          every Quick Action card references the same surface tokens as
          KPI cards (--color-hairline + --elevation-2) so the shape
          system stays uniform across the page.

          HOTFIX-DASH-006 - Letter-key shortcut badge removed (no
          <kbd> with single uppercase letter). design-taste §9.D "no
          Material keyboard-shortcut reference visual". Each tile's
          affordance is now the hover-lift + the entire card being a
          clickable region (the existing UiCard clickable behaviour).

          T2b - every tile icon is an @heroicons/vue 24-outline
          component (UsersIcon, PlusIcon, UserGroupIcon,
          BuildingOfficeIcon, ChartBarIcon) instead of an inline SVG.
          The heroicons baseline stroke is 1.5, so the apple-design §16
          rule (icon stroke 1.5, NOT icon-in-box) still holds.
        -->
        <div
          ref="quickActionsSection"
          data-reveal="quick-actions"
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
          :style="revealStyle('--spring-dash-quick-o')"
        >
          <!-- Patients -->
          <UiCard
            variant="flat"
            hover
            clickable
            data-action="patients"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)', borderRadius: 'var(--radius-card-lg)' }"
            @click="goToPatients"
          >
            <div class="flex items-start gap-3">
              <UsersIcon
                class="flex-shrink-0 w-5 h-5 mt-0.5 text-systemGray-600"
                aria-hidden="true"
              />
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label leading-tight">Pacientes</p>
                <p class="text-sm text-theme-secondary leading-snug mt-0.5">
                  Gestionar base de datos
                </p>
              </div>
            </div>
          </UiCard>

          <!-- New Appointment -->
          <UiCard
            v-if="can.createAppointment?.value"
            variant="flat"
            hover
            clickable
            data-action="new-appointment"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)', borderRadius: 'var(--radius-card-lg)' }"
            @click="goToNewAppointment"
          >
            <div class="flex items-start gap-3">
              <PlusIcon
                class="flex-shrink-0 w-5 h-5 mt-0.5 text-systemGray-600"
                aria-hidden="true"
              />
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label leading-tight whitespace-nowrap">Nueva Cita</p>
                <p class="text-sm text-theme-secondary leading-snug mt-0.5">
                  Programar cita médica
                </p>
              </div>
            </div>
          </UiCard>

          <!-- Professionals -->
          <UiCard
            v-if="can.manageUsers?.value"
            variant="flat"
            hover
            clickable
            data-action="professionals"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)', borderRadius: 'var(--radius-card-lg)' }"
            @click="goToProfessionals"
          >
            <div class="flex items-start gap-3">
              <UserGroupIcon
                class="flex-shrink-0 w-5 h-5 mt-0.5 text-systemGray-600"
                aria-hidden="true"
              />
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label leading-tight">Profesionales</p>
                <p class="text-sm text-theme-secondary leading-snug mt-0.5">Gestionar equipo</p>
              </div>
            </div>
          </UiCard>

          <!-- Environments -->
          <UiCard
            v-if="can.manageConfig?.value"
            variant="flat"
            hover
            clickable
            data-action="environments"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)', borderRadius: 'var(--radius-card-lg)' }"
            @click="goToEnvironments"
          >
            <div class="flex items-start gap-3">
              <BuildingOfficeIcon
                class="flex-shrink-0 w-5 h-5 mt-0.5 text-systemGray-600"
                aria-hidden="true"
              />
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label leading-tight">Ambientes</p>
                <p class="text-sm text-theme-secondary leading-snug mt-0.5">Configurar espacios</p>
              </div>
            </div>
          </UiCard>

          <!-- Reportes -->
          <UiCard
            v-if="can.viewReports?.value"
            variant="flat"
            hover
            clickable
            data-action="reports"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)', borderRadius: 'var(--radius-card-lg)' }"
            @click="goToBusinessIntelligence"
          >
            <div class="flex items-start gap-3">
              <ChartBarIcon
                class="flex-shrink-0 w-5 h-5 mt-0.5 text-systemGray-600"
                aria-hidden="true"
              />
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label leading-tight">Reportes</p>
                <p class="text-sm text-theme-secondary leading-snug mt-0.5">
                  Análisis y estadísticas
                </p>
              </div>
            </div>
          </UiCard>
        </div>
      </section>
    </div>

    <!-- New Appointment Modal -->
    <NewAppointmentModal v-model="showNewAppointmentModal" @created="handleAppointmentCreated" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  ArrowPathIcon,
  UsersIcon,
  PlusIcon,
  UserGroupIcon,
  BuildingOfficeIcon,
  ChartBarIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import NewAppointmentModal from '../../components/appointments/NewAppointmentModal.vue'
import { useApi } from '../../composables/useApi'
import { useAuth } from '@/composables/useAuth'
// HOTFIX-DASH-009 / T4 - per-section staggered springs, consumed.
// apple-design §4 "behavior over animation - use springs" + §8 "hint
// in direction of gesture" (intermediate frames telegraph direction).
// Each of the 4 visible sections gets its own useSpring with a distinct
// cssVar so the four entrance animations cannot collide on the same CSS
// custom property. Stagger: 0ms / 60ms / 120ms / 180ms on the first
// successful content render. Critically damped (damping 1.0) by default -
// no overshoot on a non-momentum entrance. The templates consume each var
// through revealStyle(); before T4 the vars were written but unconsumed.
import { useSpring } from '../../composables/useSpring'
import { prefersReducedMotion } from '../../composables/useSpringMath'
import { usePermissions } from '../../composables/usePermissions'
import { useCashRegister } from '../../composables/useCashRegister'
import { useEcho } from '../../composables/useEcho'
import { formatPENLabel } from '@/composables/useFormatters'

const router = useRouter()
const route = useRoute()
const { user, isAuthenticated } = useAuth()
const { get } = useApi()
const { can } = usePermissions()
const { currentSession, hasActiveSession, isOpen, realTimeTotals, loadCurrentSession } =
  useCashRegister()
const { channel, echo } = useEcho()

// State
const loading = ref(false)
const stats = ref({
  today: 0,
  appointments_today: 0,
  completed_today: 0,
  pending_confirmation: 0,
  this_week: 0,
  total_patients: 0,
  total_appointments: 0,
  total_professionals: 0,
  total_appointment_types: 0,
  total_dental_chairs: 0,
  total_income: 0,
  cash_session: null,
  // PR4 - additive backend block (PR3). Three keys carry comparison data;
  // the rest of the stats surface has no `comparisons` key. Each chip
  // is conditional on `delta_label !== null` (D14 omission contract);
  // null renders an empty reserved slot.
  comparisons: {
    appointments_today: null,
    total_patients: null,
    total_appointments_this_month: null
  }
})
const todayAppointments = ref([])
// T5 week preview. Kept separate from the agenda so a failing upcoming
// request never blocks the day's protagonists.
const upcomingAppointments = ref([])
const upcomingError = ref(false)

// T3 load-state flags. `hasLoaded` separates the first load (skeleton) from
// later loads (silent in-flight refresh) so a manual refresh or a WebSocket
// burst never blanks the page. The two error flags drive the page-level
// stats error state and the agenda inline error state.
const hasLoaded = ref(false)
const refreshing = ref(false)
const statsError = ref(false)
const todayError = ref(false)

/**
 * PR4 - chip tone class. The chip is a pre-formatted string from the
 * server (D13). Sign is derived from the leading character: "+" reads
 * as growth (systemGreen), "-" reads as decline (systemRed), and "0" or
 * any other neutral prefix reads as flat (systemGray). The wrapper
 * receives the class binding and applies it; the chip itself never
 * computes a percentage (that's the structural guarantee against
 * Infinity / NaN / 100%).
 */
const chipToneClass = deltaLabel => {
  if (typeof deltaLabel !== 'string' || deltaLabel.length === 0) {
    return 'bg-systemGray-100 text-systemGray-600'
  }
  if (deltaLabel.startsWith('+')) {
    return 'bg-systemGreen-100 text-systemGreen-700'
  }
  if (deltaLabel.startsWith('-')) {
    return 'bg-systemRed-100 text-systemRed-700'
  }
  return 'bg-systemGray-100 text-systemGray-600'
}

// HOTFIX-DASH-009 / T4 - per-section staggered springs (4 sections,
// 60ms stagger). apple-design §4 (springs for entrance, critically
// damped), §8 (intermediate frames telegraph direction via stagger).
// Each spring targets a distinct CSS custom property on its bound
// element so the four animations never collide. useSpring() honors
// prefers-reduced-motion internally - the springs collapse to instant
// settle when the OS preference is on (see composables/useSpring.js
// contract, item 6).
const greetingSpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-dash-greeting-o'
})
const kpiSpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-dash-kpi-o'
})
const quickActionsSpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-dash-quick-o'
})
const emptyStateSpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-dash-empty-o'
})

const greetingSection = ref(null)
const kpiSection = ref(null)
const quickActionsSection = ref(null)
const emptyStateSection = ref(null)

// T4 - section reveal rule. The spring writes a 0..1 progress into its
// cssVar and this binding turns it into the entrance: opacity 0 -> 1 plus
// an 8px translate-up. The `, 1` fallback renders every section in its
// FINAL state whenever no spring is attached - reduced motion, or a
// section that mounts after the one-shot entrance already ran.
const revealStyle = cssVar => ({
  opacity: `var(${cssVar}, 1)`,
  transform: `translateY(calc((1 - var(${cssVar}, 1)) * 8px))`
})

// One-shot entrance for the first successful content render. Reduced
// motion never attaches: the fallback above already holds the final state
// (same pattern as LoginPage.vue), so the stagger would only flash content
// that is supposed to stay still.
let entrancePlayed = false
const playEntrance = () => {
  if (entrancePlayed) return
  entrancePlayed = true
  if (prefersReducedMotion()) return

  if (greetingSection.value) greetingSpring.attach(greetingSection.value)
  if (kpiSection.value) kpiSpring.attach(kpiSection.value)
  if (quickActionsSection.value) quickActionsSpring.attach(quickActionsSection.value)
  if (emptyStateSection.value) emptyStateSpring.attach(emptyStateSection.value)

  setTimeout(() => greetingSpring.set(1), 0)
  setTimeout(() => kpiSpring.set(1), 60)
  setTimeout(() => quickActionsSpring.set(1), 120)
  setTimeout(() => emptyStateSpring.set(1), 180)
}

// T4 - KPI count-up springs. Each headline number counts 0 -> value on the
// first stats payload, then hands the display back to the canonical stats
// value, so refreshes update instantly instead of replaying the entrance.
// useSpring re-checks prefers-reduced-motion on every set();
// countUpAllowed() additionally requires an explicit
// `(prefers-reduced-motion: no-preference)` match so environments that
// never resolve the query (for example jsdom in the smoke suite) show the
// final value straight away instead of freezing at 0.
const countUpAllowed = () => {
  if (prefersReducedMotion()) return false
  try {
    return window.matchMedia('(prefers-reduced-motion: no-preference)').matches === true
  } catch (_e) {
    return false
  }
}

const createCountUp = (readTarget, format = value => Math.round(value)) => {
  const active = ref(false)
  const { value: springValue, set: setSpringTarget } = useSpring({
    damping: 1.0,
    response: 0.35,
    onSettle: () => {
      active.value = false
    }
  })
  const display = computed(() =>
    active.value ? format(springValue.value) : format(readTarget() || 0)
  )
  const start = () => {
    active.value = true
    setSpringTarget(readTarget() || 0)
  }
  return { display, start }
}

const todayKpi = createCountUp(() => stats.value.today)
const patientsKpi = createCountUp(() => stats.value.total_patients)
const monthKpi = createCountUp(
  () => stats.value.total_appointments_this_month || stats.value.total_appointments
)
const incomeKpi = createCountUp(
  () => stats.value.total_income,
  value => formatPENLabel(value)
)

let countUpsPlayed = false
const startKpiCountUps = () => {
  if (countUpsPlayed) return
  countUpsPlayed = true
  if (!countUpAllowed()) return
  todayKpi.start()
  patientsKpi.start()
  monthKpi.start()
  incomeKpi.start()
}

// Utility functions
const getGreeting = () => {
  const hour = new Date().getHours()
  if (hour < 12) return 'Buenos días'
  if (hour < 18) return 'Buenas tardes'
  return 'Buenas noches'
}

const firstName = computed(() => {
  const raw = user.value?.name || ''
  return raw.split(' ')[0] || 'equipo'
})

const getTodayDate = () => {
  return new Date().toLocaleDateString('es-ES', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

/**
 * PR4 correction round - short date for the Citas Hoy caption slot.
 * The full `martes, 11 de agosto de 2026` Spanish format overflows the
 * KPI card's caption slot at 5-up and `truncate` clips it mid-word.
 * The short form `11 de ago` (day + Spanish month abbreviation, same
 * tokens the chip's period_label uses) fits the slot on one line at
 * the audit-confirmed 1440x900 width.
 */
const getShortTodayDate = () => {
  const months = [
    'ene',
    'feb',
    'mar',
    'abr',
    'may',
    'jun',
    'jul',
    'ago',
    'sep',
    'oct',
    'nov',
    'dic'
  ]
  const now = new Date()
  const day = now.getDate()
  const month = months[now.getMonth()]
  return `${day} de ${month}`
}

const formatTime = dateTime => {
  if (!dateTime) return ''
  return new Date(dateTime).toLocaleTimeString('es-ES', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

/**
 * Patient display name for the today list. AppointmentResource emits
 * `patient.full_name`; older/raw payloads may only carry the split
 * first/last fields (or the legacy `name` accessor). Fall back in that
 * order so the row never loses the patient identity.
 */
const getPatientName = appointment => {
  const patient = appointment?.patient
  if (!patient) return 'Paciente'
  if (patient.full_name) return patient.full_name
  const composed = [patient.first_name, patient.last_name].filter(Boolean).join(' ').trim()
  return composed || patient.name || 'Paciente'
}

const getStatusText = status => {
  const texts = {
    scheduled: 'Programada',
    confirmed: 'Confirmada',
    in_consultation: 'En Consulta',
    completed: 'Completada',
    cancelled: 'Cancelada',
    no_show: 'No se presentó'
  }
  return texts[status] || status
}

const getStatusVariant = status => {
  const variants = {
    scheduled: 'secondary',
    confirmed: 'success',
    in_consultation: 'warning',
    completed: 'primary',
    cancelled: 'error',
    no_show: 'warning'
  }
  return variants[status] || 'secondary'
}

/**
 * T5 - local calendar-day key for the week preview. Uses the same local
 * timezone formatTime() renders in, so a late-evening appointment never
 * lands in the wrong day header.
 */
const upcomingDayKey = dateTime => {
  const date = new Date(dateTime)
  if (Number.isNaN(date.getTime())) return 'sin-fecha'
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(
    date.getDate()
  ).padStart(2, '0')}`
}

/** Short Spanish day header, e.g. "Lun 6". */
const upcomingDayLabel = dateTime => {
  const date = new Date(dateTime)
  if (Number.isNaN(date.getTime())) return ''
  const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']
  return `${dayNames[date.getDay()]} ${date.getDate()}`
}

/**
 * T5 - group the week preview by local day. The endpoint returns rows
 * ordered by scheduled_at, so insertion order is already chronological and
 * no client-side sort is applied.
 */
const upcomingGroups = computed(() => {
  const groups = []
  const byKey = new Map()
  for (const appointment of upcomingAppointments.value) {
    const key = upcomingDayKey(appointment?.scheduled_at)
    let group = byKey.get(key)
    if (!group) {
      group = {
        key,
        label: upcomingDayLabel(appointment?.scheduled_at),
        appointments: []
      }
      byKey.set(key, group)
      groups.push(group)
    }
    group.appointments.push(appointment)
  }
  return groups
})

// Navigation functions
const goToCalendar = () => {
  router.push('/calendar')
}

const goToPatients = () => {
  router.push('/patients')
}

const showNewAppointmentModal = ref(false)

const goToNewAppointment = () => {
  showNewAppointmentModal.value = true
}

const handleAppointmentCreated = async () => {
  // Slice 08 / FF-015: refresh data after the user creates an appointment
  // from anywhere (quick-action button or empty-state CTA). Single fetch
  // rather than a fan-out - the WebSocket path will catch subsequent edits.
  await loadDashboardData()
}

const goToProfessionals = () => {
  router.push('/professionals')
}

const goToCashRegister = () => {
  router.push('/cash-register')
}

// Cash status: render the Spanish label directly via a primitive that
// supports custom labels. Replaces a previous attempt that passed English
// keys ('open' / 'closed' / 'no_session') to UiStatusPill - that primitive
// only maps appointment / plan statuses and fell through to render the raw
// English key on the page. The state is now used purely as a data
// attribute (data-cash-pill-state) for testability; the user-visible
// label and aria-label are always Spanish. iOS filled pattern per
// Decision 7:
//   - open        → label "Abierta",     bg-systemGreen-100 text-systemGreen-600
//   - closed      → label "Cerrada",     bg-systemRed-100 text-systemRed-600
//   - no_session  → label "Sin sesión",  bg-systemGray-100 text-systemGray-600
const cashStatusPillState = computed(() => {
  if (isOpen.value) return 'open'
  if (hasActiveSession.value) return 'closed'
  return 'no_session'
})

const cashStatusLabel = computed(() => {
  if (isOpen.value) return 'Abierta'
  if (hasActiveSession.value) return 'Cerrada'
  return 'Sin sesión'
})

const cashStatusBadgeVariant = computed(() => {
  if (isOpen.value) return 'success'
  if (hasActiveSession.value) return 'error'
  return 'neutral'
})

const cashStatusBadgeClass = computed(() => {
  if (isOpen.value) return 'bg-systemGreen-100 text-systemGreen-600'
  if (hasActiveSession.value) return 'bg-systemRed-100 text-systemRed-600'
  return 'bg-systemGray-100 text-systemGray-600'
})

const cashStatusDotClass = computed(() => {
  if (isOpen.value) return 'bg-systemGreen-500'
  if (hasActiveSession.value) return 'bg-systemRed-500'
  return 'bg-systemGray-500'
})

const cashBalanceText = computed(() => {
  if (isOpen.value && realTimeTotals.value) {
    return `Saldo: ${formatPENLabel(realTimeTotals.value.currentBalance)}`
  }
  if (hasActiveSession.value) {
    return 'Sesión cerrada'
  }
  return 'No hay sesión activa'
})

const goToEnvironments = () => {
  router.push('/environments')
}

const goToBusinessIntelligence = () => {
  router.push('/business-intelligence')
}

// Data loading

/**
 * T3 - fetch the stats resource without throwing. A 401 is surfaced as
 * `unauthorized` so the caller keeps the /login redirect; any other failure
 * is surfaced as `ok: false` so the page can render its own error state
 * instead of swallowing the failure and painting zeros as real data.
 */
const fetchStats = async () => {
  try {
    const response = await get('/api/dashboard/stats')
    return { ok: true, unauthorized: false, data: response?.data || {} }
  } catch (error) {
    return { ok: false, unauthorized: error?.status === 401, data: null }
  }
}

/**
 * T3 - fetch today's agenda without throwing. 401/404 keep degrading to an
 * empty agenda (T1 tolerance contract); any other failure surfaces
 * `ok: false` so the agenda section can offer its own retry while the rest
 * of the page stays usable.
 */
const fetchTodayAppointments = async () => {
  try {
    const response = await get('/api/dashboard/appointments-today')
    return { ok: true, data: Array.isArray(response?.data) ? response.data : [] }
  } catch (error) {
    if (error?.status === 401 || error?.status === 404) {
      return { ok: true, data: [] }
    }
    return { ok: false, data: [] }
  }
}

/**
 * T5 - fetch the upcoming-week preview without throwing. A 401 surfaces
 * `unauthorized` so the caller keeps the /login redirect; any other
 * failure surfaces `ok: false` so the strip renders its own inline error
 * instead of the page swallowing the failure.
 */
const fetchUpcomingAppointments = async () => {
  try {
    const response = await get('/api/dashboard/upcoming')
    return {
      ok: true,
      unauthorized: false,
      data: Array.isArray(response?.data) ? response.data : []
    }
  } catch (error) {
    return { ok: false, unauthorized: error?.status === 401, data: [] }
  }
}

/** Map the backend stats payload into the frontend shape. */
const applyStats = backendStats => {
  stats.value = {
    today: backendStats.appointments_today || 0,
    appointments_today: backendStats.appointments_today || 0,
    completed_today: backendStats.completed_today || 0,
    pending_confirmation: backendStats.pending_confirmation || 0,
    this_week: backendStats.this_week || 0,
    total_patients: backendStats.total_patients || 0,
    total_appointments: backendStats.total_appointments || 0,
    total_appointments_this_month:
      backendStats.total_appointments_this_month || backendStats.total_appointments || 0,
    total_professionals: backendStats.total_professionals || 0,
    total_appointment_types: backendStats.total_appointment_types || 0,
    total_dental_chairs: backendStats.total_dental_chairs || 0,
    total_income: backendStats.total_income || 0,
    cash_session: backendStats.cash_session || null,
    // PR3 / PR4 - additive comparisons block. Three keys carry
    // data; the omitted keys (total_professionals, total_income,
    // cash_session) keep their `null` default so the chip slots
    // reserve their footprint but render no chip.
    comparisons: backendStats.comparisons || {
      appointments_today: null,
      total_patients: null,
      total_appointments_this_month: null
    }
  }
}

const loadDashboardData = async () => {
  if (!isAuthenticated.value) {
    router.push('/login')
    return
  }

  // T3 - only the first load owns the skeleton. Later loads (manual refresh,
  // WebSocket bursts, post-create reload) keep the current content mounted
  // and surface the lightweight in-flight indicator instead.
  if (hasLoaded.value) {
    refreshing.value = true
  } else {
    loading.value = true
  }

  try {
    const [statsResult, appointmentsResult, upcomingResult] = await Promise.all([
      fetchStats(),
      fetchTodayAppointments(),
      fetchUpcomingAppointments()
    ])

    if (statsResult.unauthorized) {
      router.push('/login')
    } else if (statsResult.ok) {
      applyStats(statsResult.data)
      statsError.value = false
      startKpiCountUps()
    } else {
      statsError.value = true
    }

    if (appointmentsResult.ok) {
      todayAppointments.value = appointmentsResult.data
      todayError.value = false
    } else {
      todayError.value = true
    }

    // T5 - the week preview is tolerant: its failure shows the section's
    // inline error and never blocks stats, agenda, or the KPI grid. A 401
    // keeps the page's /login redirect behavior.
    if (upcomingResult.unauthorized) {
      router.push('/login')
    } else if (upcomingResult.ok) {
      upcomingAppointments.value = upcomingResult.data
      upcomingError.value = false
    } else {
      upcomingError.value = true
    }

    // Load cash session if not already loaded
    if (!currentSession.value && hasActiveSession.value === false) {
      await loadCurrentSession()
    }
  } finally {
    loading.value = false
    refreshing.value = false
    hasLoaded.value = true
  }

  // T4 - the section reveals play only on the first successful content
  // render. A failed load keeps the entrance armed for the retry.
  if (!entrancePlayed && !statsError.value) {
    await nextTick()
    playEntrance()
  }
}

/**
 * T3 - agenda-only retry. Re-fetches just the today resource so a failing
 * agenda never forces a full page reload.
 */
const retryTodayAppointments = async () => {
  const result = await fetchTodayAppointments()
  if (result.ok) {
    todayAppointments.value = result.data
    todayError.value = false
  } else {
    todayError.value = true
  }
}

/**
 * T5 - week-preview-only retry. Re-fetches just the upcoming resource so a
 * failing strip never forces a full page reload. 401 keeps the standard
 * /login redirect.
 */
const retryUpcomingAppointments = async () => {
  const result = await fetchUpcomingAppointments()
  if (result.unauthorized) {
    router.push('/login')
    return
  }
  if (result.ok) {
    upcomingAppointments.value = result.data
    upcomingError.value = false
  } else {
    upcomingError.value = true
  }
}

// WebSocket subscriptions
let dashboardChannel = null
let appointmentsChannel = null
let cashRegisterChannel = null

// Slice 08 / FF-015: the legacy version had 14 WS listeners that all
// called loadDashboardData() directly. A burst (e.g. 5 events within 50 ms
// after a payment + a patient update) hit the API 5 times. Coalesce them
// into a single trailing-edge debounced fetch. This 300ms debounce is
// load-bearing - do not change the timing without updating the
// apply-progress evidence.
let dashboardDebounceTimer = null
const debouncedLoadDashboardData = () => {
  if (dashboardDebounceTimer !== null) {
    clearTimeout(dashboardDebounceTimer)
  }
  dashboardDebounceTimer = setTimeout(async () => {
    dashboardDebounceTimer = null
    await loadDashboardData()
  }, 300)
}

onUnmounted(() => {
  // Clear any pending debounced fetch on unmount so we don't fire
  // against a torn-down component.
  if (dashboardDebounceTimer !== null) {
    clearTimeout(dashboardDebounceTimer)
    dashboardDebounceTimer = null
  }
})

// Lifecycle
onMounted(async () => {
  // Verificar si se debe abrir el modal de nueva cita (desde redirección)
  if (route.query.openAppointmentModal === 'true') {
    showNewAppointmentModal.value = true
    router.replace({ query: {} })
  }

  // Cargar sesión de caja primero
  await loadCurrentSession()

  // Luego cargar datos del dashboard
  await loadDashboardData()

  // Suscribirse a canales WebSocket (Reverb is often not running locally;
  // connection errors here are expected and harmless - error handling is
  // inside useEcho).
  try {
    dashboardChannel = channel('dashboard-updates')
    if (dashboardChannel) {
      dashboardChannel
        .listen('.dashboard.stats-updated', () => debouncedLoadDashboardData())
        .listen('.patient.created', () => debouncedLoadDashboardData())
        .listen('.patient.updated', () => debouncedLoadDashboardData())
        .listen('.patient.deleted', () => debouncedLoadDashboardData())
        .listen('.appointment.created', () => debouncedLoadDashboardData())
        .listen('.appointment.updated', () => debouncedLoadDashboardData())
        .listen('.appointment.deleted', () => debouncedLoadDashboardData())
        .listen('.user.created', () => debouncedLoadDashboardData())
        .listen('.user.updated', () => debouncedLoadDashboardData())
    }

    appointmentsChannel = channel('appointments')
    if (appointmentsChannel) {
      appointmentsChannel
        .listen('.appointment.created', e => {
          if (e.appointment?.scheduled_at) {
            const appointmentDate = new Date(e.appointment.scheduled_at)
            const today = new Date()
            if (appointmentDate.toDateString() === today.toDateString()) {
              debouncedLoadDashboardData()
            }
          }
        })
        .listen('.appointment.updated', async e => {
          const index = todayAppointments.value.findIndex(apt => apt.id === e.appointment.id)
          if (index !== -1) {
            todayAppointments.value[index] = e.appointment
          } else {
            debouncedLoadDashboardData()
          }
        })
        .listen('.appointment.deleted', async e => {
          todayAppointments.value = todayAppointments.value.filter(
            apt => apt.id !== e.appointment_id
          )
          debouncedLoadDashboardData()
        })
    }

    cashRegisterChannel = channel('cash-register')
    if (cashRegisterChannel) {
      cashRegisterChannel
        .listen('.cash-session.opened', async () => {
          await loadCurrentSession()
          debouncedLoadDashboardData()
        })
        .listen('.cash-session.closed', async () => {
          await loadCurrentSession()
          debouncedLoadDashboardData()
        })
        .listen('.payment.registered', async () => {
          await loadCurrentSession()
          debouncedLoadDashboardData()
        })
        .listen('.cash-movement.created', async () => {
          await loadCurrentSession()
          debouncedLoadDashboardData()
        })
    }
  } catch (error) {
    // Reverb unreacheable in dev is expected.
  }
})

onUnmounted(() => {
  if (echo) {
    try {
      echo.leave('dashboard-updates')
      echo.leave('appointments')
      echo.leave('cash-register')
    } catch (e) {
      // teardown is best-effort
    }
  }
})
</script>
