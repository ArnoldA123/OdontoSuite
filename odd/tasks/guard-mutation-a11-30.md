# Task: auditoría de guards por mutación (issue #30, A11)

Status: DONE, PR abierto. Pendiente: merge (cierra #30).

## Muestra (8 guards, todos restaurados, cero brechas de producto)

| Guard | Mutación | Falla | Veredicto |
|---|---|---|---|
| ApiDashboardRoutesTest | ruta renombrada | SÍ | efectivo |
| GeneratedTokensCssTest | token alterado | SÍ | efectivo |
| BroadcastingAuth503Test | 503→500 | NO→SÍ | arreglado inline (asertaba comentario) |
| OrphanEventsDeprecatedTest | marcador NF-2 tocado | SÍ | efectivo |
| SidebarEyebrowAuditTest | eyebrow inyectado | SÍ | efectivo |
| IconInBoxAuditTest | icon-in-box inyectado | SÍ | efectivo |
| HotfixDashboardStaggerTest | useSpring comentado | NO→SÍ | arreglado inline (contaba comentarios) |
| CardHoverMotionGateTest | hover alterado | SÍ | efectivo |

Más: `EventChannelAuthorizationTest` endurecido (nombres base, no comentarios).

## Evidencia

- `.atl/qa-evidence/audit/guards/guards-muestreo.md` (local, gitignored).
- Suites objetivo verdes; sin residuo de mutaciones en source.
- TIENE-ISSUE: ninguno (las 2 debilidades eran del lado test).

## Espejo Engram

Observación pendiente al mergear.
