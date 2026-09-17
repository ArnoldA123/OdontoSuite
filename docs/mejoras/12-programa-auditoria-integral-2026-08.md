# Plan #12 — Programa de auditoría integral de OdontoSuite V2 (agosto 2026)

> **Fecha**: 2026-08-05 (creado) · **Última revisión**: 2026-08-05 **Origen**:
> el proyecto acumula inconsistencias, bugs, errores, funcionalidad a medias y
> partes no funcionales. La cobertura completa no cabe en una sesión, así que
> este documento define **el método** (ejes, evidencia, criterios de salida y
> protocolo de issues) y **no** la lista cerrada de defectos. **Alcance**:
> auditoría y conversión a issues. No es un plan de corrección: cada corrección
> nace como issue y se ejecuta con su propio ciclo. **Revisión de referencia de
> los datos de este documento**: `3f5cc58` (árbol limpio, `main`).

---

## 1. Regla de evidencia (obligatoria, no negociable)

Este repositorio ya pagó el costo de afirmar sin medir: `AGENTS.md` §6 declaraba
que la suite pasaba en CI con MySQL y esa suite **nunca se había ejecutado**.
Todo hallazgo de este programa cumple:

| Regla                                    | Significado operativo                                                                                                                                                        |
| ---------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Sin comando, no hay hallazgo**         | Cada hallazgo cita el comando exacto ejecutado y su salida cruda (guardada en `.atl/qa-evidence/audit/`).                                                                    |
| **Etiqueta de origen**                   | `MEDIDO` (salida observada), `INFERIDO` (deducido de una lectura), `NO VERIFICADO` (hipótesis). Un total puede ser medido y su desglose inferido: se etiquetan por separado. |
| **Atado a una revisión**                 | Toda cifra se ata al commit donde se midió. Nunca se reutiliza como "actual".                                                                                                |
| **Prohibido el número volátil en prosa** | Si una cifra cambia con cada migración o cada push, el documento no la fija: cita el comando que la obtiene. Un número escrito deriva solo.                                  |
| **Corrección visible**                   | Si una medición refuta una afirmación previa (de este documento o de otro), no se borra en silencio: se marca la refutación.                                                 |
| **Causalidad, no correlación**           | Un fallo que aparece al mismo tiempo que un cambio no es causado por él. Se distingue defecto introducido por el candidato vs defecto preexistente en la base.               |

**Criterio de calidad de un guard**: un test, gate o verificación solo cuenta
como evidencia si **puede fallar**. Un gate enmascarado con `|| echo`, un
`grep -v ... || true` o un test sin aserciones no verifican nada (es el patrón
central de los issues #14, #15, #17 y del eje A11).

---

## 2. Ejes de auditoría

Once ejes (`A1`–`A11`). Cada uno es una **sesión de tamaño controlado** con
productos entregables y criterio de salida explícito. Ninguno se cierra con
prosa: se cierra con un artefacto.

### A1 — Línea base reproducible (obligatorio primero)

**Hipótesis**: hoy ningún número del proyecto es reproducible en un solo paso;
por eso la documentación deriva.

**Método**: script único que captura todos los signos vitales y escribe un
artefacto con fecha y revisión.

```bash
git rev-parse HEAD; git status --porcelain
php artisan route:list --json            > .atl/qa-evidence/audit/routes.json
php artisan test --testsuite=Unit 2>&1   | tail -30
pnpm lint:check -f json                  > .atl/qa-evidence/audit/eslint.json
pnpm format:check 2>&1                   | tail -20
vendor/bin/pint --test 2>&1              | tail -20
pnpm build 2>&1                          | tail -10
# Suite MySQL: requiere motor real — ver A2 y el hallazgo N1 (no existe docker-compose.yml)
php artisan test --configuration=phpunit.mysql.xml 2>&1 | tail -60
```

**Entregable**: `scripts/audit/baseline.sh` +
`.atl/qa-evidence/audit/baseline-<fecha>.md`. **Criterio de salida**: una sola
ejecución reproduce todas las cifras que el programa cite; ninguna cifra del
programa queda sin comando.

---

### A2 — Suite en MySQL real: la familia de fallos del issue #18

**Hipótesis**: los ~143 fallos restantes no son bugs independientes; se
concentran en pocas clases raíz (modo estricto de MySQL, contexto `$this`,
artefactos frontend ausentes en el runner, lecturas de archivo fuente).

**Método**: correr la suite con MySQL y **agrupar por clase de excepción**, no
por test. El issue #18 ya tiene un desglose medido en un run previo; este eje lo
vuelve a medir sobre el estado actual y por clase raíz.

**Entregable**: tabla de distribución (causa → tests afectados →
archivo:línea) + un issue por clase raíz, no por test. **Criterio de salida**:
toda falla del run tiene causa asignada y dueño. **Bloqueo conocido**: el
workaround documentado en `AGENTS.md` §8 (`docker compose up -d mysql`) **no es
ejecutable**: no existe `docker-compose.yml` en el repositorio. Medido en
`3f5cc58`. Ver backlog N1.

---

### A3 — Contratos de API (backend ↔ frontend)

**Hipótesis**: hay endpoints que devuelven una forma que ningún consumidor usa,
y consumidores que esperan campos que la API no entrega (envoltura
`{data, meta}`, filtros por sede, paginación, 404 vs 200 con lista vacía).

**Método en dos pasos**:

1. **Estático**: cruzar las rutas declaradas en `routes/api.php` con los sitios
   de consumo (`useApi().get/post/...`) y detectar rutas sin consumidor y
   llamadas sin ruta.
2. **En vivo**: con la app levantada y token de cada rol, recorrer cada ruta
   `GET` y validar `status` + claves de nivel superior del payload.

```bash
php artisan serve &
node scripts/audit/probe-api.mjs   # a crear: matriz rol × endpoint → status + claves
```

**Entregable**: matriz `rol × endpoint` + lista de desajustes. **Criterio de
salida**: cero rutas sin clasificar (con consumidor / sin consumidor / excluida
con motivo).

---

### A4 — Matriz de autorización real (RBAC)

**Hipótesis**: la tabla de roles por módulo de `AGENTS.md` §5 y el middleware
`role:` de `routes/api.php` no coinciden entre sí ni con lo que el frontend deja
ver (`AppLayout.navigation`, sin `meta.roles`).

**Método**: derivar la expectativa **del código** (middleware por ruta), loguear
un usuario demo por rol (`CREDENTIALS.md`) y comparar con la observación.
Incluye el ciclo `cash.session` y el multi-sede.

**Entregable**: matriz esperada vs observada, con las filas divergentes
aisladas. **Criterio de salida**: cada divergencia es un issue o una aceptación
explícita documentada.

---

### A5 — Funcionalidad a medias y no funcional

**Hipótesis**: existe trabajo intencionado y nunca escrito (el issue #19 ya
midió **7 ramas vacías**, el #20 **70 `catch` vacíos**), más código nunca
cableado ni consumido.

**Método**: cuatro barridos mecánicos, cada uno con su inventario y su triage
humano (implementar / borrar / documentar):

| Barrido           | Pregunta                                                             | Detección                                                                   |
| ----------------- | -------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| Nunca disparado   | ¿Evento sin `event(new ...)`?                                        | inventario `app/Events` vs sitios de dispatch                               |
| Nunca consumido   | ¿Listener, canal WS o endpoint sin consumidor?                       | cruce con `AppServiceProvider::boot`, `useWebSocketNotifications`, frontend |
| Nunca renderizado | ¿Componente `.vue` sin import?                                       | grep de imports + registro global de primitivas                             |
| Nunca alcanzado   | ¿Método público que devuelve `null`/vacío, `abort(501)`, rama vacía? | ESLint `no-empty` + revisión por item                                       |

**Nota de alcance**: `AGENTS.md` §6 afirma que `ReminderController` y
`ReminderTemplateController` son stubs vacíos 501 y que
`WaitingListController::update()/destroy()` también devuelven 501. Medido en
`3f5cc58`: `ReminderController` tiene 123 líneas con
`index/store/show/update/destroy/send` más `ReminderService` y FormRequests
tipados; `ReminderTemplateController` tiene 100 líneas con CRUD completo; ambas
están cableadas en `routes/api.php` (líneas 139 y 155-156).
`WaitingListController` **no existe** y no queda ninguna ruta `waiting` en
`routes/api.php`: fue eliminado deliberadamente por el slice 04
(BF-003/API-042), con guard en `StubsRemovedEndpointsTest`. La única aparición
de la cadena `501` en `app/Http/Controllers` es un comentario en
`PendingPaymentsController` que documenta el reemplazo de su propio stub. Ver
N3.

**Entregable**: inventario clasificado por barrido. **Criterio de salida**: cada
item está corregido, con issue, o aceptado por escrito.

---

### A6 — Integridad y portabilidad de datos

**Hipótesis**: la cadena de migraciones no es portable entre motores y los
seeders no respetan los contratos de `$fillable` (ya ocurrió una vez con
`SpecialtyRecordSeeder`, y el mayor bloque de fallos MySQL es
`Field '...' doesn't have a default value`, es decir modo estricto rechazando
inserts que SQLite acepta en silencio).

**Método**:

```bash
php artisan migrate:fresh --seed        # exit 0 esperado
php artisan migrate:fresh --seed --database=mysql
php artisan db:seed --class=<CadaSeeder> # contrato de campos vs $fillable
```

Más: columnas `NOT NULL` sin default, FKs sin índice, tablas con `deleted_at`
que no deberían, y cobertura del filtro multi-sede.

**Entregable**: esquema reproducible en ambos motores + lista de violaciones de
modo estricto. **Criterio de salida**: `migrate:fresh --seed` termina con exit 0
en los dos motores, o existe un issue por cada migración que lo impide.

---

### A7 — Runtime real en navegador (extiende `full-user-browser-audit-2026-08-05`)

**Hipótesis**: los tests estructurales no ven fallos de render. Ya ocurrió:
`QuotationCard.vue` lanzaba `ReferenceError` y la página `/quotations`
renderizaba **139 caracteres, cero tablas**, sin que ningún test lo notara.

**Método**: recorrido por módulo con navegador real, capturando por cada uno:
errores de consola, peticiones 4xx/5xx, longitud del contenido renderizado (el
truco que ya destapó el caso anterior) y captura PNG.

```bash
node scripts/audit/browser-audit.mjs --role=administrador --modules=todos
# evidencia → .atl/qa-evidence/audit/<modulo>/
```

**Estado de partida**: el change `full-user-browser-audit-2026-08-05` tiene 74
tareas hechas y 11 pendientes
(`openspec/changes/full-user-browser-audit-2026-08-05/tasks.md`). Este eje
**primero cierra esas 11**. **Entregable**: informe por módulo con evidencia
cruda. **Criterio de salida**: los 19 módulos de `resources/js/modules/`
recorridos y clasificados.

---

### A8 — Tiempo real (Reverb / WebSocket)

**Hipótesis**: hay eventos con consumidor en el cliente pero sin listener o sin
dispatch, y desajustes de payload entre lo emitido y lo consumido (el frontend
"se ve sano mientras deja de actualizarse", ya clasificado en el issue #20).

**Método**: mapa estático `dispatch → evento → canal → consumidor → composable`,
y luego prueba en vivo con dos sesiones (acción en una, aserción de
actualización en la otra) para cada evento cableado.

**Entregable**: tabla de cableado + resultado de la prueba en vivo por evento.
**Criterio de salida**: cada evento es cableado-verificado, huérfano declarado,
o issue.

---

### A9 — Deriva documental (extiende el issue #17)

**Hipótesis**: `AGENTS.md` (y `docs/`) afirman hechos que el código ya no
cumple, porque sus cifras no tienen guard.

**Método**: `scripts/audit/doc-drift.mjs` que extrae cada afirmación numérica de
`AGENTS.md` y la compara con el valor medido, fallando con el diff. Es decir:
**convertir la prosa en un guard que pueda fallar**.

**Entregable**: guard ejecutable + correcciones de los documentos + decisión por
cada afirmación volátil (medirla en lectura o eliminarla). **Criterio de
salida**: ninguna afirmación numérica de `AGENTS.md` sin fuente automática.

---

### A10 — Deuda estructural y duplicación

**Hipótesis**: hay lógica de negocio duplicada entre controllers y services,
`Request->validate()` inline donde ya existen FormRequests, N+1 sobre los
filtros de mayor uso, e índices ausentes en las columnas de filtrado por sede.

**Método**: revisión dirigida por evidencia de los ejes previos (no barrido
especulativo): primero los hotspots que A2/A3/A4 señalaron, después `explain`
sobre las consultas de filtrado, después los 3 FormRequests no migrables ya
documentados.

**Entregable**: lista de duplicaciones con archivo:línea y propuesta de
unificación. **Criterio de salida**: cada duplicación es issue con alcance de
refactor acotado, o aceptada con motivo.

---

### A11 — Auditoría de los guards (¿los tests pueden fallar?)

**Hipótesis**: parte de los tests estructurales (barridos por grep sobre el
código fuente) pasan siempre, incluso si el código se rompe. Un guard que no
puede fallar es el defecto que este repositorio lleva meses retirando.

**Método**: muestreo por mutación en un worktree aislado. Por cada guard
seleccionado: romper deliberadamente aquello que dice proteger, ejecutar el
test, comprobar que falla, restaurar. Evidencia: lista de guards que **pasaron
con el código roto**.

```bash
git worktree add ../audit-mutation HEAD
# por muestra: mutar → php artisan test --filter=<Guard> → registrar → restaurar
```

**Entregable**: lista de guards inefectivos con la mutación que los burló.
**Criterio de salida**: la muestra cubre los 19 tests de `tests/Unit/` de
barrido estructural.

---

## 3. Orden de ejecución y presupuesto

Una sesión por eje. El orden maximiza hallazgos tempranos y evita re-trabajo.

| Fase | Ejes                   | Por qué en este orden                                                                     |
| ---- | ---------------------- | ----------------------------------------------------------------------------------------- |
| 0    | **A1**                 | Sin línea base, todo hallazgo posterior es incomparable y toda cifra deriva.              |
| 1    | **A2**, **A4**, **A3** | Mayor rendimiento de defectos reales, medibles con comando, y desbloquean la suite en CI. |
| 2    | **A5**, **A6**         | Trabajo a medias e integridad de datos: la mayor parte son decisiones de producto.        |
| 3    | **A7**, **A8**         | Verificación en navegador; A7 continúa un change ya al 87% (74/85).                       |
| 4    | **A9**, **A11**        | Deriva documental y guards falsos: cierran el ciclo y evitan que la deuda vuelva.         |
| 5    | **A10**                | Refactor: solo con la evidencia de las fases anteriores.                                  |

**Presupuesto por sesión** (regla operativa): un eje completo, issues creados
dentro de la misma sesión, y ninguna corrección de más de 400 líneas de
revisión. Si un eje no cabe, se parte por barrido (A5) o por clase raíz (A2),
nunca se alarga la sesión.

---

## 4. Protocolo de creación de issues

### 4.1 Reglas

1. **Un issue por clase raíz**, no por síntoma. La suite no se arregla con 143
   issues; se arregla con los pocos patrones que los causan.
2. **Sin evidencia no hay issue.** Contexto = comando + salida cruda + revisión.
3. **Separar por naturaleza del trabajo**: corrección mecánica, decisión de
   producto, y borrado de código muerto son issues distintos aunque compartan
   regla de lint (así se hizo con #19 y #20).
4. **Preexistente ≠ descartado.** Se marca la causalidad: si el defecto existe
   en la base y no lo introdujo el cambio en curso, se declara y se decide
   aparte.
5. **Un tope por sesión**: entre 3 y 7 issues. Más cantidad significa que el
   triage no agrupó por clase raíz.
6. **Ningún issue se cierra por "aplicado"**: se cierra con la observación (run,
   salida o captura) que demuestra el efecto.

### 4.2 Etiquetas

Creadas el 2026-08-05 (16, total del repositorio: 25). El formulario
`.github/ISSUE_TEMPLATE/auditoria-defecto.yml` declara `auditoria-2026-08`; el
resto se aplica por issue según corresponda.

**Criticidad** (definición operativa, no adjetivo):

| Etiqueta     | Criterio                                                                    |
| ------------ | --------------------------------------------------------------------------- |
| `crit:alta`  | Produce confianza falsa o bloquea la verificación del sistema.              |
| `crit:media` | Reduce la capacidad de detección; causará fallos visibles si no se atiende. |
| `crit:baja`  | Consistencia, higiene o cosmético; no altera el comportamiento observable.  |

**Área** (con qué parte del sistema se relaciona la causa): `area:backend` ·
`area:frontend` · `area:ci` · `area:docs` · `area:data` · `area:realtime` ·
`area:tooling`

**Naturaleza**: `type:defecto` · `type:no-terminado` · `type:guard-falso` ·
`type:verificacion-ausente`

**Agrupación y programa**: `root-class` (síntomas con una única causa raíz) ·
`auditoria-2026-08` (hallazgo del plan #12)

La relación con issues concretas no se expresa con etiquetas dinámicas (GitHub
no las soporta): va en el campo **Relacionado con** del formulario, que es
obligatorio.

### 4.5 Formulario de entrada

`.github/ISSUE_TEMPLATE/auditoria-defecto.yml` es la autoridad de formato: 11
campos, 10 requeridos, y las dos confirmaciones obligatorias (la evidencia fue
ejecutada, no inferida; el criterio de aceptación es un comando o una
observación). Sin ese formulario no se publica una issue de auditoría.

**Nota**: un cuerpo creado por CLI no pasa por el renderizador del formulario;
debe materializarse con los mismos encabezados declarados, en el mismo orden.
Los cuerpos de #21, #22 y #23 se construyeron así.

### 4.3 Plantilla

```markdown
## Síntoma observable

Qué se ve, en una frase, sin adjetivos.

## Evidencia

Comando ejecutado, salida cruda, revisión (`<sha>`), artefacto en
`.atl/qa-evidence/audit/`. Etiqueta: MEDIDO | INFERIDO | NO VERIFICADO.

## Impacto

Efecto para el usuario o para la confiabilidad del proyecto (p. ej. "gate verde
que no verifica nada").

## Causa

Medida (archivo:línea) o "hipótesis a confirmar", declarado explícitamente.

## Alcance de la corrección

Archivos previstos. Fuera de alcance: lo que NO se toca.

## Criterio de aceptación

El comando exacto que debe salir en verde (o la observación que debe ocurrir).

## Causalidad

Introducido por `<cambio>` en `<sha>` | preexistente en la base.

## Esfuerzo y dependencias

S | M | L, y qué issue debe ir primero.
```

### 4.4 Triage sistémico

Antes de crear issues, agrupar todos los hallazgos de la sesión por **clase
raíz** (misma causa, distinto síntoma) y atacar la clase. Si dos hallazgos solo
comparten vecindad de archivo, no son la misma clase.

---

## 5. Backlog vivo

Estados: `nuevo` (medido, sin issue) · `issue#N` (registrado) · `cerrado`
(observado) · `aceptado` (decisión explícita de no actuar).

### 5.1 Issues ya registrados (no re-auditar)

| Issue | Asunto                                                                       | Estado                         |
| ----- | ---------------------------------------------------------------------------- | ------------------------------ |
| #13   | `Node` 20 vs `pnpm@11.5.0` (requiere ≥22.13)                                 | **Cerrado**, con run observado |
| #14   | ESLint: errores reales tras quitar la mitad de formato                       | Abierto                        |
| #15   | Cuatro de las cinco puertas de calidad no pueden fallar                      | Abierto                        |
| #17   | `AGENTS.md` documenta comportamiento de CI que nunca fue cierto              | Abierto                        |
| #18   | Suite MySQL: las fallas restantes (el `APP_KEY` explicaba 34)                | Abierto                        |
| #19   | Siete ramas vacías: la conducta se quiso y nunca se escribió                 | Abierto                        |
| #20   | Setenta `catch` vacíos: acciones de usuario y updates que fallan en silencio | Abierto                        |

### 5.2 Hallazgos medidos el 2026-08-05 (revisión `3f5cc58`, árbol limpio)

Todos `MEDIDO` salvo indicación. No reutilizar las cifras fuera de esta
revisión. El mapeo a las issues publicadas está en §5.4.

| ID  | Hallazgo                                                                                                                                                                                                                                                                                                                                                                                                    | Comando de reproducción                                                                                                                                                                                   | Eje    | Estado                                         |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ | ---------------------------------------------- |
| N1  | `AGENTS.md` §8 documenta `docker compose up -d mysql` como workaround de los fallos SQLite, pero **no existe `docker-compose.yml`**: el procedimiento no se puede ejecutar tal como está escrito                                                                                                                                                                                                            | `ls docker-compose.yml` → _No such file or directory_                                                                                                                                                     | A2     | nuevo                                          |
| N2  | `AGENTS.md` §4 dice 14 seeders activos y §12 dice 13; medido: 15 archivos, 14 sin contar `DatabaseSeeder`                                                                                                                                                                                                                                                                                                   | `ls database/seeders/*.php \| wc -l` → 15                                                                                                                                                                 | A9     | nuevo (familia #17)                            |
| N3  | `AGENTS.md` §6 declara tres stubs 501 que ya no existen: `ReminderController` (123 líneas, CRUD + `send`, cableado) y `ReminderTemplateController` (100 líneas, CRUD, cableado) están implementados; `WaitingListController` fue eliminado por diseño en el slice 04. La única coincidencia de `501` en los controllers es un comentario que documenta el reemplazo del stub de `PendingPaymentsController` | `grep -rn "501" app/Http/Controllers` → 1 coincidencia, un comentario; `wc -l app/Http/Controllers/Api/ReminderController.php` → 123; `ls app/Http/Controllers/Api/WaitingListController.php` → no existe | A5, A9 | nuevo                                          |
| N4  | `AGENTS.md` §4/§11 dicen 148 rutas; medido: 203 rutas totales, 190 con prefijo `api/`                                                                                                                                                                                                                                                                                                                       | `php artisan route:list --json \| php -r '...'` → 203 / 190                                                                                                                                               | A9     | nuevo (familia #17)                            |
| N5  | `AGENTS.md` §4 dice 36 controllers API; medido: 31 en `app/Http/Controllers/Api` y 33 en todo `app/Http/Controllers`                                                                                                                                                                                                                                                                                        | `ls app/Http/Controllers/Api/*.php \| wc -l` → 31                                                                                                                                                         | A9     | nuevo (familia #17)                            |
| N6  | `AGENTS.md` §4/§11 dicen 47 modelos; medido: 50                                                                                                                                                                                                                                                                                                                                                             | `ls app/Models/*.php \| wc -l` → 50                                                                                                                                                                       | A9     | nuevo (familia #17)                            |
| N7  | `AGENTS.md` §4 dice 98 migraciones; medido: 109                                                                                                                                                                                                                                                                                                                                                             | `ls database/migrations/*.php \| wc -l` → 109                                                                                                                                                             | A9     | nuevo (familia #17)                            |
| N8  | `AGENTS.md` §6 afirma "26 eventos huérfanos marcados con `@deprecated`"; medido: **0** archivos de `app/Events` contienen `@deprecated` (33 archivos, 10 listeners)                                                                                                                                                                                                                                         | `grep -rl "@deprecated" app/Events/*.php \| wc -l` → 0                                                                                                                                                    | A5, A9 | nuevo                                          |
| N9  | Las puertas de Pint y Prettier siguen enmascaradas: `\|\| echo "..."`, y el chequeo de sintaxis PHP usa `grep -v ... \|\| true`                                                                                                                                                                                                                                                                             | lectura de `.github/workflows/ci.yml` (steps 12-13)                                                                                                                                                       | A11    | ya cubierto por #15 (reconfirmado)             |
| N10 | Cobertura de verificación del frontend: **no hay runner de tests de UI**; `package.json` no declara vitest, jest ni playwright, y no existe `pnpm test` de frontend (la clave `test` invoca PHPUnit)                                                                                                                                                                                                        | `cat package.json`                                                                                                                                                                                        | A7     | nuevo                                          |
| N11 | `INFERIDO` — los tests de `tests/Feature/Ui/*` (16 archivos) validan el fuente por grep, no el render; el caso `QuotationCard.vue` es la prueba de que ese estilo de guard no ve fallos de runtime. Se confirma o refuta con A11 (mutación)                                                                                                                                                                 | `ls tests/Feature/Ui \| wc -l` → 16                                                                                                                                                                       | A11    | nuevo (hipótesis)                              |
| N12 | Contradicción interna en `AGENTS.md`: §6 lista "waiting list" entre lo funcional **y** en el mismo apartado la declara stub 501, cuando el slice 04 la eliminó a propósito (BF-003/API-042). Una de las dos afirmaciones es falsa en cualquier estado del código                                                                                                                                            | cruzar §6 con `routes/api.php` (sin rutas `waiting`) y `tests/Feature/Api/StubsRemovedEndpointsTest.php`                                                                                                  | A9     | nuevo                                          |
| N13 | `REFUTADO` (se deja constancia para que no se re-descubra): al inspeccionar con truncado de terminal, `StubsRemovedEndpointsTest.php` pareció tener métodos sin firma y docblock sin cerrar. `php -l` → `ok`, exit 0, y la lectura completa muestra el archivo correcto. No hay defecto                                                                                                                     | `php -l tests/Feature/Api/StubsRemovedEndpointsTest.php` → ok                                                                                                                                             | —      | cerrado (falso positivo de herramienta)        |
| N14 | `package-correct.json` está rastreado desde el commit inicial y **no es un backup**: es un `package.json` con dependencias actualizadas (vite ^7.1.9, vue ^3.5.22, laravel-vite-plugin ^2.0.1, jspdf ^3.0.3, `@vue/eslint-config-prettier` ^9.0.0, `axios` añadido) preparado y nunca aplicado. **No se borra**: contiene trabajo intencionado                                                              | `php -r` comparando las claves de `package.json` contra `package-correct.json`; `git log -1 -- package-correct.json` → d452270                                                                            | A10    | sin issue: decisión del dueño                  |
| N15 | `package-lock.json` rastreado desde el commit inicial (lockfileVersion 3, vite ^5.4.21) coexiste con `pnpm-lock.yaml` en un proyecto que exige pnpm. Riesgo: alguien ejecuta `npm install` y obtiene un árbol divergente                                                                                                                                                                                    | `git log -1 -- package-lock.json` → d452270; lectura del lock                                                                                                                                             | A6     | sin issue: hallazgo medido, decisión del dueño |
| N16 | 18 de los 19 archivos de `docs/mejoras/*.md` no pasan `pnpm exec prettier --check` (el plan #12 sí, por eso se formateó antes de commitear). Cuando el gate de Prettier se desenmascare (#15) los marcará a todos                                                                                                                                                                                           | `pnpm exec prettier --check "docs/mejoras/*.md"` → 18 archivos con avisos                                                                                                                                 | A9     | sin issue: familia #15                         |

### 5.3 Candidatos por eje aún no explorados

Los ejes A3, A4, A6, A8 y A10 no tienen todavía ningún hallazgo medido: su
primera sesión es su inventario. No se listan sospechas sin medición (regla §1).

### 5.4 Issues publicadas el 2026-08-05

| Issue | Título                                                                                            | Criticidad   | Área                      | Naturaleza                   |
| ----- | ------------------------------------------------------------------------------------------------- | ------------ | ------------------------- | ---------------------------- |
| #21   | `AGENTS.md` describe un proyecto que ya no existe (cifras vencidas + tres stubs 501 inexistentes) | `crit:alta`  | `area:docs`               | `type:defecto`, `root-class` |
| #22   | El workaround documentado para SQLite no es ejecutable: no existe `docker-compose.yml`            | `crit:alta`  | `area:tooling`, `area:ci` | `type:defecto`               |
| #23   | 19 módulos de frontend sin verificación ejecutable de UI                                          | `crit:media` | `area:frontend`           | `type:verificacion-ausente`  |

Las tres llevan `auditoria-2026-08` (declarada por el formulario). Mapeo desde
el backlog:

| Backlog                         | Issue                                                           |
| ------------------------------- | --------------------------------------------------------------- |
| N1                              | #22                                                             |
| N2, N3, N4, N5, N6, N7, N8, N12 | #21                                                             |
| N9                              | #15 (reconfirmado con un comentario de evidencia el 2026-08-05) |
| N10                             | #23                                                             |
| N11                             | sin issue: es una hipótesis y espera su medición (eje A11)      |
| N13                             | sin issue: refutado, se conserva como constancia                |
| N14, N15                        | sin issue: hallazgos medidos que esperan una decisión del dueño |
| N16                             | sin issue: pertenece a la familia de #15                        |

**Verificación de publicación**: cada issue se leyó de vuelta desde GitHub y
coincidió en título y cuerpo; cada cambio de etiqueta se hizo con lectura previa
de identidad y lectura posterior del conjunto completo, sin perder ninguna
etiqueta previa.

---

## 6. Guardarraíles del repositorio

| Guardarraíl                 | Detalle                                                                                                                                                                                                                    |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Rutas largas en Windows     | `git config core.longpaths true` es obligatorio en cada clon; sin él el tooling de review falla con `candidate-owner-preparation-failed`.                                                                                  |
| Suite en SQLite local       | Falla por DDL no soportado. Nunca se cita el número de fallos en prosa (deriva con cada migración): se obtiene con el comando de A1. El workaround documentado está roto (N1).                                             |
| `BROADCAST_CONNECTION=null` | Necesario en tests locales; `phpunit.xml` ya lo declara.                                                                                                                                                                   |
| Escrituras                  | Un solo escritor a la vez. Paralelizar requiere worktrees aislados y aprobación explícita.                                                                                                                                 |
| Carga de revisión           | Ninguna corrección supera las 400 líneas de revisión; si las supera, se encadena en slices.                                                                                                                                |
| Review nativo               | El candidato se revisa **mientras vive en el árbol de trabajo**: la proyección por rango comprometido falla de forma reproducida en este repositorio (defecto de tooling ya documentado en `odd/tasks/ci-conformance.md`). |
| Datos                       | Prohibido `migrate:fresh` sobre la base de la aplicación; la auditoría usa bases de prueba.                                                                                                                                |
| Commits                     | Prefijos semánticos, sin emojis, un work-unit por tarea, tests y docs junto al comportamiento.                                                                                                                             |

---

## 7. Definición de terminado del programa

El programa se cierra cuando:

1. Los 11 ejes tienen producto entregado y criterio de salida cumplido.
2. Todo hallazgo está en un issue con evidencia, o marcado `aceptado` con
   motivo.
3. El backlog §5 no tiene filas `nuevo` sin decisión.
4. Existe la línea base ejecutable de A1 y el guard de deriva documental de A9,
   de modo que la deuda no pueda reaparecer en silencio.
5. Los gates de CI pueden fallar de verdad (#15) y la suite MySQL está verde o
   cada fallo tiene issue e dueño (#18).

---

## 8. Changelog

- **2026-08-05** — Creación. Basado en la medición de `3f5cc58`: 203 rutas (190
  con prefijo `api/`), 50 modelos, 31 controllers en `app/Http/Controllers/Api`,
  109 migraciones, 15 archivos de seeder, 127 archivos `*Test.php`, 19 módulos
  de frontend. Backlog sembrado con N1–N11.
- **2026-08-05 (tarde)** — Issues #21, #22 y #23 publicadas con el formulario
  `.github/ISSUE_TEMPLATE/auditoria-defecto.yml` y 16 etiquetas nuevas; §4.2,
  §4.5 y §5.4 añadidas; N12–N16 registrados; el archivo se formateó con Prettier
  para pasar el gate.
