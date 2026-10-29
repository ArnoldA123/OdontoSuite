# #501 — el arnés de auditoría no soporta la corrida `--all` sobre una sola base

Rama `fix/audit-harness-run-501` (worktree `odontosuite-501`), desde `origin/main` `5987c305`.
Sólo arnés (`tests/e2e/audit/**`): ninguna ruta, payload, permiso ni pantalla de producto cambia.

## Specs

- **S1** — U1 reset: «`tests/e2e/audit/u1/recovery.js` llama `tokenInput.inputValue()` cuando el
  token del enlace ya está aplicado y el campo no existe; la espera agota el timeout de 30 s antes
  de leer `data-reset-token-applied`. Comprobado a mano: el modal pinta el marcador y no pide
  código.» La sonda no debe esperar por un campo que no existe y debe leer el marcador.
- **S2** — U3 finanzas: «`render for finanzas` mide el ancho de la columna semanal de un rol
  negativo (`expectation: negative`) que no recibe datos (403 esperado); la aserción
  `dayColumnWidth >= 96` recibe `null`.» El contrato de #397 se afirma sólo donde hay rejilla que
  medir; en el rol negativo la fila se mide y se declara no aplicable, no se falla.
- **S3** — U5: «`reuseStoredFixtures` reutiliza un `fixtures.json` de `.atl` anterior a la
  recreación de la base y `fixtures.appointments` llega `undefined`; arrastra `/cash-register`, el
  walkthrough de caja, `ready-to-bill` y la cotización.» El estado guardado se reutiliza sólo si su
  forma y su base son las de esta corrida; si no, se reconstruye. Un fallo de fixtures es un
  `reason`, nunca una excepción.
- **S4** — U7 report: «lee un `evidence.matrix.summary.deviations` de una evidencia vieja que no lo
  tiene; el informe no se regenera.» El informe se regenera con una evidencia sin esa clave y no
  inventa cifras: publica `null` donde no pudo medir.
- **S5** — U8 api-matrix: «exige `deviations.length >= 1` (`#434-#437` ya no deja desviaciones), así
  que la aserción falla con 0. La expectativa debe seguir al contrato corregido.» La matriz
  declara el contrato vigente (código de 21 caracteres → 422, `q` filtra, `is_active=false` filtra)
  y exige cero desviaciones.
- **S6** — U9 residuos: «la limpieza de la sesión no puede borrar un método de pago con
  transacciones creadas por la sesión U5; queda «residuos no declarados».» U5 no adopta filas de
  fixture de otra sesión (nombre `AUDIT-*`) como si fueran del sembrado, y U9 declara con su motivo
  la fila que la API no puede borrar porque el libro la referencia.
- **S7** — Cierre: la corrida completa sobre una sola base sembrada termina sin fallos del arnés
  (`0 failed`); los saltos quedan con su motivo medido.

## Tasks

| ID  | Specs | Ruta   | Entrega                                                              | Commit     |
| --- | ----- | ------ | -------------------------------------------------------------------- | ---------- |
| T1  | S1    | inline | U1 recovery: `inputValue()` sólo con el campo presente                | `6b028dac` |
| T2  | S2    | inline | U3: el contrato semanal de #397 no se afirma en el rol negativo       | `aaae1612` |
| T3  | S3    | inline | U5: predicado de reutilización (forma + base) y fixtures sin excepción | `81e0bd99` |
| T4  | S4    | inline | U7/U8: cobertura de la matriz a prueba de evidencia vieja             | `b726d9fb` |
| T5  | S6    | inline | U5 no adopta filas de fixture ajenas                                  | `c3cd1c17` |
| T6  | S6    | inline | U9: rechazo del libro declarado con su motivo                        | `41deb080` |
| T7  | S5    | inline | U8: contrato corregido y cero desviaciones                            | `f5284a8f` |
| T8  | S7    | inline | Corrida de reproducción y corrida de verificación medida              | ver §Log   |
| T9  | —     | inline | `listos para facturar` lee el vacío declarado (#452)                   | `3f3230c0` |
| T10 | S6    | inline | La caja mide el estado que la base le da (héroe, reuso, aritmética) | `b9055e47` |
| T11 | —     | inline | U6: el select de un diálogo no cierra el formulario que lo contiene   | `41d67c84` |
| T12 | —     | inline | Docs: estado, informe de cierre y esta nota                           | pendiente  |

**Entrega**: la rama acumula las seis causas del issue más la deuda que destapó la primera corrida
completa. Se entrega en tres PRs encadenados: **A** = las seis causas (`6b028dac`…`f5284a8f`),
**B** = residuo de U9, vacío de «listos para facturar» y matriz corregida (`41deb080`…`3f3230c0`),
**C** = sondas de caja y de planes + docs (`b9055e47`…docs) sobre B. El push y la apertura de los
PRs son decisión del usuario.

## Log

- **L1** (pedido del usuario, verbatim): «haz la issue 501 en worktree nuevo».
- **L2** (cuerpo de la issue, verbatim — causas medidas): ver §Specs S1-S6; reproducir con base
  sembrada y app levantada; «No se corrige aquí: la ola 7 no toca producto.»
- **L3** (medido): la base de escritura de U5/U6/U7 es `odontosuite_audit` por guarda explícita
  (`PHP_ENV` + primera sentencia del script); la corrida de verificación se hace sobre una base
  `odontosuite_audit` propia, sembrada, y no sobre `odontosuite` ni `odontosuite_test`.
- **L4** (medido): `PaymentMethodController@destroy` responde 409 «No se puede eliminar: el metodo
  tiene N transaccion(es)» y `active()` ordena por `name`, así que una fila `AUDIT-U9-*` de una
  corrida anterior se ordena antes que `Efectivo` y U5 la elige con `byType('cash')`.
- **L5** (RED/GREEN medido, base `odontosuite_audit` sembrada, app en :8000):
  - S1 RED: `@U1 recovery · the mailed link…` → «Test timeout of 30000ms exceeded». GREEN: 8.2 s,
    `verdict: ok`, `tokenApplied: true`, `tokenFieldPresent: false`.
  - S2 RED: `@U3 calendar · render for finanzas` → `dayColumnWidth` `null` en
    `toBeGreaterThanOrEqual(96)`. GREEN: pasa y publica `mobileWeekContract.asserted: false` con su
    motivo; el rol con datos mide columna 112 px y scroll 848/306 como la ola 7.
  - S3 RED: estado guardado sin `appointments` → la fila falla (TypeError fuera de
    `ensureFixtures`). GREEN: el estado se rechaza por el predicado, la sesión reconstruye
    (`fixtures.ok: true`, 3 citas) y la fila pasa.
  - S5 RED: `@U8 · capa 5` → «declared deviations measured: Expected >= 1», con `mismatches 0`,
    `deviations 0` y las dos `contentFacts` en `false`. GREEN: 28 endpoints, 0 desvíos, 0
    desviaciones y las dos hechos en `true`.
  - S6 RED: con una fila `AUDIT-U9-501PRUEBA` plantada al frente de `/payment-methods/active`, el
    color del catálogo era suyo y el `DELETE` de cierre respondía 409. GREEN: el catálogo publica
    `cash: Efectivo` y la fila excluida con su motivo, y el `DELETE` de la fila plantada responde
    200 (la sesión nunca le cargó dinero).
  - S4 GREEN: informe de sesión sobre evidencia anterior (sin las claves nuevas) → ver §Verificación.
  - Unidades: `vitest` completo 175 archivos / 1574 pruebas en verde (incluye
    `auditFixturesReuse` 7 y `auditTeardownRefusal` 3); ESLint 0, Prettier limpio.
- **L6** (entorno de la corrida, medido): la SPA lleva `VITE_APP_URL` en el bundle, así que la app
  tiene que escuchar en ese origen y puerto (`127.0.0.1:8000` por omisión de `.env`); servirla en
  otro puerto deja peticiones `net::ERR_CONNECTION_REFUSED` y convierte filas de producto en fallos
  del arnés. Además `php artisan serve` es de un solo proceso: con `PHP_CLI_SERVER_WORKERS=6` las
  ráfagas de la SPA dejan de perder peticiones («TypeError: Failed to fetch» en el login).
- **L7** (corrida completa, base `odontosuite_audit` recién sembrada, app en :8000 con
  `PHP_CLI_SERVER_WORKERS=6`, `AUDIT_SCOPE=all`, `VERIFY501B`): **425 passed / 4 failed / 2 skipped**
  en 48.6 min (antes: 361 / 10 / 60). Los cuatro fallos ya no son las seis causas: (a)
  `@U5 · listos para facturar` caía con `Cannot read properties of null (reading 'rowCount')` porque
  #452 hace del vacío un estado de la página (`[data-rtb-empty]`, sin tabla); corregido en
  `3f3230c0` y re-medido en aislado (pasa en 43.1 s y publica `steps.empty: {rows: 0, empty: true}`,
  igual que `@U5 · generar cotización`, 9.1 s); (b) los dos walkthroughs de caja (U5) y el walk de
  planes (U6) siguen en rojo por deuda propia de esas sondas, no por las seis causas:
  `hero-sin-sesion`/`bloqueo-contable`/`cobro-a1-efectivo` arrastran expectativas anteriores a
  #407/#410/#466 y dependen del estado de dinero de la base, y `@U6 · planes` agota sus 900 s tras
  fallar el paso que espera `[data-plan-submit]`. Se reportan al usuario; no se reescriben aquí
  porque el veredicto correcto de esas filas exige una decisión de producto.
- **L8** (corrida de cierre, `VERIFY501C`, base recién sembrada, app :8000): **429 passed / 0
  failed / 2 skipped en 32.7 min** (Playwright exit 0) y gate `ui-pass` **36/36**, 0 page errors, 40
  diálogos, 0 filas de contrato fallidas. Las sondas siguen registrando sus hallazgos de producto con
  veredicto (caja: la fila de cobro publica el precio del tipo de cita y no el saldo; planes: el alta
  no encuentra el submit tras la segunda línea) sin tumbar la fila, que es el contrato del instrumento.
- **L9** (evidencia): `RED501/` (u1 timeout, u3 `dayColumnWidth` null, u5 TypeError, u7 TypeError,
  u8 `deviations >= 1`), `GREEN501/` (u1, u3, u5 estado viejo, u8, u5 walk con fila `AUDIT-*`, u9
  unitario), `DIAG501/` (u5 rtb + walks, u6 aislado) y `VERIFY501B/` (corrida completa) bajo
  `.atl/qa-evidence/audit/ui/` (ignorado por git).
