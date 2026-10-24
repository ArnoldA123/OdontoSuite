# Quotations Contract Hardening — CERRADO ✅

Rama: `fix/quotations-contract-hardening` (rebasada sobre `main` @ 9153a68)
PR: #238 · merge commit `6046069` · worktree `.worktrees/quotations-contract` (eliminado)
Creado: 2026-10-20 · Orquestador: el Gentleman

## Specs

- **S1.** "quiero hacer issues #200 #201 #202 #203 #204 desarrolles, en un
  worktree nuevo" — el trabajo se aísla en un worktree y en la rama
  `fix/quotations-contract-hardening`, partiendo de `main` @ 3cc3bd9. La
  evidencia de cada issue se reconfirma contra el código de `main`; los
  números de línea de los issues no se reproducen.
- **S2 (#200).** "`PrivateChannel('quotations')` (y decidir el de
  `dashboard-updates` en #183) + autorización por rol en `routes/channels.php`
  (lectura: administrador, finanzas, odontologo, implantologo, recepcionista —
  el mismo grupo de `routes/api.php`)." Consumidores a `privateChannel`:
  `QuotationsPage.vue`, `useWebSocketNotifications.js` y
  `PatientDetailPage.vue`. Los guards que fijan el literal público se re-anclan
  al contrato privado: `CajaPagesAppShellTest` (PAGOS-RT-001) y
  `PatientDetailAppShellTest` (PAC-RT-001). Test de autorización de canal (rol
  permitido vs denegado) y smoke JS del listener privado.
- **S3 (#201).** "aprobar/rechazar sólo desde `sent`/`viewed` (422 con mensaje
  explícito desde el resto), y una sola fila de aprobación vigente." "Decidir y
  escribir la máquina de estados: quién pasa a `viewed` ... y quién a
  `expired`". "Alinear `index?expired=` con el estado o documentar que es un
  cálculo derivado de `valid_until`." Tests de transición (permitida y
  rechazada) y del escritor de `viewed`/`expired`.
- **S4 (#201, decisión del usuario).** "La descarga del PDF marca
  sent→viewed": `GET /api/quotations/{id}/pdf` ejecuta la transición
  `sent → viewed`, idempotente; `draft`/terminales no cambian.
- **S5 (#201, decisión del usuario).** "la mejor opcion que vaya de acuerdo al
  caso y tu speriencia como ingeniero y mejores deciciones" — se adopta un
  comando programado `quotations:expire` que pasa `sent`/`viewed` vencidos a
  `expired`, y el estado pasa a ser la fuente de verdad del filtro
  `index?expired=` y del contador `summary.overdue`. El badge de vigencia del
  front sigue derivando de `valid_until` para avisar antes de la corrida.
- **S6 (#202).** "Decidir contrato: emitir `QuotationRejected` (nuevo) o reusar
  `QuotationUpdated` en `rejectQuotation`, envuelto en try/catch como el resto
  (`QuotationServiceTest` ya fija ese patrón)." Se adopta el evento nuevo:
  `quotation.rejected` con `quotation` y `reason`. "Conectar el evento al canal
  (ver #200: hoy público) y consumirlo en la página y en
  `useWebSocketNotifications`. Mapear la notificación en `NotificationService`
  y cubrirlo con test."
- **S7 (#203).** "Abrir el detalle desde la lista: `/quotations?open=<id>` (o el
  query que defina la página) y que `/quotations` lo consuma, en vez de
  inventar una ruta hija." "Reemplazar el estado crudo por
  `QuotationStatusBadge` (ya cubre los 6 estados) o por el vocabulario."
  "Migrar la descarga a `useApi().download`."
- **S8 (#204).** "Extraer el saneo a un solo lugar (un trait/FormRequest
  compartido o un mutator) y aplicarlo en store y update, con los mismos
  topes." "Decidir si `terms_conditions` admite HTML: si no (hoy se guarda
  plano desde el editor), normalizar a texto y documentarlo". Se decide texto
  plano (el PDF ya lo escapa con `e()`), topes 2000/1000. "`rejectQuotation`
  anexa el motivo al `notes` sin tope (el `reason` sí tiene `max:500`)." "Test
  que fija la paridad: el mismo input por store y por update deja el mismo
  valor almacenado."
- **S9.** Convenciones de `AGENTS.md` §7: código limpio y profesional, sin
  comentarios pedagógicos salvo pedido explícito; pnpm, nunca npm; commits
  semánticos cortos en inglés, sin emojis; código e identificadores en inglés,
  copy de UI en español.

## Tasks

| ID  | Issue | Tarea | Commit |
| --- | ----- | ----- | ------ |
| T1 | #204 | Saneo de texto libre en un solo lugar + `UpdateQuotationRequest` + tope del motivo de rechazo | eddeb01 |
| T2 | #201 | Guarda de transición, fila única de aprobación, escritor de `viewed` (PDF), comando `quotations:expire`, filtro/resumen por estado | 1e56f6c |
| T3 | #200 | Canal privado `quotations` (eventos, autorización, tres consumidores, guards) | cccd6db |
| T4 | #202 | Evento `QuotationRejected` + notificación + consumidores | 8a2dfcc |
| T5 | #203 | Detalle de paciente: `?open=`, vocabulario de estado y descarga canónica | 6a9a988 |
| T6 | — | Docs (`AGENTS.md` §4/§6/§12), guard de drift y verificación completa | 423208f |
| T7 | — | Docblocks obsoletos de los tres eventos de presupuesto | a9ed5cc |
| T8 | — | `QuotationController`: 404 para ids inexistentes y 500 sin filtrar la excepción (+ changelog) | a326f5f |

### Fronteras de slice

La rama supera el slice de ~400 líneas: es una rama de feature con unidades de
commit revisables, no un único cambio. Fronteras registradas:

- **Slice 1 — contrato backend:** T1 + T2 (78033d5, 80098e0), 914 líneas
  cambiadas. Revisable por separado: saneo y máquina de estados.
- **Slice 2 — contrato de tiempo real:** T3 + T4 (bac76e9, a5967ce). Canal
  privado y evento de rechazo comparten consumidores.
- **Slice 3 — superficie de paciente + docs:** T5 + T6 (cc35c79 + 4ee1652).

Si se abre PR, va encadenado por slice (`chained-pr`), no como un único diff de
la rama completa; el orquestador no crea PR sin decisión del usuario.

## Log

- **L1.** "quiero hacer issues #200 #201 #202 #203 #204 desarrolles, en un
  worktree nuevo"
- **L2.** Decisión #201 `viewed`: "La descarga del PDF marca sent→viewed"
  (opción elegida por el usuario).
- **L3.** Decisión #201 `expired`: "la mejor opcion que vaya de acuerdo al caso
  y tu speriencia como ingeniero y mejores deciciones" — se adopta comando
  programado + estado autoritativo.
- **L4.** Evidencia base reconfirmada contra `main` @ 3cc3bd9: los tres eventos
  emiten `Channel('quotations')` + `Channel('dashboard-updates')` con el modelo
  completo; `approveQuotation`/`rejectQuotation` no validan estado;
  `rejectQuotation` no emite evento; `PatientDetailPage.vue` navega a
  `/quotations/{id}` (sin ruta) y duplica el fetch del PDF;
  `QuotationController@update` valida `terms_conditions`/`notes` sin saneo.
- **L5.** `dashboard-updates` sigue público para el resto de eventos (#183);
  los tres eventos de presupuesto dejan de emitirse por ese canal porque su
  payload lleva datos del paciente y montos. `DashboardPage` no escucha eventos
  de presupuesto en ese canal, así que no hay consumidor roto.
- **L6.** Verificación final sobre la base rebasada: suite PHP completa
  `1774 tests / 8387 assertions`, exit 0 (solo deprecations preexistentes de
  PHPUnit); Vitest `94 files / 755 tests`, exit 0; `pint --test` 598 archivos;
  Prettier y ESLint sin errores; `scripts/audit/doc-drift.mjs` 12/12. Base de
  pruebas aislada `odontosuite_test_quotes` (otros agentes usan el servicio
  MySQL compartido).
- **L7.** `gentle_review assess` sobre el rango comprometido: riesgo nativo
  `medium`, `reviewDue=false` (`under_budget`), sin verificador independiente.
  No se inicia revisión nativa; la evidencia de verificación es la de L6.
- **L7.** La rama se rebasó sobre `origin/main` @ dc11206 (caja hardening,
  issues #191/#193/#194/#196/#198) para no acumular deriva: los hashes finales
  son los de la tabla y la suite completa se re-ejecutó sobre esa base. El único
  conflicto fue el changelog de `AGENTS.md`; se conservaron las dos entradas.
- **L8.** Hallazgos fuera de #200-#204 detectados durante la sesión, cerrados en
  la misma rama: docblocks obsoletos de los tres eventos (T7) y los nueve
  handlers de `QuotationController` que filtraban `$e->getMessage()` y
  respondían 500 por ids inexistentes (T8, follow-up de #196/#236). Deuda ancha
  levantada como issue #237: migrar `@test`/`@dataProvider` de doc-comments a
  atributos (519 deprecations de PHPUnit).
- **L9.** Entrega: PR #238 con CI en verde (quality, backend MySQL y frontend
  build en push y pull_request); merge commit 6046069 sobre `main`; rama local y
  remota eliminadas y worktree retirado. Hallazgos fuera de alcance: T7/T8
  cerrados en la rama; deuda de PHPUnit levantada como #237.
