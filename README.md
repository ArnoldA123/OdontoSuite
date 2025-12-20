# OdontoSuite

Sistema de gestión odontológica completo desarrollado con Laravel y Vue.js. OdontoSuite facilita la administración de clínicas dentales, incluyendo gestión de pacientes, citas, historiales clínicos, finanzas y reportes.

![Laravel](https://img.shields.io/badge/Laravel-12.0-FF2D20?style=flat&logo=laravel&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-3.3-4FC08D?style=flat&logo=vue.js&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)

## 📋 Tabla de Contenidos

- [Características](#-características)
- [Tecnologías](#-tecnologías)
- [Requisitos](#-requisitos)
- [Instalación Rápida](#-instalación-rápida)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Características Principales](#-características-principales)
- [Documentación](#-documentación)
- [Contribuir](#-contribuir)
- [Licencia](#-licencia)

## ✨ Características

### Gestión de Pacientes
- Registro completo de información del paciente
- Historial médico y alergias
- Odontograma interactivo con sistema FDI
- Registros de especialidades (Ortodoncia, Endodoncia, Implantología, etc.)
- Evoluciones clínicas y archivos adjuntos
- Exportación de expedientes en PDF y ZIP

### Sistema de Citas
- Calendario interactivo con vista semanal y mensual
- Gestión de tipos de citas personalizables
- Asignación de profesionales y ambientes
- Estados de citas (programada, confirmada, en progreso, completada, cancelada)
- Recordatorios automáticos
- Lista de espera

### Gestión Financiera
- Sistema de caja con apertura y cierre de sesiones
- Múltiples métodos de pago (Efectivo, Tarjetas, Yape, Plin, Transferencias)
- Transacciones y movimientos de caja
- Planes de pago y cuotas
- Cotizaciones y aprobaciones
- Reportes financieros y cierres de caja

### Historiales Clínicos
- Registros médicos completos
- Especialidades:
  - Ortodoncia
  - Endodoncia
  - Implantología
  - Rehabilitación
  - Cirugía Oral
- Análisis de imágenes con IA (OpenAI)
- Interconsultas entre especialistas
- Planes de tratamiento

### Business Intelligence
- Dashboard con métricas en tiempo real
- Reportes de productividad por profesional
- Análisis de ingresos y citas
- Exportación de datos a Excel
- Gráficos y visualizaciones interactivas

### Multi-sede
- Gestión de múltiples sucursales
- Configuración independiente por sede
- Reportes consolidados

### Sistema de Roles
- **Administrador**: Acceso completo al sistema
- **Recepcionista**: Gestión de pacientes y citas
- **Odontólogo**: Gestión clínica y pacientes
- **Implantólogo**: Especialidad en implantes
- **Técnico Dental**: Gestión de laboratorio
- **Asistente**: Apoyo clínico
- **Finanzas**: Gestión financiera y reportes

## 🛠️ Tecnologías

### Backend
- **Laravel 12.0** - Framework PHP
- **PHP 8.2+** - Lenguaje de programación
- **MySQL 8.0+** - Base de datos
- **Laravel Sanctum** - Autenticación API
- **Laravel Reverb** - WebSockets en tiempo real
- **Laravel DomPDF** - Generación de PDFs
- **Maatwebsite Excel** - Exportación a Excel

### Frontend
- **Vue.js 3.3** - Framework JavaScript
- **Vue Router 4.2** - Enrutamiento
- **Vite 5.4** - Build tool y dev server
- **Tailwind CSS 3.3** - Framework CSS
- **FullCalendar 6.1** - Calendario interactivo
- **Chart.js 4.4** - Gráficos y visualizaciones
- **Laravel Echo** - WebSockets cliente
- **Axios 1.6** - Cliente HTTP

### Herramientas de Desarrollo
- **ESLint** - Linter JavaScript
- **Prettier** - Formateador de código
- **Laravel Pint** - Formateador PHP
- **PHPUnit** - Testing PHP
- **Composer** - Gestor de dependencias PHP
- **npm** - Gestor de paquetes Node.js

### Integraciones
- **OpenAI API** - Análisis de imágenes con IA
- **WebSockets** - Comunicación en tiempo real
- **PDF Generation** - Exportación de documentos

## 📦 Requisitos

- PHP >= 8.2
- Composer >= 2.0
- Node.js >= 18.0
- npm >= 9.0
- MySQL >= 8.0 (o MariaDB >= 10.3)
- Extensiones PHP: OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath, Fileinfo, GD

## 🚀 Instalación Rápida

### 1. Clonar el repositorio

```bash
git clone https://github.com/ArnoldA123/OdontoSuite.git
cd OdontoSuite
```

### 2. Instalar dependencias

```bash
# Dependencias PHP
composer install

# Dependencias JavaScript
npm install
```

### 3. Configurar entorno

```bash
# Copiar archivo de entorno
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate
```

### 4. Configurar base de datos

Edita el archivo `.env` y configura tu base de datos:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=odontosuite
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

### 5. Ejecutar migraciones y seeders

```bash
# Crear estructura de base de datos
php artisan migrate

# Insertar datos esenciales
php artisan db:seed --class=EssentialDataSeeder
```

### 6. Compilar assets

```bash
# Desarrollo
npm run dev

# Producción
npm run build
```

### 7. Iniciar servidores

```bash
# Terminal 1: Servidor Laravel
php artisan serve

# Terminal 2: Reverb (WebSockets)
php artisan reverb:start

# Terminal 3: Vite (si usas npm run dev)
npm run dev
```

### 8. Acceder al sistema

Abre tu navegador en: `http://localhost:8000`

**Credenciales por defecto:**
- Admin: `admin@odontosuite.com` / `password`
- Recepcionista: `recepcionista@odontosuite.com` / `password`
- Odontólogo: `odontologo@odontosuite.com` / `password`

⚠️ **Importante:** Cambia estas contraseñas después del primer inicio de sesión.

Para una guía de instalación detallada, consulta [INSTALACION.md](INSTALACION.md).

## 📁 Estructura del Proyecto

```
OdontoSuite/
├── app/                    # Lógica de la aplicación
│   ├── Http/
│   │   ├── Controllers/    # Controladores API
│   │   ├── Middleware/      # Middleware personalizado
│   │   └── Requests/        # Validaciones de formularios
│   ├── Models/              # Modelos Eloquent
│   ├── Services/            # Lógica de negocio
│   └── Jobs/                # Jobs de cola
├── config/                  # Archivos de configuración
├── database/
│   ├── migrations/          # Migraciones de base de datos
│   ├── seeders/             # Seeders de datos
│   └── factories/           # Factories para testing
├── public/                  # Punto de entrada público
├── resources/
│   ├── js/                  # Código JavaScript/Vue
│   │   ├── components/      # Componentes Vue reutilizables
│   │   ├── composables/     # Composables Vue
│   │   ├── modules/         # Módulos de la aplicación
│   │   └── router/          # Configuración de rutas
│   ├── css/                 # Estilos CSS
│   └── views/               # Vistas Blade
├── routes/                  # Definición de rutas
│   ├── api.php              # Rutas API
│   └── web.php              # Rutas web
├── storage/                 # Archivos de almacenamiento
└── tests/                   # Tests automatizados
```

## 🎯 Características Principales

### Dashboard Personalizado
Cada rol tiene un dashboard adaptado a sus necesidades:
- **Administrador**: Vista general con todas las métricas
- **Recepcionista**: Gestión de citas y pacientes
- **Clínico**: Agenda personal y pacientes
- **Finanzas**: Reportes y gestión de caja

### Sistema de Notificaciones en Tiempo Real
- Actualizaciones instantáneas usando WebSockets
- Notificaciones de nuevas citas
- Alertas de pagos pendientes
- Recordatorios de citas

### Exportación de Datos
- Expedientes de pacientes en PDF
- Reportes financieros en Excel
- Cierres de caja en PDF
- Cotizaciones y recibos

### Análisis de Imágenes con IA
- Integración con OpenAI para análisis de radiografías
- Detección de problemas dentales
- Sugerencias de tratamiento

### Sistema de Auditoría
- Registro de todos los cambios importantes
- Historial de acciones por usuario
- Trazabilidad completa

## 📚 Documentación

- [Guía de Instalación Completa](INSTALACION.md) - Instrucciones detalladas paso a paso
- [Seguridad](SEGURIDAD.md) - Checklist de seguridad antes de subir a producción

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📝 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 🙏 Agradecimientos

- [Laravel](https://laravel.com) - Framework PHP
- [Vue.js](https://vuejs.org) - Framework JavaScript
- [Tailwind CSS](https://tailwindcss.com) - Framework CSS
- [FullCalendar](https://fullcalendar.io) - Calendario interactivo
- [Chart.js](https://www.chartjs.org) - Gráficos
- Y todas las librerías de código abierto que hacen posible este proyecto

---

⭐ Si este proyecto te resulta útil, considera darle una estrella en GitHub.
