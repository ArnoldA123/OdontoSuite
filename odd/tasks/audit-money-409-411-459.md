# Auditoría del dinero — #409, #411 y #459 (cobro, carga de caja y desglose)

Tres hallazgos de la auditoría UI U5 (#368, plan #13) sobre el módulo de caja.
Rama `fix/audit-money-409-411-459`, worktree `<ruta-local>/odontosuite-money-409-411-459`,
base `main` @ `56103c5d`. Un commit de unidad por issue.

## Specs

S1. #409: «Toda clave de error sin campo visible cae en el aviso general del
modal (o en un toast), nunca en silencio.» Medido: con la cuenta sin gaveta
propia el cobro ad-hoc responde
`422 {"message":"Los datos proporcionados no son válidos.","errors":{"session":["No hay una sesión de caja abierta…"]}}`
y el modal no pinta error general, ni de campo, ni toast.

S2. #409: «El cobro de una cita pendiente envía `reference_number` (y `notes`)
cuando el formulario los recoge; Yape, tarjeta y transferencia se cobran con su
referencia por esa puerta.» Medido: el modal muestra «Referencia», la deja
llenar y no la envía en `POST /api/pending-payments/{id}/pay`; la API rechaza
`422 errors.reference_number` y acepta el mismo cobro con `reference_number`.

S3. #409: «Una prueba cubre el 422 sin sesión visible y el cobro con Yape con y
sin referencia.»

S4. #411: «El hero y los KPIs tienen estado de carga propio (skeleton o
spinner) y no publican «Sin sesión»/`N/D` mientras la primera lectura está en
vuelo.» Medido: con `GET /api/cash-register/current` retrasado 900 ms el primer
fotograma dice «Sin sesión», KPIs `N/D`, 0 skeletons en el hero.

S5. #411: «El error de la sesión y el de las listas no comparten el mismo `ref`;
un fallo de la carga principal pinta el panel y su «Reintentar», y el reintento
recupera sin recargar la página.» Medido: con `GET /api/cash-register/current`
forzado a 500 no se pintan `[data-caja-error]` ni `[data-caja-retry]`, porque
`loadSessions()` limpia el mismo `ref` de error al empezar y al terminar.

S6. #411: «Una prueba de navegador cubre el primer pintado y el error con
reintento.»

S7. #459: «El desglose no se contradice: cuando no hay ítems y el importe es
fijo por tipo de cita, el subtotal es el total (o el subtotal no se muestra), y
una nota nombra el origen del importe. La cifra que se cobra no cambia.»
Medido: `BillingService::paymentPreview` suma sólo ítems del plan en `subtotal`
y publica el total desde `AppointmentBalance`; una cita de monto fijo pinta
Subtotal `S/ 0,00` y Total a cobrar `S/ 90,00` sin línea que lo explique.

## Tasks

- T1 — #409: enrutar toda clave de error del API al campo visible o al aviso
  general, y enviar `reference_number`/`notes` en la puerta de cobros pendientes.
  Spec: S1-S3. Commit `fix(cash): …`.
- T2 — #411: separar el error y la carga de la sesión de los de las listas, y
  pintar skeleton en hero y KPIs durante la primera lectura. Spec: S4-S5.
- T3 — #411: prueba de navegador (primer pintado y error con reintento) y su
  corrida de evidencia. Spec: S6. Comparte commit con T2.
- T4 — #459: `paymentPreview` declara el origen del importe y el desglose no se
  contradice; la cifra que se cobra no cambia. Spec: S7. Commit `fix(cash): …`.
- T5 — Sincronizar `AGENTS.md` §12 y correr la verificación completa (Vitest,
  PHPUnit MySQL, Pint, ESLint, Prettier, `doc-drift`). Commit `docs: …`.

## Log

L1. «haz las issues #409 #411 #459 en un worktree nuevo»

L2. (exploración) Causas confirmadas por lectura: `PaymentModal.vue:557-571`
copia `errors` del API y sólo `fieldError('general')` tiene bloque visible (la
clave `session` se pierde); `submitManualPayment` arma el `POST` de cobros
pendientes sin `reference_number` ni `notes`; `useCashRegister.js` usa un solo
`error` y un solo `loading` para `loadCurrentSession`, `getSessions` y los demás
loaders; `CashRegisterPage.vue` no tiene primitiva de carga en el hero;
`BillingService::paymentPreview` deriva `subtotal` sólo de los ítems del plan.

L3. (alcance) Los tres hallazgos son `area:frontend`; #459 toca además el
contrato del previo (`BillingService::paymentPreview`). No hay migración: la
cifra que se cobra, `final_amount` y el estado de cuenta no cambian.

L4. (T1) #409 entregado: `applyApiErrors` enruta cada clave del API a su campo
o al aviso general (mapea `reference_number` → `reference`) y la puerta de
cobros pendientes envía `reference_number`/`notes`. RED observado en
`caja-cobros` (aviso ausente y payload incompleto) y GREEN 20/20. Commit del
issue en la rama.

L5. (T2) #411 entregado: `sessionLoading`/`sessionError` separados de
`loading`/`error`, `canOpen` no ofrece puerta sobre un estado desconocido, el
hero pinta skeleton en badge y KPIs, publica «Estado no disponible» ante el
fallo y el panel «Reintentar» recupera. RED en `useCashRegister` y
`caja-caja-actual`, GREEN 12/12.

L6. (T3) El pase de navegador encontró que `UiSkeleton` ignoraba `width`/
`height` y su rectángulo colapsaba a ancho cero dentro de una fila flex: el
skeleton existía pero era invisible (medido con bounding box 0×200). La
primitiva honra ahora sus props: quien no las declara pinta el mismo tamaño y
`ProcedureStatsPage` recibe el `height="320px"` que ya declaraba (`ui-skeleton`,
3), y `cash-loading.spec.js` pasa en navegador real
sobre `odontosuite_audit` con el servidor en `:8014`: 2/2, evidencia en
`.atl/qa-evidence/audit/money-loading/`.

L7. (T4) #459 entregado: `amount_source` y subtotal = total cuando no hay
ítems del plan; la tarjeta nombra «Monto fijo por tipo de cita» junto a la
cifra. RED en `AppointmentPaymentPreviewTest` (amount_source nulo) y
`caja-ready-to-bill` (sin hooks de totales), GREEN 2/2 y 11/11.

L8. (T5) Rodajas de entrega medidas: #409 (244 líneas), #411 (382), #459 (202)
y documentación (60). Estrategia: un PR por issue (#409, #411 y #459 con su
entrada de §12), cada uno dentro del presupuesto de ~400 líneas del revisor
nativo. Los hashes no se fijan aquí: cada rebase sobre `main` los reescribe y la
evidencia estable es el commit por issue en la rama.

L9. (verificación independiente) Siete specs `holds`. Hallazgo real: la
primitiva compartida `UiSkeleton` ya tenía una consumidora con tamaño declarado
(`ProcedureStatsPage`, `height="320px"`) que en `main` era una prop muerta; el
arreglo le da el alto que pedía. Se corrigió la redacción de §12 y de L6 para
decirlo con exactitud, y se añadió la aserción de `canOpen` bajo `sessionError`
que faltaba (un borrado silencioso de esa cláusula pasaba toda la suite JS).
