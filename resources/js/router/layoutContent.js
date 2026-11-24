// Ancho del contenido — una propiedad de la RUTA, no del layout.
//
// El layout envuelve todas las páginas en `mx-auto max-w-7xl` (1.280 px). En una
// ventana ancha eso deja cientos de píxeles en blanco a cada lado, y la agenda
// del calendario —que reparte una columna por ambiente más el panel del
// turno— necesita ese ancho para no caer en una barra horizontal con la
// pantalla vacía alrededor.
//
// La lista vive acá y no en `app.js` porque el router se define en `app.js`: la
// guarda `tests/js/unit/app-shell-width.test.js` cruza esta lista con la tabla
// de rutas real, así que declarar una ruta acá sin existir en `app.js` (o al
// revés) falla con el nombre de la ruta. El día que una ruta declare
// `meta.fullWidth` en `app.js`, esa meta gana y esta lista puede encogerse.
export const FULL_WIDTH_ROUTE_PATHS = ['/calendar']

const matchesPath = (path, candidate) => path === candidate || path.startsWith(`${candidate}/`)

/**
 * `true` cuando la ruta pide contenido fluido: `meta.fullWidth` explícito o una
 * de las rutas de `FULL_WIDTH_ROUTE_PATHS` (con sus subrutas).
 */
export function isFullWidthLayout(route) {
  if (route?.meta?.fullWidth === true) return true
  const path = typeof route?.path === 'string' ? route.path : ''
  if (!path) return false
  return FULL_WIDTH_ROUTE_PATHS.some(candidate => matchesPath(path, candidate))
}
