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
    redirect: { path: '/dashboard', query: { openAppointmentModal: 'true' } }
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
    path: '/procedure-catalog',
    name: 'procedure-catalog',
    component: () => import('./modules/procedure-catalog/ProcedureCatalogPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/procedure-catalog/:id',
    name: 'procedure-catalog-detail',
    component: () => import('./modules/procedure-catalog/ProcedureCatalogDetailPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/my-procedures',
    name: 'my-procedures',
    component: () => import('./modules/my-procedures/MyProceduresPage.vue'),
    beforeEnter: requireAuth
  },
  {
    path: '/reception-procedures',
    name: 'reception-procedures',
    component: () => import('./modules/reception-procedures/ReceptionProceduresPage.vue'),
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
    path: '/cash-register/ready-to-bill',
    name: 'cash-register-ready-to-bill',
    component: () => import('./modules/cash-register/ReadyToBillPage.vue'),
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
  },
  // Sprint 1 (B-CASH-3): modulo de sucursales (solo administrador).
  // El role gate se hace en backend (Route::middleware('role:administrador'))
  // y en frontend con usePermissions().isAdministrador en la pagina.
  {
    path: '/settings/branches',
    name: 'settings-branches',
    component: () => import('./modules/settings/branches/BranchesPage.vue'),
    beforeEnter: requireAuth
  },
  // Sprint 2 (B-CASH-3): modulo de metodos de pago (solo administrador).
  {
    path: '/settings/payment-methods',
    name: 'settings-payment-methods',
    component: () => import('./modules/settings/payment-methods/PaymentMethodsPage.vue'),
    beforeEnter: requireAuth
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

// Crear la aplicación Vue con un componente raíz
// key=fullPath fuerza remonte del componente al cambiar ruta → arregla
// botón "atrás" del browser que quedaba pegado (onMounted no se llamaba).
const App = {
  template: '<router-view :key="$route.fullPath" />'
};

const app = createApp(App);
app.use(router);
app.use(uiComponents);
app.mount('#app');
