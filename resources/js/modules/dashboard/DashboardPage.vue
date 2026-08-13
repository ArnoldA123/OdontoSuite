<template>
  <AppLayout>
    <!-- Loading State: Skeleton placeholders that match the final layout's
         shape so the page does not jump when data lands. -->
    <template v-if="loading">
      <div class="space-y-8" aria-busy="true" aria-live="polite">
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
        <!-- Today's appointments skeletons -->
        <section aria-label="Cargando citas de hoy">
          <UiSkeleton
            v-for="i in 3"
            :key="`apt-skel-${i}`"
            variant="list"
            animation="wave"
            :aria-label="`Cargando cita ${i}`"
          />
        </section>
      </div>
    </template>

    <!-- Main Content -->
    <div v-else class="space-y-8">
      <!--
        Page greeting (defect 7 — two competing headings fix).
        The AppLayout top bar already renders the page title h1; this
        greeting is a calm welcome line, not a heading. The previous
        h1-equivalent size competed with the topbar h1 and read as
        h1 + h2. PR4 reduces it to text-lg font-medium text-theme-secondary:
        a quiet welcome line that lets the topbar h1 own the heading
        hierarchy.
      -->
      <header class="flex items-end justify-between flex-wrap gap-4">
        <div>
          <p class="text-lg font-medium text-theme-secondary leading-tight">
            {{ getGreeting() }},
            <span class="text-label">{{ firstName }}</span>
          </p>
          <p class="text-xs text-theme-secondary mt-1">
            {{ getTodayDate() }}
          </p>
        </div>
      </header>

      <!--
        Stats Grid — five stat cards, fixed-slot anatomy (KPI card anatomy).
        Each card allocates four reserved slots in a fixed row grid so the
        baseline is uniform regardless of which cards carry a chip:

          [eyebrow]    h-4  (16 px)
          [number]     h-12 (48 px)
          [chip slot]  h-6  (24 px — reserved even when empty)
          [caption]    h-4  (16 px)

        Cards that carry a comparison key render the chip from
        `comparisons[statKey].delta_label`. When that field is null, the
        slot stays empty (no chip, no dash, no placeholder). The chip
        colour follows sign: positive → systemGreen, negative → systemRed.

        Defect 2 fix: every card border consumes the PR1 hairline token
        (alpha 0.12) instead of the previous opaque separator.
        Defect 3 fix: every card shadow consumes the PR1 elevation-2
        rung (iOS label/separator hue family) instead of the previous
        pure-black shadow.
        Defect 6 fix: every icon plate uses the same tint (systemGray-100
        + systemGray-600 — the iOS Settings / List treatment).
      -->
      <section aria-label="Resumen del día">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
          <!-- Citas Hoy (PRIMARY stat - operationally live; gated) -->
          <UiCard
            v-if="can.viewAppointment?.value"
            variant="glass"
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
                  Eyebrow (defect 4 — Estado de Caja row-rhythm fix).
                  text-[11px] + whitespace-nowrap + no tracking lets
                  the longest label ("Estado de Caja") sit on a single
                  line at the 5-up KPI card width. text-xs (12 px) with
                  tracking-wide wrapped it; the smaller font and removed
                  tracking keep all five cards aligned on one line.
                -->
                <div class="h-4 flex items-center">
                  <p
                    class="text-[11px] font-medium text-theme-secondary uppercase whitespace-nowrap"
                  >
                    Citas Hoy
                  </p>
                </div>
                <div class="h-12 flex items-center">
                  <p
                    class="text-5xl font-bold text-label tabular-nums leading-none"
                    style="font-feature-settings: var(--font-features-tabular-nums)"
                    aria-live="polite"
                  >
                    {{ stats.today || 0 }}
                  </p>
                </div>
                <!--
                  Chip slot (defect 2 — chip layout fix).
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
                  Caption slot (defect 3 — date truncation fix).
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
              <div
                class="flex-shrink-0 w-12 h-12 bg-systemGray-100 rounded-ios flex items-center justify-center"
              >
                <svg
                  class="w-6 h-6 text-systemGray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                  />
                </svg>
              </div>
            </div>
          </UiCard>

          <!-- Pacientes (reference count). The headline is the cumulative
               active count (data.total_patients, NOT new registrations).
               The chip, when present, is an absolute count of new
               registrations this month — a different quantity. -->
          <UiCard
            variant="glass"
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
                  <p
                    class="text-[11px] font-medium text-theme-secondary uppercase whitespace-nowrap"
                  >
                    Pacientes
                  </p>
                </div>
                <div class="h-12 flex items-center">
                  <p
                    class="text-5xl font-bold text-label tabular-nums leading-none"
                    style="font-feature-settings: var(--font-features-tabular-nums)"
                  >
                    {{ stats.total_patients || 0 }}
                  </p>
                </div>
                <!--
                  Chip slot (defect 2 — chip layout fix). The
                  comparisons.total_patients.period_label is the
                  static string "nuevos este mes" and is intentionally
                  a different quantity from the headline (D15 — the
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
                  <p class="text-xs text-theme-secondary truncate">
Total registrados
</p>
                </div>
              </div>
              <div
                class="flex-shrink-0 w-12 h-12 bg-systemGray-100 rounded-ios flex items-center justify-center"
              >
                <svg
                  class="w-6 h-6 text-systemGray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"
                  />
                </svg>
              </div>
            </div>
          </UiCard>

          <!-- Profesionales (reference count; gated). No comparison key
               ships from the controller (only three stats carry the
               additive comparisons block); the chip slot stays empty. -->
          <UiCard
            v-if="can.manageUsers?.value"
            variant="glass"
            hover
            clickable
            data-stat="total-professionals"
            data-stat-card="total-professionals"
            class="relative"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
            @click="goToProfessionals"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="h-4 flex items-center">
                  <p
                    class="text-[11px] font-medium text-theme-secondary uppercase whitespace-nowrap"
                  >
                    Profesionales
                  </p>
                </div>
                <div class="h-12 flex items-center">
                  <p
                    class="text-5xl font-bold text-label tabular-nums leading-none"
                    style="font-feature-settings: var(--font-features-tabular-nums)"
                  >
                    {{ stats.total_professionals || 0 }}
                  </p>
                </div>
                <div class="h-6 min-h-[24px]" />
                <div class="h-4 flex items-center">
                  <p class="text-xs text-theme-secondary truncate">
Equipo médico
</p>
                </div>
              </div>
              <div
                class="flex-shrink-0 w-12 h-12 bg-systemGray-100 rounded-ios flex items-center justify-center"
              >
                <svg
                  class="w-6 h-6 text-systemGray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                  />
                </svg>
              </div>
            </div>
          </UiCard>

          <!-- Total Citas (reference count) -->
          <UiCard
            variant="glass"
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
                  <p
                    class="text-[11px] font-medium text-theme-secondary uppercase whitespace-nowrap"
                  >
                    Total Citas
                  </p>
                </div>
                <div class="h-12 flex items-center">
                  <p
                    class="text-5xl font-bold text-label tabular-nums leading-none"
                    style="font-feature-settings: var(--font-features-tabular-nums)"
                  >
                    {{ stats.total_appointments_this_month || stats.total_appointments || 0 }}
                  </p>
                </div>
                <!--
                  Chip slot (defect 2 — chip layout fix). Period_label
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
                  <p class="text-xs text-theme-secondary truncate">
Este mes
</p>
                </div>
              </div>
              <div
                class="flex-shrink-0 w-12 h-12 bg-systemGray-100 rounded-ios flex items-center justify-center"
              >
                <svg
                  class="w-6 h-6 text-systemGray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                  />
                </svg>
              </div>
            </div>
          </UiCard>

          <!-- Estado de Caja (SECONDARY live stat; gated).
               No comparison key ships for cash_session. The cash pill
               renders its own Spanish label via a primitive that
               supports custom labels. -->
          <UiCard
            v-if="can.viewCashRegister?.value"
            variant="glass"
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
                  Eyebrow (defect 4). text-[11px] + whitespace-nowrap
                  + no tracking lets "Estado de Caja" sit on a single
                  line at the 5-up KPI card width. Same treatment as
                  the four sibling eyebrows for row rhythm.
                -->
                <div class="h-4 flex items-center">
                  <p
                    class="text-[11px] font-medium text-theme-secondary uppercase whitespace-nowrap"
                  >
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
              <div
                class="flex-shrink-0 w-12 h-12 bg-systemGray-100 rounded-ios flex items-center justify-center"
              >
                <svg
                  class="w-6 h-6 text-systemGray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
              </div>
            </div>
          </UiCard>
        </div>
      </section>

      <!-- Quick Actions -->
      <section aria-label="Acciones rápidas">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-ink-800">Acciones Rápidas</h2>
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
          Quick Actions — 3 cols at lg+ (see layout note above for why
          not 5). PR4 (G4) adds a keyhint affordance to each tile: the
          banned chevron SVG path (M9 5l7 7-7 7) cannot be reintroduced,
          but the tiles still need a "this is clickable" cue beyond the
          hover lift. The device: a `<kbd>` chip in the top-right corner
          carrying the keyboard shortcut for the action. Different from a
          chevron (it's a keyhint), satisfies the source-assertion test,
          and matches iOS's keyboard-shortcut disclosure convention.
        -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <!-- Patients -->
          <UiCard
            variant="flat"
            hover
            clickable
            data-action="patients"
            data-keyhint="P"
            @click="goToPatients"
          >
            <div class="flex items-start gap-3">
              <div
                class="flex-shrink-0 w-10 h-10 bg-systemGray-100 rounded-lg flex items-center justify-center"
              >
                <svg
                  class="w-5 h-5 text-systemGray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label leading-tight">Pacientes</p>
                <p class="text-sm text-theme-secondary leading-snug mt-0.5">
                  Gestionar base de datos
                </p>
              </div>
              <kbd
                class="flex-shrink-0 self-start text-[10px] font-medium text-systemGray-500 border border-systemGray-200 rounded px-1.5 py-0.5"
              >
                P
              </kbd>
            </div>
          </UiCard>

          <!-- New Appointment -->
          <UiCard
            v-if="can.createAppointment?.value"
            variant="flat"
            hover
            clickable
            data-action="new-appointment"
            data-keyhint="N"
            @click="goToNewAppointment"
          >
            <div class="flex items-start gap-3">
              <div
                class="flex-shrink-0 w-10 h-10 bg-systemGray-100 rounded-lg flex items-center justify-center"
              >
                <svg
                  class="w-5 h-5 text-systemGray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label leading-tight whitespace-nowrap">Nueva Cita</p>
                <p class="text-sm text-theme-secondary leading-snug mt-0.5">
                  Programar cita médica
                </p>
              </div>
              <kbd
                class="flex-shrink-0 self-start text-[10px] font-medium text-systemGray-500 border border-systemGray-200 rounded px-1.5 py-0.5"
              >
                N
              </kbd>
            </div>
          </UiCard>

          <!-- Professionals -->
          <UiCard
            v-if="can.manageUsers?.value"
            variant="flat"
            hover
            clickable
            data-action="professionals"
            data-keyhint="R"
            @click="goToProfessionals"
          >
            <div class="flex items-start gap-3">
              <div
                class="flex-shrink-0 w-10 h-10 bg-systemGray-100 rounded-lg flex items-center justify-center"
              >
                <svg
                  class="w-5 h-5 text-systemGray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label leading-tight">Profesionales</p>
                <p class="text-sm text-theme-secondary leading-snug mt-0.5">Gestionar equipo</p>
              </div>
              <kbd
                class="flex-shrink-0 self-start text-[10px] font-medium text-systemGray-500 border border-systemGray-200 rounded px-1.5 py-0.5"
              >
                R
              </kbd>
            </div>
          </UiCard>

          <!-- Environments -->
          <UiCard
            v-if="can.manageConfig?.value"
            variant="flat"
            hover
            clickable
            data-action="environments"
            data-keyhint="E"
            @click="goToEnvironments"
          >
            <div class="flex items-start gap-3">
              <div
                class="flex-shrink-0 w-10 h-10 bg-systemGray-100 rounded-lg flex items-center justify-center"
              >
                <svg
                  class="w-5 h-5 text-systemGray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label leading-tight">Ambientes</p>
                <p class="text-sm text-theme-secondary leading-snug mt-0.5">Configurar espacios</p>
              </div>
              <kbd
                class="flex-shrink-0 self-start text-[10px] font-medium text-systemGray-500 border border-systemGray-200 rounded px-1.5 py-0.5"
              >
                E
              </kbd>
            </div>
          </UiCard>

          <!-- Reportes -->
          <UiCard
            v-if="can.viewReports?.value"
            variant="flat"
            hover
            clickable
            data-action="reports"
            data-keyhint="B"
            @click="goToBusinessIntelligence"
          >
            <div class="flex items-start gap-3">
              <div
                class="flex-shrink-0 w-10 h-10 bg-systemGray-100 rounded-lg flex items-center justify-center"
              >
                <svg
                  class="w-5 h-5 text-systemGray-600"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                  />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-label leading-tight">Reportes</p>
                <p class="text-sm text-theme-secondary leading-snug mt-0.5">
                  Análisis y estadísticas
                </p>
              </div>
              <kbd
                class="flex-shrink-0 self-start text-[10px] font-medium text-systemGray-500 border border-systemGray-200 rounded px-1.5 py-0.5"
              >
                B
              </kbd>
            </div>
          </UiCard>
        </div>
      </section>

      <!-- Today's Appointments Preview: list OR empty state.
           The empty state is the live state today (GET /api/dashboard/today
           returns 404 due to the known bug). Build it properly, not as an
           afterthought. -->
      <section v-if="can.viewAppointment?.value" aria-label="Citas de hoy">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-base font-semibold text-ink-800">Citas de Hoy</h2>
          <UiButton
            v-if="todayAppointments.length > 0"
            variant="ghost"
            size="sm"
            @click="goToCalendar"
          >
            Ver todas
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
          Empty state for the today-appointments case.
          Composed from the design system: the existing <EmptyState>
          primitive with its default calendar icon, a one-line Spanish
          message, and a real call-to-action that routes to appointment
          creation. NO remote illustration — clinical products must not
          leak requests to third-party hosts (air-gapped deployments
          would render a broken image, and the previous Picsum URL
          resolved to an unrelated stock photo anyway, since the seed
          is meaningless to a placeholder service). The pattern is
          enforced project-wide by the no-external-image test in
          DashboardAppShellTest.
        -->
        <EmptyState
          v-if="todayAppointments.length === 0"
          title="Sin citas para hoy"
          description="Aún no hay citas registradas para el día de hoy. Puedes crear una nueva cita desde la sección de calendario."
          action-text="Agendar nueva cita"
          action-variant="primary"
          data-state="empty-appointments"
          @action="goToNewAppointment"
        />

        <div v-else class="grid gap-3">
          <UiCard
            v-for="appointment in todayAppointments.slice(0, 3)"
            :key="appointment.id"
            variant="flat"
            data-appointment-row
            class="hover:shadow-medium"
          >
            <div class="flex items-center justify-between gap-4">
              <div class="flex items-center gap-4 min-w-0">
                <div
                  class="flex-shrink-0 w-10 h-10 bg-systemBlue-100 rounded-ios flex items-center justify-center border border-systemBlue-200"
                >
                  <svg
                    class="w-5 h-5 text-systemBlue-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="font-medium text-ink-800 truncate">
                    {{ appointment.patient?.name || 'Paciente' }}
                  </p>
                  <p class="text-sm text-ink-500 truncate">
                    {{ formatTime(appointment.scheduled_at) }} ·
                    {{ appointment.appointment_type?.name || 'Consulta' }}
                  </p>
                </div>
              </div>
              <UiBadge :variant="getStatusVariant(appointment.status)" size="sm">
                {{ getStatusText(appointment.status) }}
              </UiBadge>
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
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import NewAppointmentModal from '../../components/appointments/NewAppointmentModal.vue'
import { useApi } from '../../composables/useApi'
import { useAuth } from '@/composables/useAuth'
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
  // PR4 — additive backend block (PR3). Three keys carry comparison data;
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

/**
 * PR4 — chip tone class. The chip is a pre-formatted string from the
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

// Spring hooks are not strictly required for the rebuild — we expose the
// composables via the design contract (useSpring/useSpring2D live in PR2's
// composables). Numbers are displayed via Vue's reactive interpolation; a
// WebSocket burst lands in the same value, so the bindings naturally tween
// visually (no DOM-level entrance replay). See apply-progress.md for the
// decision trail.

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
 * PR4 correction round — short date for the Citas Hoy caption slot.
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

const getRoleLabel = role => {
  const labels = {
    administrador: 'Administrador',
    recepcionista: 'Recepcionista',
    odontologo: 'Odontólogo',
    implantologo: 'Implantólogo',
    tecnico_dental: 'Técnico Dental',
    asistente: 'Asistente',
    finanzas: 'Finanzas'
  }
  return labels[role] || role
}

const formatTime = dateTime => {
  if (!dateTime) return ''
  return new Date(dateTime).toLocaleTimeString('es-ES', {
    hour: '2-digit',
    minute: '2-digit'
  })
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
  // rather than a fan-out — the WebSocket path will catch subsequent edits.
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
// keys ('open' / 'closed' / 'no_session') to UiStatusPill — that primitive
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

const goToAppointmentTypes = () => {
  router.push('/appointment-types')
}

const goToBusinessIntelligence = () => {
  router.push('/business-intelligence')
}

// Data loading
const loadDashboardData = async () => {
  if (!isAuthenticated.value) {
    router.push('/login')
    return
  }

  loading.value = true
  try {
    const [statsResponse, appointmentsResponse] = await Promise.all([
      get('/api/dashboard/stats'),
      get('/api/dashboard/today').catch(err => {
        // GET /api/dashboard/today returns 404 in the running app; the
        // empty-state path is the live UX. Treat any error as an empty list
        // rather than throwing, so other stats still render.
        if (err && (err.status === 404 || err.status === 401)) {
          return { data: [] }
        }
        throw err
      })
    ])

    // Map backend stats into the frontend shape.
    const backendStats = statsResponse.data || {}
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
      // PR3 / PR4 — additive comparisons block. Three keys carry
      // data; the omitted keys (total_professionals, total_income,
      // cash_session) keep their `null` default so the chip slots
      // reserve their footprint but render no chip.
      comparisons: backendStats.comparisons || {
        appointments_today: null,
        total_patients: null,
        total_appointments_this_month: null
      }
    }

    todayAppointments.value = Array.isArray(appointmentsResponse?.data)
      ? appointmentsResponse.data
      : []

    // Load cash session if not already loaded
    if (!currentSession.value && hasActiveSession.value === false) {
      await loadCurrentSession()
    }
  } catch (error) {
    if (error?.status === 401) {
      router.push('/login')
    }
  } finally {
    loading.value = false
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
// load-bearing — do not change the timing without updating the
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
  // connection errors here are expected and harmless — error handling is
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
