# Auditoría clínica U7 — #424, #425, #426, #427 y #428

Cinco hallazgos de la auditoría UI U7 (#370, plan #13) sobre historia clínica,
análisis de IA y registros de especialidad. Rama
`fix/audit-u7-clinic-424-428`, worktree `.worktrees/u7-clinic-424-428`, base
`main` @ `ff382b5d`. Un commit de unidad por issue.

## Specs

S1. #424: «Cada tab vacía de `/medical-records` ofrece la acción que la
resuelve, como sus hermanas.» Medido: `data-state="no-record-evolutions"` y
`data-state="empty-stats"` declaran qué falta y no ofrecen control
(`1 estado(s) vacío(s), 0 acción(es)`), mientras Historia general y Adjuntos sí
lo hacen.

S2. #424: «La acción de Estadísticas depende del estado real del expediente»:
«Registrar evolución» cuando hay una historia seleccionada y «Crear historia»
cuando el paciente no tiene ninguna. Las estadísticas son del paciente, no de
una historia concreta, y eso no cambia.

S3. #425: «El envío vacío se rechaza con el motivo en el formulario (o en su
campo)», como el alta de historia, el adjunto y el addendum. `POST
/api/medical-records/{id}/evolutions` responde `422` cuando la evolución no
registra ningún bloque SOAP, indicación ni medición, y no escribe fila. Medido
hoy: `201` y una tarjeta sin contenido en el timeline, sobre un acto que #292 ya
no permite corregir ni borrar.

S4. #425: el formulario «Nueva evolución» rechaza ese envío vacío antes de
llegar al API y pinta el motivo en su bloque de error.

S5. #426: el paso de imagen del alta de IA usa el canal de error en línea para
los dos rechazos (tipo de archivo y tamaño), sin `alert()` nativo, sin retener
la vista previa del archivo rechazado y con el selector de archivo disponible
otra vez; la X de la vista previa tiene nombre accesible. Medido hoy:
`errores en línea []; diálogos nativos ["Solo se permiten archivos de imagen"]`
y `vista previa retenida=true; selector de archivo disponible tras el
rechazo=0; X=1`.

S6. #427: un alta cuya fila queda `failed` no responde «Análisis completado
exitosamente», publica un motivo legible para el usuario (`failure_reason`, sin
volcar `raw_response`) y el SPA no cierra el sheet ni abre el detalle como si
hubiera resultado. Medido hoy: `POST /api/ai-analysis/upload-and-analyze → 201`
con mensaje de éxito y `status: "failed"`.

S7. #427: el detalle de un análisis fallido muestra ese motivo, no solo «Fallido
· Sin hallazgos reportados».

S8. #428: `PUT` y `DELETE /api/specialty-records/{id}` con un id inexistente
responden `404 {"message":"Registro de especialidad no encontrado"}`, igual que
`show`, y el intento no deja una fila de error de servidor en el log. Medido
hoy: `500` con mensaje genérico, capturado por el `catch (\Exception)`.

S9. Cada issue trae su prueba — PHPUnit MySQL para los de backend, Vitest para
los de frontend — y las suites existentes siguen en verde.

## Tasks

- T1 — #424: acción en las dos tabs vacías de la historia. Spec: S1-S2.
  Commit `fix(medical-records): …`.
- T2 — #425: contrato de contenido mínimo del alta de evolución y su bloque de
  error en el formulario. Spec: S3-S4. Commit `fix(medical-records): …`.
- T3 — #426: el paso de imagen del alta de IA usa el canal en línea y no retiene
  el archivo rechazado. Spec: S5. Commit `fix(ai-analysis): …`.
- T4 — #427: contrato de resultado del alta de IA y su lectura en la pantalla.
  Spec: S6-S7. Commit `fix(ai-analysis): …`.
- T5 — #428: desenlace 404 de las dos puertas de escritura de registros de
  especialidad. Spec: S8. Commit `fix(specialty-records): …`.
- T6 — Sincronizar `AGENTS.md` §4/§12 y correr la verificación completa
  (Vitest, PHPUnit MySQL, Pint, ESLint, Prettier, `doc-drift`). Spec: S9.
  Commit `docs: …`.

## Log

L1. «has las issues #424 #425 #426 #427 #428 en un worktree nuevo»

L2. (exploración) Causas confirmadas por lectura: `MedicalRecordsPage.vue:505`
y `MedicalRecordStats.vue:7` dejan el `UiSectionEmpty` sin slot de acción;
`StoreEvolutionRequest` declara los nueve bloques SOAP
`sometimes|nullable`; `FileUpload.vue:97` abre `alert()` y el sheet no puede
limpiar el estado del primitivo; `AiImageAnalysisController:472` publica el
mensaje de éxito sin mirar el estado y `AiImageAnalysisResource` no expone
motivo; `SpecialtyRecordController@update/@destroy` capturan `\Exception` antes
que `ModelNotFoundException`. El instrumento U7 (`walk-ai.js`,
`walk-records.js`, `api-matrix.js`) mide los cinco defectos con los contratos
viejos y quedaría en falso contra el código nuevo.

L3. (alcance) #424 y #426 son `area:frontend`; #425, #427 y #428 son
`area:backend`, y #425 y #426 tocan también su superficie. No hay rutas,
controllers ni permisos nuevos; #427 agrega una migración
(`failure_reason`), así que §4 sube a 156.

L4. (T1) #424 entregado: RED `4 failed | 14 passed (18)` → GREEN 18/18 y 15/15
del módulo. Las dos tabs resuelven con el criterio de sus hermanas y sin
permisos nuevos.

L5. (T2) #425 entregado: RED 3 fallos PHPUnit y 2 Vitest → GREEN 6/6 y 11/11.
Dos pruebas existentes codificaban el acto vacío que el contrato ahora prohíbe
(`MedicalRecordSpecialtyValidationTest`, `medical-records-specialty`); se les
añadió un bloque clínico conservando la regla que fijan, y quedaron 17/17
MySQL y 13/13 Vitest.

L6. (T3) #426 entregado: RED `8 failed | 13 passed (21)` → GREEN 21/21.
`FileUpload` publica `fileRejected` y expone `clear()`; el sheet publica los
dos rechazos en su bloque en línea. El primitivo reinicia su input al rechazar
para que volver a elegir el mismo archivo dispare `change`.

L7. (T4) #427 entregado: RED 3 errores y 2 fallos → GREEN 89/89 en el filtro
`AiAnalysis` y 39/39 Vitest. `failure_reason` guarda la causa legible; las dos
puertas de alta corrigen su mensaje y el SPA no trata un fallo como resultado.

L8. (T5) #428 entregado: RED 2 de 4 casos en 500 → GREEN 4/4 y 95/95 en el
filtro `SpecialtyRecord`. La rama nueva no escribe log de error y no captura de
más.

L9. (instrumento) `walk-ai.js` mide el rechazo del paso de imagen por su canal
en línea y el desenlace del fallo por su aviso; `walk-records.js` mide el envío
vacío por el mensaje del formulario y la ausencia de petición;
`api-matrix.js` deja de declarar la desviación 500 de las dos puertas de
especialidad.

L10. (rodajas) Presupuesto del revisor nativo: #424 (158 líneas), #425 (~320),
#426 (~300), #427 (~700 con su migración y sus dos suites), #428 (~140). La
estrategia de entrega es **un PR por issue**, cada uno con su entrada de §12 y
su propio commit de unidad, para que ninguno pase el presupuesto de ~400
líneas que el revisor nativo toma solo; #427 es el único que se acerca y se
revisa aparte.

L11. (verificación) Vitest **1416 pruebas en 166 archivos**; suite MySQL **OK
(2873 tests, 17454 assertions)**; Pint 914 archivos; ESLint 0 errores / 36
avisos (los dos avisos nuevos del stub se cerraron declarando sus defaults);
Prettier en verde; `node scripts/audit/doc-drift.mjs` 14/14.

L12. (verificación independiente) Ocho de nueve specs `holds`. Hallazgo real en
S5: el sheet adopta la imagen con `maxSize = Infinity` en el primitivo y sólo
después aplica su regla de 20 MB, así que `clear()` limpiaba el estado mientras
el `FileReader` seguía en vuelo y su `onload` **repintaba la vista previa del
archivo rechazado** — exactamente el estado que S5 prohíbe. Corregido en el
primitivo con un `readToken` que cada reset incrementa: una lectura que termina
tarde no repinta lo que ya se soltó, y una adopción posterior vuelve a pintar su
propia vista previa (la invalidación es de archivo, no un silencio permanente).
RED medido con el primitivo anterior (`expected <img …> to be null`) y GREEN
7/7, 50/50 en los cuatro smokes de IA y **1417 pruebas en 166 archivos** en la
suite completa. El nit de atribución del motivo de fallo (un error local recibe
la frase del proveedor) queda declarado como seguimiento: la frase es legible y
no filtra nada, pero nombra una causa externa para un fallo interno.

L13. (pendiente declarado) La corrida de navegador del instrumento U7
(`walk-ai.js`, `walk-records.js`, `api-matrix.js`) no se ejecutó: requiere base
sembrada y Chromium. Su alineación con el producto está verificada por lectura.
