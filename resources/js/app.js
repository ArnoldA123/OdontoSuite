import './bootstrap';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import LoginPage from './modules/auth/LoginPage.vue';
import { requireAuth, requireGuest } from './router/auth';
import uiComponents from './plugins/ui-components';
import { useEcho } from './composables/useEcho';

// Inicializar Echo para WebSockets
if (typeof window !== 'undefined') {
  const { echo } = useEcho();
  window.Echo = echo;
}

// Configuración del router
const routes = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/login',
    name: 'login',
    component: LoginPage,
    beforeEnter: requireGuest
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('./modules/dashboard/DashboardPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/calendar',
    name: 'calendar',
    component: () => import('./modules/appointments/CalendarPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/appointments/new',
    name: 'new-appointment',
    component: () => import('./modules/appointments/NewAppointmentPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/patients',
    name: 'patients',
    component: () => import('./modules/patients/PatientsPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/patients/:id',
    name: 'patient-detail',
    component: () => import('./modules/patients/PatientDetailPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/professionals',
    name: 'professionals',
    component: () => import('./modules/professionals/ProfessionalsPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/professionals/:id',
    name: 'professional-detail',
    component: () => import('./modules/professionals/ProfessionalDetailPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/environments',
    name: 'environments',
    component: () => import('./modules/environments/EnvironmentsPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/environments/:id',
    name: 'environment-detail',
    component: () => import('./modules/environments/EnvironmentDetailPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/appointment-types',
    name: 'appointment-types',
    component: () => import('./modules/appointment-types/AppointmentTypesPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/appointment-types/:id',
    name: 'appointment-type-detail',
    component: () => import('./modules/appointment-types/AppointmentTypeDetailPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/business-intelligence',
    name: 'business-intelligence',
    component: () => import('./modules/business-intelligence/BusinessIntelligencePage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/cash-register',
    name: 'cash-register',
    component: () => import('./modules/cash-register/CashRegisterPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/test',
    name: 'test',
    component: () => import('./modules/test/TestPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/treatment-plans',
    name: 'treatment-plans',
    component: () => import('./modules/treatment-plans/TreatmentPlansPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/quotations',
    name: 'quotations',
    component: () => import('./modules/quotations/QuotationsPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/medical-records',
    name: 'medical-records',
    component: () => import('./modules/medical-records/MedicalRecordsPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/specialty-records',
    name: 'specialty-records',
    component: () => import('./modules/specialty-records/SpecialtyRecordsPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/ai-analysis',
    name: 'ai-analysis',
    component: () => import('./modules/ai-analysis/AiAnalysisPage.vue'),
    beforeEnter: requireAuth
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

// Crear la aplicación Vue con un componente raíz
const App = {
  template: '<router-view />'
};

const app = createApp(App);
app.use(router);
app.use(uiComponents);
app.mount('#app');
