# Seeders legacy (no se ejecutan automáticamente)

Esta carpeta contiene seeders que ya **no se usan** desde `DatabaseSeeder.php` (Sprint 1 fix DM-5 del plan-mejoras-futuras-2026-06.md).

Los archivos conservan su namespace original `Database\Seeders\XxxSeeder` para mantener `git mv` y poder invocarlos sin reconfigurar autoload. Estar en `_legacy/` es puramente organizacional.

## Por qué están aquí

Se mantuvieron por dos razones:
1. **Trazabilidad histórica**: el proyecto pasó por varias iteraciones de setup de datos (EasyDent → OdontoSuite). Estos seeders documentan cada intento.
2. **Referencia futura**: si se quiere recuperar alguna idea (ej. más pacientes, escenarios específicos), el código está disponible sin buscar en git history.

## Cómo se activaban antes

Antes de Sprint 1, `DatabaseSeeder.php` los llamaba en distintas combinaciones. Tras el cierre del plan de inconsistencias (2026-06-10) y el plan de catálogo, solo quedaron **11 seeders activos** (los del raíz de `database/seeders/`).

## Seeders legacy y razón de exclusión

| Seeder | Por qué es legacy |
|---|---|
| `AdminUserSeeder` | Dominio `@easydent.com` y rol antiguo `admin` (renombrado a `administrador`) |
| `AiAnalysisSeeder` | No referenciado desde `DatabaseSeeder` ni desde código de aplicación |
| `AppointmentSeeder` | Reemplazado por `SimpleAppointmentsSeeder` y `CompletedAppointmentsSeeder` |
| `BranchSeeder` | Reemplazado por seeders de prueba en runtime; las sedes se crean en el flujo de demo |
| `CashRegisterTestSeeder` | Reemplazado por `CashRegisterSeeder` |
| `ClinicalAttachmentSeeder` | No referenciado desde `DatabaseSeeder` ni código |
| `ClinicalDataSeeder` | Reemplazado por seeders específicos de HC/odontogramas/adjuntos |
| `DentalChairSeeder` | Reemplazado por `EnvironmentSeeder` (nombre canónico) |
| `DentalPieceSeeder` | Solo lo usa `ClinicalDataSeeder` (también legacy) |
| `DentalPiecesSeeder` | Duplicado de `DentalPieceSeeder` |
| `DentistUserSeeder` | Dominio `@easydent.com` y rol antiguo |
| `EnvironmentSeeder` | Reemplazado por setup de prueba en runtime |
| `EssentialDataSeeder` | Mega-seeder monolítico reemplazado por 11 seeders específicos |
| `EssentialUsersSeeder` | Reemplazado por `RoleBasedUsersSeeder` |
| `MedicalRecordSeeder` | No usado (las HC se crean vía API) |
| `PaymentMethodsSeeder` | Solo lo usaba `EssentialDataSeeder` (legacy) |
| `ProfessionalSeeder` | No usado (los profesionales se crean vía `UserController`) |
| `RealisticAppointmentsSeeder` | Reemplazado por `SimpleAppointmentsSeeder` |
| `ReceptionUserSeeder` | Dominio `@easydent.com` y rol antiguo `recepcion` |
| `SampleDataSeeder` | Reemplazado por seeders específicos |
| `SimpleSpecialtyRecordSeeder` | Reemplazado por `SpecialtyRecordSeeder` |
| `TestDataSeeder` | No usado |
| `TestUserSeeder` | No usado |

## Cómo correr un seeder legacy manualmente

Si querés probar uno (ej. `AdminUserSeeder` para ver el formato antiguo):

```bash
# Desde la raíz del proyecto (namespace sin el prefijo _legacy)
php artisan db:seed --class="Database\\Seeders\\AdminUserSeeder"
```

> **Cuidado**: los seeders legacy pueden tener FKs rotas o dominios de email no vigentes. No los corras en producción.

## Cómo eliminar definitivamente

Si en el futuro se confirma que ninguno se necesita:

```bash
git rm -r database/seeders/_legacy/
git commit -m "chore: eliminar seeders legacy tras validación final"
```

## Historia

- **2026-06-11** — Movidos aquí desde `database/seeders/` con `git mv` (preserva historial). Sprint 1 del plan-mejoras-futuras-2026-06.md, hallazgo DM-5.
