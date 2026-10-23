# ADR-0014 — El registro de atención es un documento firmado: motivo, CIE-10 y firma electrónica simple

- **Status:** Accepted
- **Decided:** 2026-10-22 (issue #286)
- **Body recorded:** 2026-10-22 (misma entrega)
- **Supersedes:** nothing. **Superseded by:** nothing.

## Context

NTS 139-MINSA/2018 exige por cada atención la fecha y hora, el motivo de
consulta, el examen, el diagnóstico con código CIE-10, el tratamiento, la
próxima cita y la firma del profesional. NTS 188-MINSA/DGIESP-2022 regula el
odontograma que esa atención documenta.

Antes de esta decisión el acto de F2 (#282) ya existía como entidad propia, pero
el diagnóstico era texto libre: `diagnosis_catalog` estaba en el esquema desde
la primera migración con su `cie_code` único y ninguna clase, seeder ni ruta lo
leían. Tampoco había forma de saber quién respondía por un acto cerrado: el
cierre escribía `closed_by` y la contraseña del profesional nunca se revalidaba.
Y el expediente no se podía imprimir: no existía documento con la estructura de
la norma.

## Decision

1. **El motivo de consulta vive en la atención.** `clinical_encounters` gana
   `reason_for_consultation`. La puerta de urgencia lo acepta al abrir; el
   cierre lo exige en todas las modalidades. No se duplica en la evolución
   porque la evolución documenta el examen y el tratamiento, no el relato de
   llegada.

2. **El diagnóstico se codifica contra el catálogo CIE-10.** `diagnosis_catalog`
   gana modelo (`DiagnosisCatalog`), seeder con el capítulo odontológico
   (K00-K14 más las dos entradas de estomatología que la práctica usa) y
   superficie de lectura (`GET /api/diagnoses`, permiso
   `view.diagnosis_catalog`). El acto guarda una fila por código en
   `encounter_diagnoses` con `diagnosis_catalog_id` y una copia de `cie_code` y
   `name`: editar o retirar una entrada del catálogo no reescribe un registro
   firmado. Una entrada inactiva no se puede codificar.

3. **La firma es electrónica simple y se toma al cerrar.** El profesional del
   acto reingresa su propia contraseña; el cierre guarda `signed_by`,
   `signed_at`, `signer_license` y `signature_hash`, que es un SHA-256 del
   contenido congelado —cabecera del acto, evoluciones, diagnósticos, hallazgos
   del odontograma y materiales—. Un cierre sin contraseña válida, sin
   diagnóstico o firmado por alguien que no es el profesional del acto responde
   422/403 y no escribe nada. El plan de tratamiento queda fuera del hash a
   propósito: el plan sigue vivo después del acto, y un registro no puede dejar
   de verificar porque una sesión posterior agregó una línea.

4. **El registro imprimible es la vista del acto firmado.**
   `GET /api/encounters/{id}/record` emite un PDF con los bloques de la norma
   (paciente, atención, motivo, examen, diagnóstico CIE-10, tratamiento, próxima
   cita, odontograma y firma con su hash de verificación). Sólo una atención
   cerrada tiene registro, y la lectura deja `medical_record_viewed` con
   `surface = attention_record`, como cualquier otra lectura clínica (#292).

## Alternatives considered

- **Guardar el CIE-10 como texto en la evolución.** Barato, pero el catálogo
  existen para algo: sin `diagnosis_catalog_id` no hay validación, no hay
  búsqueda por nombre y cada profesional escribe el código a mano.
- **Firma dibujada (rúbrica) como prueba principal.** Un trazo no prueba
  identidad y no se puede verificar; se descartó como firma única. Puede sumarse
  después como imagen complementaria sin cambiar este contrato.
- **Firma con certificado digital.** Es la firma jurídicamente fuerte, pero
  exige una autoridad certificadora y custodia de claves que el producto no
  tiene contratadas. La firma simple deja el registro íntegro y atribuible, y el
  hash es el punto de extensión hacia un certificado.
- **Sellar el registro completo incluyendo el plan.** Haría que toda firma
  histórica fallara en cuanto el plan recibiera una sesión nueva.

## Consequences

- Un acto cerrado por la API de hoy siempre está firmado; los actos históricos
  de #282 quedan sin firma y el recurso lo declara (`signature.signed = false`)
  en lugar de inventar una.
- Cambiar el catálogo no altera registros pasados, y el hash permite detectar
  una edición directa en base de datos.
- El cierre de la cita exige tres miembros nuevos de payload
  (`reason_for_consultation`, `diagnoses`, `signature`) y el asistente del SPA
  los captura; cualquier cliente externo que cerrara atenciones debe sumarlos.
- La firma es de re-autenticación: un usuario con la contraseña de un
  profesional puede firmar como él. La traza (`signed_by`, IP y auditoría) es la
  defensa, igual que en cualquier firma simple.
- Retención y archivo por antigüedad, y firma digital con certificado, siguen
  fuera de alcance.

## References

- Issue #286 (épica #294, fase F6). Depende de #282 y #292.
- `app/Support/ClinicalRecordSignature.php` — payload canónico y verificación.
- `app/Services/ConsultationService.php` — el cierre único firma el acto.
- `app/Http/Controllers/Api/DiagnosisCatalogController.php` y
  `app/Http/Controllers/Api/ClinicalEncounterController.php@record`.
- `database/seeders/DiagnosisCatalogSeeder.php`.
- `resources/views/reports/encounter-record.blade.php`.
- Tests: `ClinicalRecordComplianceTest`, `DiagnosisCatalogTest`,
  `EncounterRecordPdfTest`.
- NTS N° 139-MINSA/2018 y NTS N° 188-MINSA/DGIESP-2022.
