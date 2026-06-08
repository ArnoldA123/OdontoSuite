# Plan de Mejora — Planes de Tratamiento

> Filosofía: **no monolítica, todo a la vista**. El personal de la clínica no debería tener que seguir un camino lineal ni esconderse en menús para hacer su trabajo. La información vive afuera, las acciones están donde el cursor está, y la UI no obliga a un orden.

---

## 0. Filosofía de diseño (lo no negociable)

Tres principios que guían cada decisión:

1. **Lo importante, visible.** El estado del plan, el paciente, el costo, el avance y las acciones siempre a la vista, no detrás de un click.
2. **Múltiples puntos de entrada, un solo modelo.** Que se pueda crear un plan desde la ficha del paciente, desde el calendario, desde un botón global. Que se pueda ver el plan en formato lista, kanban o timeline. La información es la misma; lo que cambia es la lente.
3. **Cero estados "muertos".** Si abriste un plan, no tienes que cerrar para ver otro. Si filtraste, el filtro vive con la URL. Si editaste, puedes deshacer. Si te equivocaste, está la acción a un click, no a un menú de 3 niveles.

Concretamente, esto se traduce en:

- **Sin wizards rígidos.** El modal de crear/editar es un único formulario scrolleable con secciones plegables, no pasos forzados. Si el usuario ya sabe todo, llena y guarda en 30 segundos. Si necesita ayuda, las secciones le dan orden visual.
- **Sin modales dentro de modales.** Detalle y edición conviven en panel lateral (drawer), no se enciman.
- **Sin confirmaciones destructivas como `alert()`.** Pero tampoco un wizard de 4 pasos para confirmar un delete. Un modal de confirmación centrado, copy claro, botón rojo.
- **Sin estados escondidos.** No hay "Activo/Inactivo" detrás de un toggle. El estado del plan se ve en su color de badge, en su posición en el kanban, en su filtro de la lista, en su contador del dashboard.

---

## 1. Estado actual y bugs detectados (Sprint 0)

El módulo funciona para el flujo happy path pero tiene 6 bugs reales. **Estos se arreglan antes de seguir mejorando**, porque comprometen datos y eventos.

### Bugs

| # | Bug | Impacto | Archivo |
|---|---|---|---|
| B-1 | `calculateTotals()` usa `unit_price` (columna es `unit_cost`) | **Totales en 0** en todos los planes | `TreatmentPlanService.php:164` |
| B-2 | Inconsistencia `unit_price` ↔ `unit_cost` en controller, service, frontend | 422 silenciosos, datos inconsistentes | controller + 2 .vue |
| B-3 | `duplicate()` no emite evento WebSocket | Otras pestañas no se enteran | `TreatmentPlanService.php` |
| B-4 | Filtro de paciente en UI no funciona | Buscar es dead-end | `TreatmentPlansPage.vue` + service |
| B-5 | `last_activity_at` no se actualiza nunca | Ordenamiento y métricas rotas | service (5 métodos) |
| B-6 | `Resource` omite `requires_anesthesia`, `is_urgent`, `notes`, `quantity`, `phase_number` del item | Frontend pinta placeholders | `TreatmentPlanResource.php` |

**Sprint 0 = corregir los 6, con un comando artisan para recalcular totales de planes existentes.**

---

## 2. Mejoras priorizadas (lo que sigue, sin orden obligatorio)

Las mejoras se organizan por **lo que ve el usuario** durante su día, no por sprint. Esto refleja la filosofía: no hay una sola ruta.

### 2.1 — El personal ve los planes a primera vista

| Mejora | Valor | Esfuerzo |
|---|---|---|
| Barra de progreso en la card | Ver avance sin abrir el detalle | 1h |
| Badge de "Vencido" si `end_date < today` y no completado | Planes atrasados saltan a la vista | 1h |
| Color del borde de la card según estado (no solo badge) | Escaneo visual más rápido | 30m |
| Filtros siempre visibles (no en "Buscar") | Sin click extra para filtrar | 30m |
| Toggle Lista ↔ Kanban en la misma página | Cada rol usa su vista preferida | 4h |
| Filtros rápidos tipo pill (Borrador / Activos / Vencidos / Míos) | 1 click para ver lo relevante | 1h |

### 2.2 — Crear un plan sin fricción

| Mejora | Valor | Esfuerzo |
|---|---|---|
| Catálogo de procedimientos en el form (autocomplete) | Nombres consistentes, precios prellenados | 3h |
| Botón "Crear desde plantilla" al inicio del form | Plantillas listas (limpieza, ortodoncia, etc.) | 4h |
| Atajo `n` en cualquier pantalla → abre drawer de nuevo plan | Menus escondidos, matan | 30m |
| Recordar el último paciente usado | Si atiendo al mismo paciente, lo autocomplete | 1h |
| Drag & drop para reordenar items | Reordenar el plan sin editar | 2h |
| Resumen sticky en la parte inferior del form (costos en vivo) | Siempre ver el total mientras editas | 1h |

### 2.3 — Editar y trabajar con planes sin perder el contexto

| Mejora | Valor | Esfuerzo |
|---|---|---|
| Detalle en drawer lateral (no modal full-screen) | Ver plan al lado de la lista, no en lugar | 4h |
| Tabs en el detalle (Info · Procedimientos · Fases · Presupuestos · Notas) | Cada cosa en su lugar | 3h |
| Cambiar estado con un click desde la card o el kanban | Sin abrir detalle para "Aprobar" | 1h |
| Historial visible (cambios de estado, ediciones) | Auditoría instantánea | 4h |
| Confirmación al descartar cambios con copy claro | No perder lo que llevas | 1h |
| Editar items inline (click en celda, edita, Enter) | Sin modal de "editar item" | 4h |

### 2.4 — Acciones que faltan en la vida real

| Mejora | Valor | Esfuerzo |
|---|---|---|
| Versión de plan al editar aprobado | Auditoría + rollback | 8h |
| Botón "Generar citas" al aprobar plan | Convierte plan en agenda | 6h |
| Odontograma embebido en el plan | Ver piezas afectadas visualmente | 6h |
| Export PDF del plan (con logo de la clínica) | Compartir con paciente | 3h |
| Workflow formal de aprobación (aprobado por, fecha, motivo rechazo) | Cumplimiento | 4h |
| Firma del paciente (tablet) al aprobar | Consentimiento real | 8h |
| Adjuntos por item (fotos, radiografías) | Mejor historia clínica | 4h |

### 2.5 — Información que el equipo necesita ver (reportes)

| Mejora | Valor | Esfuerzo |
|---|---|---|
| Widget "Planes activos por odontólogo" en el dashboard | Distribución de carga visible | 3h |
| Widget "Ingresos esperados vs realizados" en el dashboard | Proyección financiera | 4h |
| Pantalla `/reports/treatment-plans` con métricas y gráficos | Decisiones basadas en datos | 8h |
| Filtros cruzados (por rango de fecha + estado + odontólogo) | Análisis ad-hoc | 2h |

### 2.6 — Lo que escala con la clínica

| Mejora | Valor | Esfuerzo |
|---|---|---|
| Plantillas de plan reutilizables | Estandarizar planes comunes | 6h |
| Comparar 2-3 planes del mismo paciente lado a lado | Decisión informada | 4h |
| Comentarios en el plan (colaboración entre clínicos) | Multi-odontólogo en mismo caso | 6h |
| Integración con stock (descuenta materiales al aprobar) | Inventario sincronizado | 8h |
| Sugerencia de plan vía IA desde odontograma | Diferenciador | 16h |

---

## 3. Lo que NO voy a hacer (decisiones de producto)

Para mantener la UI limpia y útil, **no**:

- Wizards forzados de 3+ pasos para crear un plan. Un formulario scrolleable con secciones plegables.
- Modales anidados (modal dentro de modal). Si necesitas confirmar algo, sí o sí un modal nuevo, nunca apilado.
- Sidebars con 15 sub-opciones. La navegación vive en el header principal.
- Filtros "avanzados" escondidos en un botón. Si es filtro, está visible.
- Toggle de "modo oscuro" si no hay un sistema de temas coherente. Mejor implementar dark mode bien o no tenerlo.
- "Vista previa" del plan como página aparte. El detalle ES la vista.
- Botones con tooltips de 2 líneas. Si el botón necesita tooltip largo, el ícono no es claro.

---

## 4. Roadmap de ejecución

| Semana | Foco | Entregables |
|---|---|---|
| **0** | Bugfixes (este sprint) | 6 bugs corregidos + comando de recálculo |
| **1** | Visibilidad inmediata | 2.1 completo (card, filtros, kanban toggle) |
| **2** | Creación sin fricción | 2.2 completo (catálogo, plantillas, drawer) |
| **3** | Edición contextual | 2.3 completo (drawer detalle, tabs, edición inline) |
| **4** | Features clínicas | 2.4 (generar citas, odontograma, PDF) |
| **5** | Reportes | 2.5 completo |
| **6+** | Escala | 2.6 según prioridad del negocio |

> **No es un sprint waterfall.** Si en cualquier momento una mejora de la semana 3 tiene más valor que una de la semana 2, se hace primero. Esto es una lista de valor, no un Gantt.

---

## 5. Métricas de éxito (validan que el plan funciona)

| Métrica | Hoy | Meta |
|---|---|---|
| Planes con `final_cost > 0` | 0% | 100% |
| Tiempo crear plan con 5 items | ~5 min | < 2 min |
| Planes con pieza dental asociada | desconocido | > 70% |
| Errores 422 al guardar | frecuentes | < 5% |
| `last_activity_at` actualizado | nunca | siempre |
| Eventos WebSocket emitidos correctamente | parcial | 100% en CRUD |

---

## 6. Próximo paso inmediato

**Sprint 0 ya en ejecución** (ver commits de esta sesión):
- 6 bugfixes
- Comando `php artisan plans:recalculate-totals` para datos existentes
- Sin cambios de UI (solo fixes invisibles al usuario final, pero arreglan números rotos)
