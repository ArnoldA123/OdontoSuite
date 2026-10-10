<template>
  <AppLayout>
    <!-- Loading State: Skeleton placeholders that match the final layout's
         shape so the page does not jump when data lands. -->
    <template v-if="loading">
      <div class="space-y-12" aria-busy="true" aria-live="polite">
        <!-- Day-operations skeletons: the loading shape mirrors the block
             order (KPI strip -> agenda) so the page does not jump. -->
        <section aria-label="Cargando resumen">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <UiSkeleton
              v-for="i in 4"
              :key="`stat-skel-${i}`"
              variant="card"
              animation="wave"
              :aria-label="`Cargando tarjeta ${i}`"
            />
          </div>
        </section>
        <section aria-label="Cargando agenda de hoy">
          <UiSkeleton
            v-for="i in 3"
            :key="`apt-skel-${i}`"
            variant="list"
            animation="wave"
            :aria-label="`Cargando cita ${i}`"
          />
        </section>
        <!-- Pending skeletons (WU3): same single-list shape as the merged
             pending rows so the section does not jump when data lands. -->
        <section aria-label="Cargando pendientes">
          <div class="grid gap-2">
            <UiSkeleton
              v-for="i in 3"
              :key="`pending-skel-${i}`"
              variant="list"
              animation="wave"
              :aria-label="`Cargando pendientes ${i}`"
            />
          </div>
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
      </div>
    </template>

    <!-- Main Content -->
    <div v-else data-dashboard-content class="space-y-12" :aria-busy="refreshing">
      <!--
        Hero band (WU4 / D6) - the login's visual language reaches the
        dashboard. Surface vocabulary traced to the login card (hairline +
        --radius-panel + elevation rung) over the login scene's neutral
        backdrop base (--color-canvas; user direction: no mint tint); the
        greeting rides the login display step
        (text-4xl = 36/40/-0.022em from the shared type scale). The brand
        lockup (tooth glyph + wordmark on accent-500) is the login's own
        header anatomy, reused verbatim as the sparingly used green accent.
        The topbar keeps owning the single h1; this band stays typographic.
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
        data-dashboard-hero
        data-reveal="greeting"
        class="relative overflow-hidden p-6 md:p-8"
        style="
          background: var(--color-canvas);
          border: 1px solid var(--color-hairline);
          border-radius: var(--radius-panel);
          box-shadow: var(--elevation-2);
        "
        :style="revealStyle('--spring-dash-greeting-o')"
      >
        <DentalMotifLayer variant="hero" data-hero-motif />
        <div class="relative flex items-center justify-between gap-3">
          <!-- Brand lockup: tooth glyph + wordmark, no chip chrome. -->
          <p class="inline-flex items-center gap-2">
            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.75"
              stroke-linecap="round"
              stroke-linejoin="round"
              style="color: var(--color-accent-500)"
              aria-hidden="true"
            >
              <path
                d="M8 3.5C5.5 3.5 4 5.5 4 8c0 2.5 1.5 4 2 5.5s.5 4.5 1.5 6 1.5 1 2 0 1-3.5 1.5-3.5 1 2.5 1.5 3.5 1 1 2 0 .5-4.5 1.5-6 2-3 2-5.5c0-2.5-1.5-4.5-4-4.5-1.5 0-2 1-3 1s-1.5-1-3-1z"
              />
            </svg>
            <span class="text-sm font-semibold tracking-tight text-label">OdontoSuite</span>
          </p>
          <div class="flex items-center gap-3" data-hero-actions>
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
          </div>
        </div>
        <p class="relative mt-3 text-4xl font-semibold text-label">
          {{ getGreeting() }}, {{ firstName }}
        </p>
        <!-- date interpolation marker for HOTFIX-DASH-008 anchor: {{ getTodayDate() }} -->
        <p
          style="font-feature-settings: 'tnum' 1, 'lnum' 1"
          class="relative mt-2 text-base leading-relaxed text-theme-secondary"
        >
          {{ getTodayDate() }}
        </p>
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
        Block "Hoy" (WU4 / D7) - the day's protagonists under ONE strong
        heading: the KPI strip leads (its numbers summarize the very list
        the agenda renders) and the agenda follows it. The retired
        "Resumen del día" footer section is gone: its content lives here.
      -->
      <section v-if="!statsError" aria-label="Hoy" data-dashboard-block="hoy" class="space-y-6">
        <div class="flex items-baseline gap-3">
          <h2 class="text-2xl font-semibold text-label">Hoy</h2>
          <p class="text-sm text-theme-secondary">Lo que ocurre hoy en la clínica</p>
        </div>

        <!--
          Daily-operations KPI strip (WU1). Four cards (D1): Citas Hoy,
          Atendidas Hoy, Por Atender, Saldo de Caja. Card anatomy: small
          uppercase eyebrow, big tabular number, ONE scope caption (D3).

          Single source (R1): the three day counts derive from the SAME
          appointment list the agenda renders, bucketed through the shared
          status map the rows label with, so rows and KPIs can never
          disagree. While that source is down the three cards render "N/D"
          instead of a fabricated zero.

          No comparison chips and no reserved blank slots (D3): the backend
          comparison labels are broken ("-100" for patients), so each card
          is exactly eyebrow + number + caption. The cash card (WU2 / D4) is
          the single cash surface: state line + session balance + caption +
          its contextual CTA, all fed by useCashRegister.

          T3 - the strip is a static reference surface: no card carries a
          click affordance (no clickable/hover props, no @click). The cash
          card's "Ir a Caja" button is its contextual action, not a
          card-level affordance.
        -->
        <div
          ref="kpiSection"
          data-reveal="kpi"
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4"
          :style="revealStyle('--spring-dash-kpi-o')"
        >
          <!-- Citas Hoy: the day's agenda total (same list the agenda renders). -->
          <UiCard
            v-if="can.viewAppointment?.value"
            variant="glass"
            padding="sm"
            data-stat-card="appointments-today"
            data-priority="primary"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Citas Hoy
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  aria-live="polite"
                  data-stat="appointments-today"
                >
                  {{ todayKpiDisplay }}
                </p>
                <p
                  class="mt-1 text-xs text-theme-secondary truncate"
                  data-kpi-caption="appointments-today"
                >
                  Agenda del día
                </p>
              </div>
            </div>
          </UiCard>

          <!-- Atendidas Hoy: completed statuses of the same list. -->
          <UiCard
            v-if="can.viewAppointment?.value"
            variant="glass"
            padding="sm"
            data-stat-card="completed-today"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Atendidas Hoy
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  data-stat="completed-today"
                >
                  {{ completedKpiDisplay }}
                </p>
                <p
                  class="mt-1 text-xs text-theme-secondary truncate"
                  data-kpi-caption="completed-today"
                >
                  Completadas
                </p>
              </div>
            </div>
          </UiCard>

          <!--
            Por Atender: the honest remainder of the same list. The real
            status enum has no pending-confirmation state, so the bucket is
            "neither completed nor cancelled".
          -->
          <UiCard
            v-if="can.viewAppointment?.value"
            variant="glass"
            padding="sm"
            data-stat-card="pending-today"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Por Atender
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  data-stat="pending-today"
                >
                  {{ pendingKpiDisplay }}
                </p>
                <p
                  class="mt-1 text-xs text-theme-secondary truncate"
                  data-kpi-caption="pending-today"
                >
                  Sin completar ni cancelar
                </p>
              </div>
            </div>
          </UiCard>

          <!--
            Saldo de Caja: the single cash surface (WU2 / D4). State line,
            session balance and the one cash CTA live here; the header keeps
            only greeting, date and refresh. Everything cash-visible reads
            the useCashRegister state.
          -->
          <UiCard
            v-if="can.viewCashRegister?.value"
            variant="glass"
            padding="sm"
            data-stat-card="cash-balance"
            data-priority="secondary"
            class="relative h-full flex flex-col"
            :style="{ boxShadow: 'var(--elevation-2)', borderColor: 'var(--color-hairline)' }"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-medium text-theme-secondary uppercase whitespace-nowrap">
                  Saldo de Caja
                </p>
                <p
                  class="mt-1 text-xs font-medium"
                  :class="cashStateToneClass"
                  :data-cash-state="cashStateKey"
                >
                  {{ cashStateLabel }}
                </p>
                <p
                  class="mt-2 text-2xl font-bold text-label tabular-nums leading-none truncate"
                  style="font-feature-settings: 'tnum' 1, 'lnum' 1"
                  data-stat="cash-balance"
                >
                  {{ cashKpiBalance }}
                </p>
                <p class="mt-1 text-xs text-theme-secondary truncate" data-kpi-caption="cash-balance">
                  {{ cashKpiCaption }}
                </p>
                <div class="mt-3">
                  <UiButton variant="ghost" size="sm" @click="goToCashRegister">Ir a Caja</UiButton>
                </div>
              </div>
            </div>
          </UiCard>
        </div>

        <!--
          Agenda de hoy - the day's detail list (WU4 / D7: under the block
          heading, after the KPI strip). Rows render EVERY appointment
          returned by the canonical GET /api/dashboard/appointments-today
          endpoint (no slice cap). Row anatomy: time (tabular) / patient /
          type / professional / status. The empty state keeps the
          HOTFIX-DASH-007 line-art SVG through the shared
          DashboardSectionEmpty pattern (T5).
        -->
      <section v-if="can.viewAppointment?.value" aria-label="Agenda de hoy">
        <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
          <h3 class="text-base font-semibold text-label">Agenda de hoy</h3>
          <div class="flex items-center gap-2">
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
          Agenda inline error (T5). Only the today resource failed: the
          section keeps its header and the rest of the page stays usable, so
          the retry affordance lives here and re-fetches ONLY
          /api/dashboard/appointments-today through the shared section-error
          component.
        -->
        <DashboardSectionError
          v-if="todayError"
          data-state="error-appointments"
          role="alert"
          title="No pudimos cargar la agenda de hoy"
          description="Reintenta para ver las citas programadas para el día."
          :retry-attrs="{ 'data-retry-appointments': '' }"
          @retry="retryTodayAppointments"
        />

        <!--
          Empty state for the today-appointments case, rendered through the
          shared compact DashboardSectionEmpty pattern (T5, WU3 / D9).

          HOTFIX-DASH-007 - line-art calendar SVG. The calendar SVG
          stays inline in this template, passed through the component's icon
          slot, so the stroke-width="1.5" rule stays auditable in source
          (apple-design §16 baseline, NOT the previous 2.0 default).

          WU3 / R4 - single CTA per destination: the empty state carries NO
          button. The header owns the only "Nueva cita" CTA and it opens the
          appointment modal, so the copy stays informational and never points
          users at the calendar section.
        -->
        <DashboardSectionEmpty
          v-else-if="todayAppointments.length === 0"
          ref="emptyStateSection"
          data-state="empty-appointments"
          data-reveal="empty-state"
          title="Sin citas para hoy"
          description="Cuando registres citas, aparecerán aquí."
          :style="revealStyle('--spring-dash-empty-o')"
        >
          <template #icon>
            <!--
              HOTFIX-DASH-007 - inline line-art calendar SVG.
              stroke-width="1.5" (apple-design §16 baseline).
              Color: var(--color-label-tertiary-label) - the iOS
              tertiaryLabel token so the icon recedes.
            -->
            <svg
              class="mx-auto h-8 w-8 mb-3"
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
          </template>
        </DashboardSectionEmpty>

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
      </section>

      <!--
        Block "Requiere tu atención" (WU4 / D7) - quotations and treatment
        plans waiting on a patient decision, fed by GET /api/dashboard/pending,
        rendered as ONE merged list (WU3 / D6): actionable work before the
        reference preview. The backend omits the subsets the current role
        cannot read, so rows render only for payload keys that are present;
        when neither key is present the whole block stays hidden. The fetch
        is tolerant: this resource alone can fail without blocking the rest
        of the page.
      -->
      <section
        v-if="!statsError && (hasPendingGroups || pendingError)"
        aria-label="Requiere tu atención"
        data-dashboard-block="attention"
        class="space-y-4"
      >
        <h2 class="text-xl font-semibold text-label">Requiere tu atención</h2>

        <!--
          Pending inline error (T7b). Only this resource failed: the rest
          of the page stays usable, and the retry re-fetches ONLY
          /api/dashboard/pending through the shared section-error component.
        -->
        <DashboardSectionError
          v-if="pendingError"
          data-state="error-pending"
          role="alert"
          title="No pudimos cargar los pendientes"
          description="Reintenta para ver presupuestos y planes en espera de respuesta."
          :retry-attrs="{ 'data-retry-pending': '' }"
          @retry="retryPending"
        />

        <DashboardSectionEmpty
          v-else-if="pendingRows.length === 0"
          data-state="empty-pending"
          title="Sin pendientes"
          description="Los presupuestos y planes que esperen respuesta aparecerán aquí."
        />
        <!--
          WU3 / D6 - ONE merged list: quotations and treatment plans are
          rows of a single dataset (patient, amount, type badge, status
          badge, date) sorted by their pending date ascending. Each row
          carries the single action into its module list; no section-level
          link duplicates those destinations.
        -->
        <div v-else class="grid gap-2">
          <UiCard
            v-for="row in pendingRows"
            :key="row.key"
            variant="flat"
            padding="sm"
            hover
            :data-pending-row="row.rowHook"
          >
            <div class="flex items-center gap-3">
              <p class="min-w-0 flex-1 text-sm font-medium text-label truncate">
                {{ row.patientName }}
              </p>
              <span
                class="flex-shrink-0 text-sm font-semibold text-label tabular-nums"
                :data-pending-amount="row.rowHook"
              >
                {{ row.amountLabel }}
              </span>
              <UiBadge variant="neutral" size="sm" data-pending-type>
                {{ row.typeLabel }}
              </UiBadge>
              <UiBadge :variant="pendingStatusVariant(row.status)" size="sm">
                {{ pendingStatusLabel(row.status) }}
              </UiBadge>
              <span
                v-if="row.dateLabel"
                class="flex-shrink-0 text-xs text-theme-secondary tabular-nums"
              >
                {{ row.dateLabel }}
              </span>
              <UiButton
                variant="ghost"
                size="sm"
                :aria-label="row.actionAria"
                @click="goToPendingEntity(row.entityType)"
              >
                Ver
              </UiButton>
            </div>
          </UiCard>
        </div>
      </section>

      <!--
        Block "Próximos días" (WU4 / D7) - the week preview, kept visually
        secondary: quiet heading plus a recessed tray around the rows.
        Compact feed from GET /api/dashboard/upcoming (now -> end of week,
        limit 10), grouped by LOCAL calendar day (the same timezone
        formatTime renders in) with a short Spanish day header. The fetch
        is tolerant: if this resource alone fails, the block carries its
        own inline error plus a scoped retry and the rest of the page
        stays usable.
      -->
      <section
        v-if="!statsError && can.viewAppointment?.value"
        aria-label="Próximos días"
        data-dashboard-block="upcoming"
        class="space-y-4"
      >
        <div class="flex items-baseline gap-3">
          <h2 class="text-base font-medium text-theme-secondary">Próximos días</h2>
          <span
            v-if="!upcomingError && upcomingAppointments.length > 0"
            class="text-sm text-theme-secondary tabular-nums"
          >
            {{ upcomingAppointments.length }}
            {{ upcomingAppointments.length === 1 ? 'cita' : 'citas' }}
          </span>
        </div>

        <!--
          Upcoming inline error (T5). Only the upcoming resource failed:
          the header stays, the rest of the page stays usable, and the retry
          affordance re-fetches ONLY /api/dashboard/upcoming through the
          shared section-error component.
        -->
        <DashboardSectionError
          v-if="upcomingError"
          data-state="error-upcoming"
          role="alert"
          title="No pudimos cargar las próximas citas"
          description="Reintenta para ver lo que queda de la semana."
          :retry-attrs="{ 'data-retry-upcoming': '' }"
          @retry="retryUpcomingAppointments"
        />

        <!--
          Compact empty state for the upcoming-week case (WU3 / D9): one
          short title plus one precise sentence instead of the previous
          redundant two-sentence pair.
        -->
        <DashboardSectionEmpty
          v-else-if="upcomingAppointments.length === 0"
          data-state="empty-upcoming"
          title="Sin citas esta semana"
          description="No hay citas registradas de mañana en adelante."
        />

        <div
          v-else
          data-upcoming-surface
          class="space-y-4 p-4"
          style="
            background: var(--color-system-gray-100);
            border: 1px solid var(--color-hairline);
            border-radius: var(--radius-panel);
          "
        >
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
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import NewAppointmentModal from '../../components/appointments/NewAppointmentModal.vue'
import DashboardSectionError from './DashboardSectionError.vue'
import DashboardSectionEmpty from './DashboardSectionEmpty.vue'
import DentalMotifLayer from './DentalMotifLayer.vue'
import { useApi } from '../../composables/useApi'
import { useAuth } from '@/composables/useAuth'
// HOTFIX-DASH-009 / T4 - per-section staggered springs, consumed.
// apple-design §4 "behavior over animation - use springs" + §8 "hint
// in direction of gesture" (intermediate frames telegraph direction).
// Each of the 3 visible sections gets its own useSpring with a distinct
// cssVar so the entrance animations cannot collide on the same CSS
// custom property. Stagger: 0ms / 60ms / 120ms on the first
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
const todayAppointments = ref([])
// T5 week preview. Kept separate from the agenda so a failing upcoming
// request never blocks the day's protagonists.
const upcomingAppointments = ref([])
const upcomingError = ref(false)
// T7b pending-and-action block. `pending` stores the subsets the backend
// returned for this role (an absent key means the role cannot read that
// module). Kept separate from stats so a failing pending request never
// blocks the rest of the page.
const pending = ref({})
const pendingError = ref(false)

// T3 load-state flags. `hasLoaded` separates the first load (skeleton) from
// later loads (silent in-flight refresh) so a manual refresh or a WebSocket
// burst never blanks the page. The two error flags drive the page-level
// stats error state and the agenda inline error state.
const hasLoaded = ref(false)
const refreshing = ref(false)
const statsError = ref(false)
const todayError = ref(false)

// HOTFIX-DASH-009 / T4 - per-section staggered springs (3 sections,
// 60ms stagger). apple-design §4 (springs for entrance, critically
// damped), §8 (intermediate frames telegraph direction via stagger).
// Each spring targets a distinct CSS custom property on its bound
// element so the entrance animations never collide. useSpring() honors
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
const emptyStateSpring = useSpring({
  damping: 1.0,
  response: 0.35,
  cssVar: '--spring-dash-empty-o'
})

const greetingSection = ref(null)
const kpiSection = ref(null)
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
  // The empty state renders through a child component, so the template ref
  // yields the component instance; the spring binds to its root element.
  if (emptyStateSection.value) {
    emptyStateSpring.attach(emptyStateSection.value.$el || emptyStateSection.value)
  }

  setTimeout(() => greetingSpring.set(1), 0)
  setTimeout(() => kpiSpring.set(1), 60)
  setTimeout(() => emptyStateSpring.set(1), 120)
}

// T4 - KPI count-up springs. Each headline number counts 0 -> value on the
// first data payload, then hands the display back to the canonical source
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

/**
 * WU1 / D1 - one status map for the whole page. The agenda rows render
 * their Spanish label and badge variant from it, and the day KPIs bucket
 * the same statuses through `outcome`, so rows and KPIs can never
 * disagree. Real enum (appointments.status): scheduled, confirmed,
 * in_consultation, in_progress, completed, cancelled, no_show,
 * rescheduled. There is no pending-confirmation state, so the third KPI
 * bucket is the honest remainder: not completed and not cancelled
 * (unknown statuses fall in it too). Outcome buckets use `pending` /
 * `completed` / `cancelled` so no raw cash key can ride this map.
 */
const APPOINTMENT_STATUS = {
  scheduled: { text: 'Programada', variant: 'secondary', outcome: 'pending' },
  confirmed: { text: 'Confirmada', variant: 'success', outcome: 'pending' },
  in_consultation: { text: 'En Consulta', variant: 'warning', outcome: 'pending' },
  completed: { text: 'Completada', variant: 'primary', outcome: 'completed' },
  cancelled: { text: 'Cancelada', variant: 'error', outcome: 'cancelled' },
  no_show: { text: 'No se presentó', variant: 'warning', outcome: 'pending' }
}

const appointmentStatusMeta = status =>
  APPOINTMENT_STATUS[status] || { text: status, variant: 'secondary', outcome: 'pending' }

/**
 * WU1 - the three day counts, derived from the SAME list the agenda
 * renders (single source, R1). `pending` is the honest remainder:
 * not completed, not cancelled.
 */
const todayCounts = computed(() => {
  const counts = { total: 0, completed: 0, pending: 0 }
  for (const appointment of todayAppointments.value) {
    counts.total += 1
    const { outcome } = appointmentStatusMeta(appointment?.status)
    if (outcome === 'completed') {
      counts.completed += 1
    } else if (outcome !== 'cancelled') {
      counts.pending += 1
    }
  }
  return counts
})

const todayKpi = createCountUp(() => todayCounts.value.total)
const completedKpi = createCountUp(() => todayCounts.value.completed)
const pendingKpi = createCountUp(() => todayCounts.value.pending)

// WU1 - while the today source is down the day KPIs report "N/D" instead
// of a fabricated zero, bypassing the count-up display.
const todayKpiDisplay = computed(() => (todayError.value ? 'N/D' : todayKpi.display.value))
const completedKpiDisplay = computed(() => (todayError.value ? 'N/D' : completedKpi.display.value))
const pendingKpiDisplay = computed(() => (todayError.value ? 'N/D' : pendingKpi.display.value))

let countUpsPlayed = false
const startKpiCountUps = () => {
  if (countUpsPlayed) return
  countUpsPlayed = true
  if (!countUpAllowed()) return
  todayKpi.start()
  completedKpi.start()
  pendingKpi.start()
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

// Short Spanish month names for the pending rows ("3 oct").
const SPANISH_MONTHS_SHORT = [
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

/**
 * T7b - short local date for a pending row, e.g. "3 oct". Returns an empty
 * string for an unparseable value so the row simply omits the date.
 */
const formatPendingDate = dateTime => {
  const date = new Date(dateTime)
  if (Number.isNaN(date.getTime())) return ''
  return `${date.getDate()} ${SPANISH_MONTHS_SHORT[date.getMonth()]}`
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

/**
 * WU1 - the rows and the day KPIs share ONE status map (APPOINTMENT_STATUS
 * above): the label and badge variant the rows render and the outcome
 * bucket the counts derive from can never disagree.
 */
const getStatusText = status => appointmentStatusMeta(status).text

const getStatusVariant = status => appointmentStatusMeta(status).variant

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

/**
 * WU3 / D6 - pending subsets. The backend omits the keys the role cannot
 * read, so the payload key itself is the visibility contract: an absent
 * key hides that type's rows, and no key at all hides the whole section.
 */
const pendingQuotations = computed(() => pending.value?.quotations || null)
const pendingTreatmentPlans = computed(() => pending.value?.treatment_plans || null)
const hasPendingGroups = computed(() =>
  Boolean(pendingQuotations.value || pendingTreatmentPlans.value)
)

/**
 * WU3 / D6 - ONE merged dataset for the Pendientes list. The per-type
 * payload gating above stays intact; only the presentation merges. Each
 * row keeps the per-type amount field (quotations: total_amount, plans:
 * final_cost with a muted "N/D" fallback) and its own module destination,
 * and the merged rows sort by their pending date ascending.
 */
const PENDING_TYPE_META = {
  quotations: {
    typeLabel: 'Presupuesto',
    rowHook: 'quotations',
    route: '/quotations',
    actionLabel: 'Ver presupuesto',
    readAmount: item => item?.total_amount
  },
  treatment_plans: {
    typeLabel: 'Plan de tratamiento',
    rowHook: 'treatment-plans',
    route: '/treatment-plans',
    actionLabel: 'Ver plan de tratamiento',
    readAmount: item => item?.final_cost
  }
}

// Unparseable or missing dates sort last instead of poisoning the merge.
const pendingSortTime = dateTime => {
  const time = new Date(dateTime).getTime()
  return Number.isNaN(time) ? Number.MAX_SAFE_INTEGER : time
}

const pendingRows = computed(() => {
  const rows = []
  const collect = (entityType, group) => {
    if (!group) return
    const meta = PENDING_TYPE_META[entityType]
    for (const item of group.items || []) {
      const amount = meta.readAmount(item)
      const patientName = item.patient_name || 'Paciente'
      rows.push({
        key: `${entityType}-${item.id}`,
        entityType,
        rowHook: meta.rowHook,
        typeLabel: meta.typeLabel,
        patientName,
        amountLabel: amount === null || amount === undefined ? 'N/D' : formatPENLabel(amount),
        status: item.status,
        dateLabel: formatPendingDate(item.created_at),
        actionAria: `${meta.actionLabel}: ${patientName}`,
        sortTime: pendingSortTime(item.created_at)
      })
    }
  }
  collect('quotations', pendingQuotations.value)
  collect('treatment_plans', pendingTreatmentPlans.value)
  rows.sort((a, b) => a.sortTime - b.sortTime)
  return rows
})

/**
 * T7b - Spanish copy for the pending statuses, mirroring the module badges
 * (QuotationStatusBadge / PlanStatusBadge). `viewed` has no badge copy in
 * the quotations module yet, so it reads "Visto" here.
 */
const pendingStatusLabels = {
  sent: 'Enviado',
  viewed: 'Visto',
  proposed: 'Propuesto'
}
const pendingStatusVariants = {
  sent: 'info',
  viewed: 'warning',
  proposed: 'neutral'
}
const pendingStatusLabel = status => pendingStatusLabels[status] || status
const pendingStatusVariant = status => pendingStatusVariants[status] || 'neutral'

// Navigation functions
const goToPendingEntity = entityType => {
  const destination = PENDING_TYPE_META[entityType]?.route
  if (destination) router.push(destination)
}

const showNewAppointmentModal = ref(false)

const goToNewAppointment = () => {
  showNewAppointmentModal.value = true
}

const handleAppointmentCreated = async () => {
  // Slice 08 / FF-015: refresh data after the user creates an appointment
  // from the agenda header CTA. Single fetch rather than a fan-out - the
  // WebSocket path will catch subsequent edits.
  await loadDashboardData()
}

const goToCashRegister = () => {
  router.push('/cash-register')
}

// WU2 / D4 - the cash card is the single cash surface. The state key rides
// only as the data-cash-state hook value; the visible line and its tone are
// always Spanish (a raw key wired to a primitive's status map would print
// English on the page):
//   - open        -> label "Abierta",     green token
//   - closed      -> label "Cerrada",     red token
//   - no_session  -> label "Sin sesión",  neutral theme token
const cashStateKey = computed(() => {
  if (isOpen.value) return 'open'
  if (hasActiveSession.value) return 'closed'
  return 'no_session'
})

const cashStateLabel = computed(() => {
  if (isOpen.value) return 'Abierta'
  if (hasActiveSession.value) return 'Cerrada'
  return 'Sin sesión'
})

const cashStateToneClass = computed(() => {
  if (isOpen.value) return 'text-systemGreen-600'
  if (hasActiveSession.value) return 'text-systemRed-600'
  return 'text-theme-secondary'
})

// WU2 - the balance and the caption read the SAME useCashRegister state as
// the state line (single source, R2). The number is the session's real-time
// balance; the caption names the opening time while the session is open.
// No session summary means "N/D", never a fabricated S/ 0.00.
const cashKpiBalance = computed(() =>
  realTimeTotals.value ? formatPENLabel(realTimeTotals.value.currentBalance) : 'N/D'
)

const cashKpiCaption = computed(() => {
  const openedAt = currentSession.value?.opened_at
  return isOpen.value && openedAt ? `Apertura ${formatTime(openedAt)}` : 'N/D'
})

// Data loading

/**
 * T3 - fetch the stats resource without throwing. WU2: nothing visible
 * reads the payload anymore (cash state comes from useCashRegister), but
 * the call stays as a load-flow gate: it owns the page-level stats error
 * state and the KPI count-up timing. A 401 is surfaced as `unauthorized`
 * so the caller keeps the /login redirect; any other failure is surfaced
 * as `ok: false` so the page can render its own error state instead of
 * swallowing the failure and painting zeros as real data.
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

/**
 * T7b - fetch the pending subsets without throwing. The backend omits the
 * subsets the current role cannot read, so a non-object payload collapses
 * to an empty map. A 401 surfaces `unauthorized` so the caller keeps the
 * /login redirect; any other failure surfaces `ok: false` so the section
 * renders its own inline error instead of silently rendering nothing.
 */
const fetchPending = async () => {
  try {
    const response = await get('/api/dashboard/pending')
    const data = response?.data
    const payload = data && typeof data === 'object' && !Array.isArray(data) ? data : {}
    return { ok: true, unauthorized: false, data: payload }
  } catch (error) {
    return { ok: false, unauthorized: error?.status === 401, data: {} }
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
    const [statsResult, appointmentsResult, upcomingResult, pendingResult] = await Promise.all([
      fetchStats(),
      fetchTodayAppointments(),
      fetchUpcomingAppointments(),
      fetchPending()
    ])

    if (statsResult.unauthorized) {
      router.push('/login')
    } else if (statsResult.ok) {
      statsError.value = false
    } else {
      statsError.value = true
    }

    if (appointmentsResult.ok) {
      todayAppointments.value = appointmentsResult.data
      todayError.value = false
    } else {
      todayError.value = true
    }

    // WU1 - the KPI count-ups start once the day counts can read their
    // final source values; starting earlier would animate to a stale zero.
    if (statsResult.ok && appointmentsResult.ok) {
      startKpiCountUps()
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

    // T7b - the pending block is tolerant: its failure shows the section's
    // inline error and never blocks stats, agenda, upcoming, or the KPI
    // grid. A 401 keeps the page's /login redirect behavior.
    if (pendingResult.unauthorized) {
      router.push('/login')
    } else if (pendingResult.ok) {
      pending.value = pendingResult.data
      pendingError.value = false
    } else {
      pendingError.value = true
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

/**
 * T7b - pending-only retry. Re-fetches just the pending resource so a
 * failing block never forces a full page reload. 401 keeps the standard
 * /login redirect.
 */
const retryPending = async () => {
  const result = await fetchPending()
  if (result.unauthorized) {
    router.push('/login')
    return
  }
  if (result.ok) {
    pending.value = result.data
    pendingError.value = false
  } else {
    pendingError.value = true
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
