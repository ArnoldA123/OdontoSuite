# Credenciales de Acceso - OdontoSuite

Este documento contiene las credenciales de acceso reales para el sistema
OdontoSuite. Datos verificados contra la BD activa (MySQL, 2026-06-11).

## Contraseña por Defecto

**Todas las cuentas utilizan la contraseña:** `password123` **El campo "Usuario"
del login es el `username`** (no el email, no el nombre).

---

## Usuarios Reales

### Administradores

| Nombre              | Email                           | Username     | Rol           |
| ------------------- | ------------------------------- | ------------ | ------------- |
| Elizabet Cunia Cruz | elizabet+administrador@test.com | `elizabet`   | administrador |
| Ever Huamán Cruz    | ever+administrador@test.com     | `ever`       | administrador |
| Admin Test          | admin+administrador@test.com    | `admin_test` | administrador |

### Recepcionista

| Nombre             | Email                                | Username             | Rol           |
| ------------------ | ------------------------------------ | -------------------- | ------------- |
| Recepcionista Test | recepcionista+recepcionista@test.com | `recepcionista_test` | recepcionista |

### Odontólogos

| Nombre               | Email                          | Username          | Rol        |
| -------------------- | ------------------------------ | ----------------- | ---------- |
| Ever Huamán Cruz     | ever+odontologo@test.com       | `ever_odontologo` | odontologo |
| Brenda Tejada Alfaro | brenda+odontologo@test.com     | `brenda`          | odontologo |
| Odontologo Test      | odontologo+odontologo@test.com | `odontologo_test` | odontologo |

### Implantólogos

| Nombre            | Email                              | Username            | Rol          |
| ----------------- | ---------------------------------- | ------------------- | ------------ |
| Wilmer Valderrama | wilmer+implantologo@test.com       | `wilmer`            | implantologo |
| Implantologo Test | implantologo+implantologo@test.com | `implantologo_test` | implantologo |

### Técnicos Dentales

| Nombre           | Email                    | Username       | Rol            |
| ---------------- | ------------------------ | -------------- | -------------- |
| Sofia Villanueva | sofia+tecnico@test.com   | `sofia`        | tecnico_dental |
| Tecnico Test     | tecnico+tecnico@test.com | `tecnico_test` | tecnico_dental |

### Asistentes

| Nombre           | Email                        | Username         | Rol       |
| ---------------- | ---------------------------- | ---------------- | --------- |
| Azul Huamán Díaz | azul+asistente@test.com      | `azul`           | asistente |
| Asistente Test   | asistente+asistente@test.com | `asistente_test` | asistente |

### Finanzas

| Nombre             | Email                      | Username        | Rol      |
| ------------------ | -------------------------- | --------------- | -------- |
| Milagros Cochachin | milagros+finanzas@test.com | `milagros`      | finanzas |
| Finanzas Test      | finanzas+finanzas@test.com | `finanzas_test` | finanzas |

---

## Matriz de Permisos por Rol

> La definición normativa vive en `app/Authorization/Permissions.php` (catálogo
> `verbo.recurso` + matriz rol → permisos, #280). Esta tabla la refleja para
> operación; si difieren, manda el código. Recepción ya no puede completar
> consultas: ese endpoint escribe historia clínica. Desde #281 la custodia del
> paciente es explícita: recepción registra la llegada y anuncia, el profesional
> inicia la atención desde su cola, y cada traspaso queda con autor, hora y
> puerta. Desde #282 el acto clínico es una entidad propia
> (`clinical_encounters`): abrirlo, cerrarlo y anularlo son actos clínicos, y la
> atención de urgencia existe sin cita. Desde #295 el cierre descuenta stock
> real: un material sin stock suficiente responde 422 con el nombre del insumo y
> la transacción no escribe nada; el inventario se recibe por compra y se ajusta
> por conteo. Desde #287 el dinero tiene vocabulario cerrado: pago y devolución
> mueven la gaveta (sólo si el método es efectivo), condonación y ajuste son
> asientos de ledger que exigen autorización registrada de un administrador
> (`authorized_by` + motivo), y el descuento es un campo del cobro, no un tipo
> de transacción. El cargo que el cierre escribe en `patient_charges` y el cargo
> manual se leen en el estado de cuenta del paciente, que es del paciente y no
> de la sede.

| Funcionalidad                       | Administrador | Recepcionista | Odontólogo | Implantólogo | Técnico | Asistente | Finanzas |
| ----------------------------------- | ------------- | ------------- | ---------- | ------------ | ------- | --------- | -------- |
| **Pacientes**                       |
| Crear pacientes                     | ✅            | ✅            | ❌         | ❌           | ❌      | ❌        | ❌       |
| Ver pacientes                       | ✅            | ✅            | ✅         | ✅           | ✅      | ✅        | ✅       |
| Editar pacientes                    | ✅            | ✅            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Eliminar pacientes                  | ✅            | ❌            | ❌         | ❌           | ❌      | ❌        | ❌       |
| Exportar ficha de paciente          | ✅            | ✅            | ✅         | ✅           | ✅      | ❌        | ❌       |
| **Citas/Agenda**                    |
| Crear citas                         | ✅            | ✅            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Ver citas                           | ✅            | ✅            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Editar citas                        | ✅            | ✅            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Cancelar citas                      | ✅            | ✅            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Registrar llegada                   | ✅            | ✅            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Anunciar paciente                   | ✅            | ✅            | ❌         | ❌           | ❌      | ❌        | ❌       |
| Iniciar atención                    | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Escritorio Mi día                   | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Completar consulta (clínica)        | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Abrir atención de urgencia          | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Cerrar atención (sin cita)          | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Anular atención abierta             | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Ver atenciones clínicas             | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| **Reportes/BI**                     |
| Ver reportes                        | ✅            | ❌            | ❌         | ❌           | ❌      | ❌        | ✅       |
| Exportar datos                      | ✅            | ❌            | ❌         | ❌           | ❌      | ❌        | ✅       |
| **Finanzas/Caja**                   |
| Abrir/cerrar caja                   | ✅            | ✅            | ❌         | ❌           | ❌      | ❌        | ✅       |
| Registrar transacciones             | ✅            | ✅            | ❌         | ❌           | ❌      | ❌        | ✅       |
| Cobrar pagos pendientes             | ✅            | ✅            | ❌         | ❌           | ❌      | ❌        | ✅       |
| Registrar cargo manual              | ✅            | ✅            | ❌         | ❌           | ❌      | ❌        | ✅       |
| Ver estado de cuenta                | ✅            | ✅            | ❌         | ❌           | ❌      | ❌        | ✅       |
| Registrar depósito bancario         | ✅            | ✅            | ❌         | ❌           | ❌      | ❌        | ✅       |
| Ver presupuestos                    | ✅            | ✅            | ✅         | ✅           | ❌      | ❌        | ✅       |
| **Configuración**                   |
| Tipos de cita                       | ✅            | ❌            | ❌         | ❌           | ❌      | ❌        | ❌       |
| Ambientes/sillones                  | ✅            | ❌            | ❌         | ❌           | ❌      | ❌        | ❌       |
| Profesionales                       | ✅            | ❌            | ❌         | ❌           | ❌      | ❌        | ❌       |
| Catálogo de procedimientos          | ✅            | ❌            | ❌         | ❌           | ❌      | ❌        | ❌       |
| Especialidades                      | ✅            | ❌            | ❌         | ❌           | ❌      | ❌        | ❌       |
| **Sedes y dinero cerrado**          |
| Catálogo de sedes                   | ✅¹           | ❌            | ❌         | ❌           | ❌      | ❌        | ❌       |
| Tarifario por sede                  | ✅²           | ❌            | ❌         | ❌           | ❌      | ❌        | ❌       |
| Fijar bloqueo contable              | ✅²           | ❌            | ❌         | ❌           | ❌      | ❌        | ❌       |
| Autorizar edición antes del bloqueo | ✅            | ❌            | ❌         | ❌           | ❌      | ❌        | ❌       |
| **Clínica**                         |
| Historias clínicas                  | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Odontogramas                        | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Planes de tratamiento               | ✅            | ❌            | ✅         | ✅           | ✅      | ❌        | ❌       |
| Análisis IA                         | ✅            | ❌            | ✅         | ✅           | ❌      | ❌        | ❌       |
| Interconsultas                      | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| Favoritos de catálogo               | ❌            | ❌            | ✅         | ✅           | ✅      | ✅        | ❌       |
| **Inventario**                      |
| Ver inventario y alertas            | ✅            | ❌            | ✅         | ✅           | ✅      | ✅        | ✅       |
| Recibir y ajustar stock             | ✅            | ❌            | ❌         | ❌           | ❌      | ✅        | ✅       |

¹ Sólo el administrador **sin sede asignada** (gerencia) crea, renombra o retira
sedes. Un administrador con `branch_id` es administrador de esa sede: administra
su sede y su tarifario, y no toca otra.

² El tarifario y el bloqueo de una sede los fija su administrador o gerencia; la
fila de bloqueo global (`branch_id` nulo) es sólo de gerencia. Editar dinero con
fecha anterior al bloqueo exige `lock_override_reason` (mínimo 10 caracteres) y
queda en `audit_logs` como `accounting_lock_overridden`; sin él la respuesta es
403 con `accounting_lock_blocked`.

---

## Cómo loguearse

1. Ir a `/` en el navegador.
2. En el campo **"Usuario"** ingresar el `username` (ej: `elizabet`).
3. En el campo **"Contraseña"** ingresar `password123`.
4. Click en "Iniciar sesión".

**Nota**: el campo es `username`, NO el email. El campo `email` existe en la
tabla `users` pero no se usa para login (se usa
`Auth::attempt(['username' => ..., 'password' => ...])`).
