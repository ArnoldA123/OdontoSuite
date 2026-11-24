# Guía de uso por rol — OdontoSuite

> Para quien abre el sistema por primera vez y necesita recorrer el caso
> completo: paciente → cita → atención → cobro → cierre administrativo. Cada
> paso dice **quién lo hace**, **qué pantalla usa**, **qué botón presiona** y
> **qué necesita tener** para que el siguiente pueda continuar.

---

## 0. El flujo en una línea

```
Recepción                Profesional                    Caja / Finanzas
─────────                ───────────                    ───────────────
Paciente   →  Cita   →   Llegada   →  Atención   →     Cobro   →  Cierre
             │           │             │               │
             │           │             └─ firma        └─ cargo + saldo 0
             │           └─ "En espera" en Mi día
             └─ recordatorios 24/48/72 h
```

La pelota cambia de manos en dos momentos:

1. **Recepción → Profesional**: cuando recepción registra la llegada
   (`En espera`).
2. **Profesional → Caja**: cuando el profesional firma el cierre (`Completada` +
   cargo).

Todo lo demás (cotización, plan, laboratorio, continuidad) son ramas del mismo
episodio; ninguna es obligatoria para cerrar una consulta simple.

---

## 1. Los siete roles

| Rol              | Para qué sirve                     | Pantallas principales                                                                                                  | Puede                                                                                                                             | No puede                                                                           |
| ---------------- | ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| `administrador`  | Configurar el sistema y supervisar | Todo el menú, `Profesionales`, `Sucursales`, `Ambientes`, `Tipos de cita`, `Catálogo`, `Bloqueo contable`, `Auditoría` | Todo, incluido anular transacciones y autorizar descuentos grandes                                                                | Nada del producto; solo límites de sede si tiene `branch_id`                       |
| `recepcionista`  | Puerta de entrada y caja           | `Pacientes`, `Calendario`, `Pendientes`, `Caja`, `Laboratorio` (lectura), `Presupuestos` (lectura)                     | Crear/editar paciente, agendar, confirmar, registrar llegada, anunciar, cobrar, abrir/cerrar caja, depositar, agendar continuidad | Escribir historia clínica, evoluciones, planes, presupuestos ni cerrar la consulta |
| `odontologo`     | Acto clínico general               | `Mi día`, `Calendario`, `Historias clínicas`, `Planes`, `Presupuestos`, `Laboratorio`, `Análisis IA`                   | Iniciar y cerrar atención, firmar, escribir historia/evolución, planes, presupuestos, casos de laboratorio, IA                    | Caja, configuración, usuarios, auditoría, reportes financieros                     |
| `implantologo`   | Acto clínico de implantología      | Igual que odontólogo                                                                                                   | Igual que odontólogo                                                                                                              | Igual que odontólogo                                                               |
| `tecnico_dental` | Taller protésico y apoyo clínico   | `Mi día`, `Calendario`, `Planes`, `Laboratorio`, `Inventario` (lectura)                                                | Iniciar/cerrar atención, planes, casos de laboratorio; **no** lee la historia clínica ni exporta la ficha                         | Historia clínica, evoluciones, adjuntos, IA, presupuestos, caja, configuración     |
| `asistente`      | Apoyo al acto clínico              | `Mi día`, `Calendario`, `Historias clínicas`, `Laboratorio`, `Inventario`                                              | Leer/escribir historia y evolución, adjuntos, iniciar/cerrar técnicamente, gestionar inventario                                   | Firmar por el profesional, planes, presupuestos, IA, caja, configuración           |
| `finanzas`       | Dinero y reportes                  | `Dashboard`, `Pendientes`, `Presupuestos`, `Caja`, `Business Intelligence`, `Inventario`                               | Cobrar, caja, depósitos, estado de cuenta, presupuestos, reportes, exportar, inventario                                           | Agenda clínica, historia clínica, planes, IA, usuarios, configuración              |

> **La firma no se delega.** El cierre lo firma el profesional **asignado a la
> cita** con su propia contraseña. Aunque otro usuario tenga el permiso de
> cerrar, el sistema responde 403 si no es el profesional del acto.
>
> **Mi día es una cola personal.** Un administrador puede abrirla porque también
> tiene permisos clínicos, pero solo verá las citas asignadas a **su** usuario;
> si no atiende pacientes, la verá vacía. No es una vista de toda la clínica.

---

## 2. Antes de la primera cita: datos maestros

Sin estos datos, el alta de cita o el cierre fallan aunque el usuario tenga el
rol correcto. Los carga **administración** (`administrador` de grupo, sin sede).

| #   | Dato                                                            | Dónde                                 | Mínimo para probar                                                        |
| --- | --------------------------------------------------------------- | ------------------------------------- | ------------------------------------------------------------------------- |
| 1   | Sede activa                                                     | `Sucursales`                          | 1 sede                                                                    |
| 2   | Ambiente activo                                                 | `Ambientes`                           | 1 ambiente                                                                |
| 3   | Profesional activo con rol clínico                              | `Profesionales`                       | 1 odontólogo                                                              |
| 4   | Tipo de cita activo con duración y precio                       | `Tipos de cita`                       | 1 tipo (ej. Consulta General, 30 min, S/ 50)                              |
| 5   | Catálogo de procedimientos activo, con precio y sitio si aplica | `Catálogo de procedimientos`          | Opcional para una consulta; obligatorio si el acto ejecuta procedimientos |
| 6   | Métodos de pago activos, con familia y referencia               | `Métodos de pago`                     | Efectivo y, si se usa, Yape/tarjeta con su referencia                     |
| 7   | Catálogo CIE-10                                                 | Sembrado con `DiagnosisCatalogSeeder` | Al menos 1 diagnóstico activo para firmar                                 |
| 8   | Piezas y superficies dentales                                   | Sembrado                              | Necesario para odontograma o procedimientos de sitio                      |
| 9   | Paciente                                                        | `Pacientes`                           | 1 paciente                                                                |
| 10  | Opcional: convenios, tarifario por sede, bloqueo contable       | `Catálogo` / `Bloqueo contable`       | No bloquea una consulta simple                                            |

Si la base está vacía en un entorno de demostración:

```bash
php artisan migrate --seed
```

Las credenciales demo están en `CREDENTIALS.md`; todos los usuarios usan la
contraseña `password123` y el login es por **usuario** (`username`), no por
email.

---

## 3. Qué debe tener cada usuario

Un usuario no queda habilitado solo por tener un rol: necesita además estar
**activo** y tener el **alcance de sede** correcto.

| Requisito              | Dónde se ve                            | Efecto                                                                                                                       |
| ---------------------- | -------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Usuario activo         | `Profesionales` → ficha → Estado       | Un usuario inactivo no puede ser asignado a una cita                                                                         |
| Rol correcto           | Ficha del usuario                      | Define todos los permisos (`permission:verbo.recurso`)                                                                       |
| Sede (`branch_id`)     | Ficha del usuario                      | **Sin sede** = alcance de grupo (ve todas). **Con sede** = solo esa sede para agenda, ambientes, caja, personal e inventario |
| Especialidad           | Ficha del profesional → especialidades | Se muestra en la agenda y filtra tipos de cita                                                                               |
| Licencia / colegiatura | Ficha del profesional                  | Sale impresa en el registro firmado de la atención                                                                           |
| Contraseña             | Login                                  | Es la firma electrónica simple del cierre: el profesional la reingresa al firmar                                             |
| Caja abierta           | `Caja`                                 | Sin una sesión propia abierta no se puede cobrar (ni en efectivo ni con tarjeta)                                             |

### Recepción

- Rol `recepcionista`, activo, con sede si solo opera una.
- No necesita licencia ni especialidad.
- Necesita que existan sede, ambientes, profesionales y tipos de cita.

### Profesional (odontólogo / implantólogo)

- Rol clínico, activo, con sede si solo opera una.
- Especialidad y licencia cargadas (la licencia aparece en el documento
  firmado).
- Contraseña propia: la usará en cada cierre.
- Para ver su cola: `Mi día` exige `view.my_day` y que la cita esté asignada a
  **él**.

### Técnico dental

- Rol `tecnico_dental`, activo.
- Recibe los casos del laboratorio y avanza sus fases.
- **No** lee la historia clínica: su insumo es el caso protésico y el plan.

### Asistente

- Rol `asistente`, activo.
- Puede documentar y apoyar, pero **el cierre lo firma el profesional**.
- No escribe planes, presupuestos ni análisis de IA.

### Finanzas

- Rol `finanzas`, activo.
- Ve caja, pacientes, presupuestos, BI, inventario y reportes.
- No ve la agenda clínica ni la historia clínica.

### Administración

- Rol `administrador`.
- Sin sede: administra todo el grupo (usuarios, sedes, catálogo, bloqueo
  contable).
- Con sede: administra su sede; no puede crear/editar el catálogo de sedes.

---

## 4. El episodio completo, paso a paso

### Paso 1 — Recepción: crear al paciente

**Pantalla:** `Pacientes` → **Nuevo paciente**.

**Obligatorio:** nombres y apellidos. El resto es opcional, pero conviene
cargarlo:

- Documento (único), email (único), teléfono (único).
- Fecha de nacimiento (anterior a hoy).
- Sexo, dirección, contacto de emergencia.
- Antecedentes médicos, alergias y notas.

**Resultado:** el paciente queda disponible para agendar y para su ficha
clínica. La ficha es **compartida entre sedes** (ADR-0010).

> Si el documento, email o teléfono ya existen, el alta responde 422 y señala el
> campo. Búsquelo antes de crearlo para no duplicar.

### Paso 2 — Recepción: agendar la cita

**Pantalla:** `Calendario` → **Nueva cita** (o un hueco libre del tablero).

**Obligatorio:**

| Campo        | Regla                          |
| ------------ | ------------------------------ |
| Paciente     | Debe existir                   |
| Profesional  | Activo y con rol clínico       |
| Ambiente     | Activo                         |
| Tipo de cita | Activo; propone la duración    |
| Fecha y hora | Al menos 1 minuto en el futuro |
| Duración     | 15 a 480 minutos               |

**Opcional:** procedimiento del catálogo y notas.

**Reglas que debe conocer:**

- El sistema **no permite solapar** al mismo profesional ni al mismo ambiente.
  Si dos recepcionistas intentan el mismo hueco, la segunda recibe
  `422 slot_taken` y la cita no se guarda.
- La cita nace `Programada` (`scheduled`). Se puede **Confirmar** antes de la
  llegada, pero no es obligatorio: el check-in acepta `Programada` o
  `Confirmada`.
- Se agendan recordatorios a 24/48/72 horas. Al cancelar, los pendientes se
  cancelan y el aviso lo dice.
- Para **cancelar** se exige un motivo. `No asistió` y `Reagendada` son
  desenlaces terminales.
- **Reagendar** mueve fecha, hora, duración, profesional o ambiente y vuelve a
  validar el conflicto.
- **Eliminar** una cita la borra lógicamente y **libera el horario**.

**Resultado:** la cita aparece en la agenda del día y en `Mi día` del
profesional asignado, dentro de **Próximos** (si es de hoy).

### Paso 3 — Recepción: llegada y anuncio

**Pantalla:** `Calendario` → abrir la cita → tarjeta **Ahora**.

1. Si está `Programada`, presione **Confirmar**.
2. Presione **Registrar llegada**.
3. Opcional: **Anunciar**.

**Qué cambia:**

- La cita pasa a **En espera** (`arrived`) y registra `checked_in_at`, quién la
  registró y la puerta (`reception` o `professional`).
- **Anunciar** no cambia el estado: envía el aviso «paciente listo» al canal
  privado del profesional y a su campana.
- El profesional ya la ve en `Mi día` → **En espera**, con el botón **Iniciar
  atención**.

> Si el profesional no la ve, revise: (a) que la cita esté asignada a él, (b)
> que sea de hoy, (c) que esté `En espera` o `En consulta`.

### Paso 4 — Profesional: iniciar la atención

**Pantalla:** `Mi día`.

- **En espera**: pacientes que recepción ya registró.
- **Atendiendo**: consultas en curso.
- **Próximos**: citas de hoy que aún no llegan.
- **Laboratorio listo**: casos que el taller devolvió.

Presione **Iniciar atención** en la fila del paciente.

**Qué cambia:**

- La cita pasa a **En consulta** (`in_consultation`).
- Se abre la **atención clínica** (`clinical_encounter`) del acto, asociada a la
  cita y al paciente. Una cita tiene como máximo una atención abierta.
- El asistente de documentación se abre con **Continuar consulta** (o desde la
  cita en `Calendario`); ahí se elige el modo y se registra el acto.

**Alternativa sin cita:** una urgencia se abre con `POST /api/encounters` desde
el escritorio clínico; no inventa una fila en la agenda.

### Paso 5 — Profesional: documentar la consulta

**Pantalla:** `Mi día` → **Continuar consulta** (o la cita en `Calendario`).

El asistente tiene siete pasos:

| Paso               | Qué se registra                                                                                                       | ¿Obligatorio?                                                           |
| ------------------ | --------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| **Modo**           | Consulta/Evaluación, Ejecutar procedimiento o Avanzar plan existente                                                  | Sí                                                                      |
| **Evolución**      | Motivo de consulta, SOAP (subjetivo, objetivo, análisis, plan), diagnósticos CIE-10, odontograma, signos y mediciones | Sí: motivo, SOAP y ≥ 1 diagnóstico CIE-10                               |
| **Procedimientos** | Procedimientos realizados; líneas del plan con precio y cantidad                                                      | No                                                                      |
| **Materiales**     | Insumos consumidos                                                                                                    | Solo si el procedimiento declara recetario; el almacén debe tener stock |
| **Adjuntos**       | Archivos clínicos (máx. 10 MB por archivo)                                                                            | No                                                                      |
| **Próxima cita**   | Conducta de continuidad (fecha preferida)                                                                             | No                                                                      |
| **Cierre y firma** | Contraseña del profesional                                                                                            | Sí                                                                      |

**Reglas del modo:**

- **Consulta/Evaluación**: no ejecuta procedimientos; puede dejar un plan
  propuesto.
- **Ejecutar procedimiento**: crea un plan con un ítem ejecutado y completa la
  cita.
- **Avanzar plan existente**: descuenta los ítems del plan que se atienden hoy.

**Reglas de diagnóstico y materiales:**

- El diagnóstico se elige del catálogo CIE-10 activo; no se escribe a mano.
- Si el procedimiento declara insumos y no hay stock suficiente, el cierre
  responde 422 con el material y la cantidad, y **no escribe nada**.
- El plan se puede crear o actualizar en el mismo acto; el estado del ítem se
  deriva de lo consumido.

### Paso 6 — Profesional: firmar y cerrar

**Pantalla:** paso **Cierre y firma** → botón **✓ Completar consulta**.

**Requisitos exactos (NTS 139):**

1. Motivo de consulta escrito.
2. Al menos un diagnóstico CIE-10 activo.
3. Evolución SOAP completa.
4. Contraseña del profesional asignado a la cita.

**Qué escribe el cierre, en una sola transacción:**

- La evolución clínica y los diagnósticos (con copia congelada del CIE-10).
- El odontograma, si se registró.
- El plan de tratamiento y/o sus consumos.
- Los materiales y sus movimientos de stock.
- Los adjuntos.
- La **atención** queda `closed`, con `final_amount`, motivo, firma, hash de
  integridad y `closed_at`.
- La **cita** se proyecta: `completed`, `completed_at`, `final_amount` y
  `consultation_mode`.
- Un **cargo** en el libro del paciente (`patient_charges`) por el importe del
  acto, en la misma transacción.
- Un **recall** y/o una **solicitud de continuidad**, si se dejaron.
- Auditoría de lectura y firma.

**Cómo se calcula el importe que se cobrará:**

1. Si hay un plan con ítems ejecutados, suma sus `total_cost`.
2. Si no, usa el precio del **tipo de cita**.

**Resultado:** la cita deja de estar en `Mi día` y aparece en
`Listos para facturar` y en `Caja → Cobros` si el saldo es mayor a 0.

> La historia clínica es **inmutable**. Un error no se edita ni se borra: se
> corrige con un **addendum** firmado, con motivo. Las lecturas quedan
> auditadas.

### Paso 7 — Caja: cobrar

**Pantalla:** `Caja`.

**Antes de cobrar:** abra su caja con **Abrir caja** e indique el monto inicial.
Cada usuario de caja tiene su propia sesión; sin sesión abierta, cualquier cobro
responde 422 «No hay una sesión de caja abierta».

**Dónde está la deuda:**

- `Calendario` → **Listos para facturar**: muestra monto, pagado y saldo.
- `Caja` → **Cobros**: la misma población y el mismo saldo (desde esta versión).
  La tabla muestra **Monto / Pagado / Saldo**.

**Cómo cobrar:**

1. En `Cobros`, presione **Cobrar** en la fila.
2. El modal abre con el saldo como importe propuesto.
3. Elija el método de pago.
4. Si el método exige referencia (Yape, tarjeta, transferencia), escríbala.
5. Ajuste el importe si es un **pago parcial**.
6. Confirme.

**Qué pasa con el dinero:**

- El pago queda en `transactions` y en el estado de cuenta del paciente.
- Solo los métodos de **efectivo** escriben un movimiento en la gaveta.
- Tarjeta, transferencia y billetera **no mueven la gaveta**, pero sí reducen el
  saldo.
- El saldo se recalcula: `total del acto − pagos completados`.
- Cuando llega a 0, la cita sale de `Listos para facturar` y de `Cobros`.
- Un pago parcial deja la fila con el saldo restante.
- La **devolución** exige autorización de administrador y motivo; la
  **condonación** también. La anulación de una transacción es solo de
  administración.

**Al cerrar el turno:**

1. **Cerrar caja** con el efectivo contado.
2. El sistema muestra esperado, contado y diferencia.
3. Registre **depósitos** con referencia y fecha si retira efectivo.
4. Revise **Cobros**, **Transacciones**, **Movimientos**, **Historial** y
   **Reportes**.

**Estado de cuenta del paciente:** ficha del paciente → pestaña **Cuenta**
(roles de caja). Muestra período, saldo de apertura y cierre, cargos, pagos,
devoluciones, condonaciones, ajustes e imputaciones.

### Paso 8 — Finanzas: conciliar y reportar

**Pantalla:** `Business Intelligence`, `Caja` y `Pacientes`.

- Reportes de período, ingresos, citas, pacientes, profesionales y utilización.
- Corte por sede; el dinero sin cajón aparece como «Sin sede».
- Conciliación del día: efectivo, electrónico, depósitos y pendiente de
  depositar.
- Exportación CSV/Excel/PDF.
- Bloqueo contable: si el período está cerrado, un cobro con fecha bloqueada
  responde 403; administración puede reintentar con motivo de autorización.
- Descuentos: finanzas aplica descuentos; los grandes los autoriza
  administración.

### Paso 9 — Recepción: continuidad (para que el paciente vuelva)

**Pantalla:** `Pendientes`.

Tres secciones:

- **Recalls vencidos**: controles de 3/6/12 meses sin cita.
- **Solicitudes pendientes**: conductas que el profesional dejó al cerrar.
- **Esperando respuesta**: presupuestos enviados y planes propuestos.

**Qué hace recepción:**

1. **Agendar**: abre el alta con la fecha preferida y crea la cita validando
   profesional, ambiente y conflictos.
2. **Descartar** (solo solicitudes de continuidad): exige motivo.
3. Un recall sale de la cola cuando se agenda o se completa.

**Desde el cierre:** el profesional también puede dejar un **recall** (intervalo
y motivo) o una **próxima cita** como conducta. El sistema calcula el
vencimiento; no inventa la cita.

### Paso 10 — Laboratorio protésico (si aplica)

**Pantalla:** `Laboratorio` (`/lab-cases`) o ficha del paciente → pestaña
**Laboratorio**.

**Quién escribe:** administración, odontólogo, implantólogo y técnico dental.
Recepción solo lee.

**Datos del caso:** paciente, pieza, trabajo, material, fecha de envío, fecha
prometida, profesional que prescribe y técnico asignado.

**Fases (solo avanzan):**

```
in_preparation → in_laboratory → try_in → delivered → placed
```

- No se puede retroceder ni saltar hacia atrás.
- Al entrar en **delivered**, el profesional recibe el aviso y el caso aparece
  en `Mi día` → **Laboratorio listo**.
- **placed** cierra el caso y ya no se edita.
- El técnico solo edita sus casos; administración y los clínicos pueden editar
  los de su sede.

### Paso 11 — Presupuesto y plan de tratamiento (rama comercial)

**Quién escribe presupuestos:** administración, finanzas, odontólogo e
implantólogo. Recepción **lee**.

**Flujo:**

1. El profesional crea el **plan** en `Planes de tratamiento` (ítems con
   procedimiento, sitio si aplica, precio y cantidad).
2. Desde `Presupuestos` o desde `Listos para facturar` → **Generar cotización**.
3. La cotización nace en **Borrador**.
4. **Enviar** la pasa a **Enviado**; recién entonces aparece en `Pendientes` →
   **Esperando respuesta**.
5. El paciente decide: **Aprobar** o **Rechazar**.
6. Al aprobar, la versión del plan queda fija: cambios posteriores abren una
   versión nueva.
7. El presupuesto emitido conserva el precio congelado y no se recalcula si
   cambia el tarifario.
8. La descarga del PDF marca **Visto**.
9. Un presupuesto vencido se marca `expired` por el comando diario.

**Reglas:** el IGV se descompone del precio (no se suma);
`subtotal + IGV = total`; los estados `approved`, `rejected` y `expired` no se
editan.

### Paso 12 — Análisis de IA (opcional)

**Quién:** administración, odontólogo e implantólogo. El técnico dental recibe
403 en todo el módulo.

1. Suba una imagen desde `Análisis IA`.
2. Declare que informó al paciente (aviso de apoyo a la decisión).
3. El análisis se guarda como **apoyo**, no como diagnóstico.
4. Revíselo y decida.
5. Para que entre al registro, **cítelo** en una evolución o en un plan.
6. Se anula con motivo (`void`), nunca se borra; conserva la traza de revisión.

### Fin del episodio

Un caso queda administrativamente cerrado cuando:

- La cita está `completed`.
- El cargo del acto está pagado (saldo 0) y la caja del turno está conciliada.
- Si hay plan: todos los ítems no cancelados están consumidos.
- Si hay laboratorio: el caso está `placed`.
- Si hay continuidad: la próxima cita o el recall quedó agendado.

---

## 5. Matriz rápida: quién puede hacer qué

| Acción                      | Admin | Recepción | Odontólogo | Implantólogo | Técnico | Asistente | Finanzas |
| --------------------------- | ----- | --------- | ---------- | ------------ | ------- | --------- | -------- |
| Crear paciente              | Sí    | Sí        | —          | —            | —       | —         | —        |
| Editar paciente             | Sí    | Sí        | Sí         | Sí           | Sí      | Sí        | —        |
| Crear/editar cita           | Sí    | Sí        | Sí         | Sí           | Sí      | Sí        | —        |
| Confirmar / llegada         | Sí    | Sí        | Sí         | Sí           | Sí      | Sí        | —        |
| Anunciar paciente           | Sí    | Sí        | —          | —            | —       | —         | —        |
| Iniciar atención            | Sí    | —         | Sí         | Sí           | Sí      | Sí        | —        |
| Cerrar y firmar             | Sí\*  | —         | Sí         | Sí           | Sí\*    | Sí\*      | —        |
| Leer historia clínica       | Sí    | —         | Sí         | Sí           | —       | Sí        | —        |
| Escribir evolución          | Sí    | —         | Sí         | Sí           | —       | Sí        | —        |
| Crear plan                  | Sí    | —         | Sí         | Sí           | Sí      | —         | —        |
| Crear presupuesto           | Sí    | —         | Sí         | Sí           | —       | —         | Sí       |
| Leer presupuesto            | Sí    | Sí        | Sí         | Sí           | —       | —         | Sí       |
| Cobrar / caja               | Sí    | Sí        | —          | —            | —       | —         | Sí       |
| Anular transacción          | Sí    | —         | —          | —            | —       | —         | —        |
| Estado de cuenta            | Sí    | Sí        | —          | —            | —       | —         | Sí       |
| Laboratorio (escribir)      | Sí    | —         | Sí         | Sí           | Sí      | —         | —        |
| Laboratorio (leer)          | Sí    | Sí        | Sí         | Sí           | Sí      | Sí        | —        |
| Continuidad (gestionar)     | Sí    | Sí        | —          | —            | —       | —         | —        |
| IA                          | Sí    | —         | Sí         | Sí           | —       | —         | —        |
| BI / reportes               | Sí    | —         | —          | —            | —       | —         | Sí       |
| Usuarios / catálogo / sedes | Sí    | —         | —          | —            | —       | —         | —        |
| Bloqueo contable            | Sí    | —         | —          | —            | —       | —         | —        |

\* El cierre siempre exige la contraseña del **profesional asignado a la cita**.
Otro usuario con el permiso no puede firmar por él.

---

## 6. Errores frecuentes y qué significan

| Mensaje / código                                           | Causa                                                      | Qué hacer                                                      |
| ---------------------------------------------------------- | ---------------------------------------------------------- | -------------------------------------------------------------- |
| `422 slot_taken`                                           | Otra cita tomó el profesional o el ambiente en ese horario | Elegir otro hueco o reasignar                                  |
| «La cita debe empezar al menos un minuto después de ahora» | Hora pasada                                                | Elegir una hora futura; el alta ya propone la siguiente válida |
| 403 al abrir `Mi día`                                      | El rol no es clínico (`view.my_day`)                       | Usar una cuenta clínica; la página ahora lo explica sin error  |
| 422 «No hay una sesión de caja abierta»                    | Se intenta cobrar sin abrir caja                           | `Caja` → Abrir caja                                            |
| 422 «El método de pago X exige un número de referencia»    | Método con referencia obligatoria                          | Escribir el número de operación                                |
| 403 `accounting_lock_blocked`                              | Fecha contable dentro de un período cerrado                | Pedir autorización a administración y reintentar con motivo    |
| 422 «Stock insuficiente para…»                             | Material sin stock                                         | Reponer en `Inventario` o ajustar la receta                    |
| 422 firma inválida / no es el profesional                  | Contraseña incorrecta o usuario distinto del asignado      | El profesional asignado firma con su contraseña                |
| 403 de sede                                                | El recurso es de otra sede                                 | Cambiar de sede o pedir alcance de grupo                       |
| 403 en historia clínica                                    | El técnico dental no la lee (ADR-0016)                     | Usar el caso de laboratorio y el plan                          |
| «Sin respuestas pendientes» en `Pendientes`                | No hay presupuestos enviados, planes propuestos ni recalls | Enviar el presupuesto o crear el plan/recall                   |

---

## 7. Ruta mínima para probar el sistema completo

Con una base sembrada y una cuenta por rol:

1. **Administrador**: verifique sede, ambiente, odontólogo activo, tipo de cita
   con precio, método de pago y CIE-10.
2. **Recepción**: cree un paciente → agende una cita para hoy con ese odontólogo
   → **Confirmar** → **Registrar llegada** → **Anunciar**.
3. **Odontólogo**: `Mi día` → **Iniciar atención** → **Continuar consulta** →
   Modo, motivo, SOAP, diagnóstico CIE-10 → **Cierre y firma** → contraseña →
   **✓ Completar consulta**.
4. **Caja (recepción o finanzas)**: **Abrir caja** → `Cobros` → **Cobrar** →
   método e importe → confirmar. Verifique que la fila desaparece con saldo 0.
5. **Recepción**: `Pendientes` → **Agendar** la próxima cita o el recall.
6. **Finanzas**: `Business Intelligence` y `Caja → Reportes` para conciliar.
7. **Administrador**: revise el registro firmado en la atención y el estado de
   cuenta del paciente.

---

## 8. Nota de esta versión

- `Listos para facturar` y `Caja → Cobros` comparten población e importe: el
  saldo que se ve es el saldo que se cobra.
- El alta de cita propone una hora futura y bloquea las casillas que ya pasaron.
- `Mi día` explica cuando el rol no es clínico, en vez de mostrar un 403.
- `Pendientes` ya no cachea 2 minutos: un presupuesto enviado aparece en la
  siguiente lectura, incluso con **Actualizar**.
