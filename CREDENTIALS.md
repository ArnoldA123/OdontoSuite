# Credenciales de Acceso - OdontoSuite

Este documento contiene las credenciales de acceso reales para el sistema OdontoSuite.
Datos verificados contra la BD activa (MySQL, 2026-06-11).

## Contraseña por Defecto

**Todas las cuentas utilizan la contraseña:** `password123`
**El campo "Usuario" del login es el `username`** (no el email, no el nombre).

---

## Usuarios Reales

### Administradores
| Nombre | Email | Username | Rol |
|--------|-------|----------|-----|
| Elizabet Cunia Cruz | elizabet+administrador@test.com | `elizabet` | administrador |
| Ever Huamán Cruz | ever+administrador@test.com | `ever` | administrador |
| Admin Test | admin+administrador@test.com | `admin_test` | administrador |

### Recepcionista
| Nombre | Email | Username | Rol |
|--------|-------|----------|-----|
| Recepcionista Test | recepcionista+recepcionista@test.com | `recepcionista_test` | recepcionista |

### Odontólogos
| Nombre | Email | Username | Rol |
|--------|-------|----------|-----|
| Ever Huamán Cruz | ever+odontologo@test.com | `ever_odontologo` | odontologo |
| Brenda Tejada Alfaro | brenda+odontologo@test.com | `brenda` | odontologo |
| Odontologo Test | odontologo+odontologo@test.com | `odontologo_test` | odontologo |

### Implantólogos
| Nombre | Email | Username | Rol |
|--------|-------|----------|-----|
| Wilmer Valderrama | wilmer+implantologo@test.com | `wilmer` | implantologo |
| Implantologo Test | implantologo+implantologo@test.com | `implantologo_test` | implantologo |

### Técnicos Dentales
| Nombre | Email | Username | Rol |
|--------|-------|----------|-----|
| Sofia Villanueva | sofia+tecnico@test.com | `sofia` | tecnico_dental |
| Tecnico Test | tecnico+tecnico@test.com | `tecnico_test` | tecnico_dental |

### Asistentes
| Nombre | Email | Username | Rol |
|--------|-------|----------|-----|
| Azul Huamán Díaz | azul+asistente@test.com | `azul` | asistente |
| Asistente Test | asistente+asistente@test.com | `asistente_test` | asistente |

### Finanzas
| Nombre | Email | Username | Rol |
|--------|-------|----------|-----|
| Milagros Cochachin | milagros+finanzas@test.com | `milagros` | finanzas |
| Finanzas Test | finanzas+finanzas@test.com | `finanzas_test` | finanzas |

---

## Matriz de Permisos por Rol

| Funcionalidad | Administrador | Recepcionista | Odontólogo | Implantólogo | Técnico | Asistente | Finanzas |
|---------------|---------------|---------------|------------|--------------|---------|-----------|----------|
| **Pacientes** |
| Crear pacientes | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Ver pacientes | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Editar pacientes | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Eliminar pacientes | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Exportar ficha de paciente | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| **Citas/Agenda** |
| Crear citas | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Ver citas | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Editar citas | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Cancelar citas | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| **Reportes/BI** |
| Ver reportes | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Exportar datos | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Finanzas/Caja** |
| Abrir/cerrar caja | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Registrar transacciones | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Ver presupuestos | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ✅ |
| **Configuración** |
| Tipos de cita | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Ambientes/sillones | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Profesionales | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Catálogo de procedimientos | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Especialidades | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Clínica** |
| Historias clínicas | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Odontogramas | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Planes de tratamiento | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Análisis IA | ✅ | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Interconsultas | ✅ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Favoritos de catálogo | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ❌ |

---

## Cómo loguearse

1. Ir a `/` en el navegador.
2. En el campo **"Usuario"** ingresar el `username` (ej: `adm1n`).
3. En el campo **"Contraseña"** ingresar `password123`.
4. Click en "Iniciar sesión".

**Nota**: el campo es `username`, NO el email. El campo `email` existe en la tabla `users` pero no se usa para login (se usa `Auth::attempt(['username' => ..., 'password' => ...])`).
