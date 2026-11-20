# Tres destinos y superficies homogéneas

## Objetivo

Reducir la duplicación de la arquitectura de información (Dashboard, Mi día,
Calendario, Continuidad) y homogeneizar las nueve superficies que el dueño
revisó: Dashboard, Mi día, Calendario, Pendientes, Pacientes, Profesionales,
Ambientes, Tipos de cita y Especialidades.

## Unidades entregadas

| Unidad | Contenido | Commit |
| --- | --- | --- |
| S1 | Tres destinos: Pendientes absorbe continuidad y comerciales; el Dashboard deja de listar citas y pierde `dashboard/appointments-today` y `dashboard/upcoming` | `7ec4127f` |
| S2 | Una cabecera (`PageHeader`), una anatomía de fila, una paleta semántica de acciones y estados, y la puerta clínica única (U3: el Calendario enlaza a Mi jornada y conserva el check-in) | `4eed9d0d` |

## Fronteras de revisión

- **S1** es un cambio de contrato: retira dos endpoints y dos secciones. Se
  revisa por comportamiento (qué desaparece y qué lo sustituye).
- **S2** es presentación: no cambia payloads ni permisos. Se revisa por medición
  en navegador (cabecera, celda, color) y por los guards estáticos.
- El trabajo previo de rediseño de login (`LoginPage.vue`,
  `DentalParallaxBackground.vue`, `usePointerParallax.js`, tokens, pruebas de
  login) queda **fuera** de estos commits y debe viajar en su propia rama.

## Verificación medida

- `vitest`: 177 archivos / 1552 pruebas.
- `phpunit`: 3014 pruebas, 10828 aserciones; 2 fallos preexistentes del trabajo
  de login (`HotfixLoginHeadlineTrackingTest`, `HotfixLoginSubtitleTest`).
- `doc-drift`: 14/14.
- Cabecera de las nueve superficies: un `h1`, 36 px / 600, radio 22, alto 158,
  sin marca ni «Volver».
- Tablas: fila 69 px salvo Ambientes (89 por equipamiento), sin recorte ni
  scroll horizontal, acciones con color por intención.
- U3 en navegador: sesión clínica sin «Iniciar atención» en el Calendario y con
  «Ir a mi jornada»; recepción conserva «Marcar llegada» y no ve el puente.

## Pendiente declarado

- `settings/branches` y `procedure-catalog` mantienen «Ver» y «Editar» con el
  mismo color, fuera de las nueve superficies.
- El guard de paleta compara tokens; el hex real solo lo vigila la medición en
  navegador.
- La base local necesitó tres migraciones (`cancellation_reason` entre ellas)
  para servir `/api/appointments`; se aplicaron al verificar.
