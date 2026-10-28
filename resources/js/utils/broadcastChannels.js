/**
 * #378/#384 — the shell subscribes only to the private channels the session can
 * authorize.
 *
 * `App\Authorization\BroadcastChannels` resolves every channel to the catalog
 * permission that already guards its desk, and `BroadcastingAuthController`
 * answers 403 to any role outside that grant. Asking for such a channel on every
 * page load only produced console noise and a wasted handshake, so the fan-out
 * reads the same permission names the server uses.
 *
 * The PHP guard `tests/Unit/Authorization/BroadcastChannelAuthorizationTest.php`
 * crosses both artifacts: a channel added here without its server counterpart,
 * or a permission that drifts, fails the build.
 */
export const CHANNEL_PERMISSIONS = Object.freeze({
  appointments: 'manage.appointment',
  patients: 'view.patient',
  'treatment-plans': 'manage.treatment_plan',
  quotations: 'view.quotation',
  'medical-records': 'manage.medical_record',
  'specialty-records': 'manage.specialty_record',
  'cash-register': 'view.cash_register',
  reminders: 'manage.reminder',
  'procedure-catalog': 'view.procedure_catalog'
})
