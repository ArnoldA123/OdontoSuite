# Guía de Instalación - OdontoSuite

Guía completa paso a paso para instalar y configurar OdontoSuite en tu computadora.

---

## 📋 Tabla de Contenidos

1. [Requisitos del Sistema](#requisitos-del-sistema)
2. [Instalación de Herramientas Necesarias](#instalación-de-herramientas-necesarias)
3. [Clonar o Descargar el Proyecto](#clonar-o-descargar-el-proyecto)
4. [Configuración del Proyecto](#configuración-del-proyecto)
5. [Configuración de la Base de Datos](#configuración-de-la-base-de-datos)
6. [Instalación de Dependencias](#instalación-de-dependencias)
7. [Configuración del Entorno](#configuración-del-entorno)
8. [Crear la Base de Datos](#crear-la-base-de-datos)
9. [Ejecutar Migraciones](#ejecutar-migraciones)
10. [Ejecutar Seeders (Datos Iniciales)](#ejecutar-seeders-datos-iniciales)
11. [Compilar Assets Frontend](#compilar-assets-frontend)
12. [Configurar Permisos](#configurar-permisos)
13. [Iniciar el Servidor](#iniciar-el-servidor)
14. [Verificar la Instalación](#verificar-la-instalación)
15. [Solución de Problemas](#solución-de-problemas)

---

## 🔧 Requisitos del Sistema

Antes de comenzar, asegúrate de tener instalado:

### Requisitos Mínimos:
- **PHP**: 8.2 o superior
- **Composer**: 2.0 o superior
- **Node.js**: 18.0 o superior
- **npm**: 9.0 o superior (viene con Node.js)
- **MySQL**: 8.0 o superior (o MariaDB 10.3+)
- **Git**: Para clonar el repositorio (opcional)

### Extensiones PHP Requeridas:
- OpenSSL
- PDO
- Mbstring
- Tokenizer
- XML
- Ctype
- JSON
- BCMath
- Fileinfo
- GD o Imagick (para procesamiento de imágenes)

---

## 🛠️ Instalación de Herramientas Necesarias

### Windows

#### 1. Instalar PHP
1. Descarga PHP desde [php.net](https://windows.php.net/download/)
2. Extrae el archivo ZIP en `C:\php`
3. Agrega `C:\php` a las variables de entorno PATH
4. Verifica la instalación:
   ```bash
   php -v
   ```

#### 2. Instalar Composer
1. Descarga el instalador desde [getcomposer.org](https://getcomposer.org/download/)
2. Ejecuta el instalador y sigue las instrucciones
3. Verifica la instalación:
   ```bash
   composer --version
   ```

#### 3. Instalar Node.js
1. Descarga Node.js desde [nodejs.org](https://nodejs.org/)
2. Ejecuta el instalador (incluye npm)
3. Verifica la instalación:
   ```bash
   node -v
   npm -v
   ```

#### 4. Instalar MySQL
1. Descarga MySQL desde [mysql.com](https://dev.mysql.com/downloads/installer/)
2. Ejecuta el instalador y configura una contraseña para el usuario `root`
3. Verifica la instalación:
   ```bash
   mysql --version
   ```

### Linux (Ubuntu/Debian)

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar PHP y extensiones
sudo apt install php8.2 php8.2-cli php8.2-common php8.2-mysql php8.2-zip \
    php8.2-gd php8.2-mbstring php8.2-curl php8.2-xml php8.2-bcmath -y

# Instalar Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar Node.js y npm
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Instalar MySQL
sudo apt install mysql-server -y
sudo mysql_secure_installation
```

### macOS

```bash
# Instalar Homebrew (si no lo tienes)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Instalar PHP
brew install php@8.2

# Instalar Composer
brew install composer

# Instalar Node.js
brew install node

# Instalar MySQL
brew install mysql
brew services start mysql
```

---

## 📥 Clonar o Descargar el Proyecto

### Opción 1: Clonar con Git (Recomendado)

```bash
# Clonar el repositorio
git clone https://github.com/tu-usuario/odontosuite.git

# Entrar al directorio
cd odontosuite
```

### Opción 2: Descargar ZIP

1. Descarga el archivo ZIP del repositorio
2. Extrae el archivo en tu directorio de trabajo
3. Abre una terminal en el directorio extraído

---

## ⚙️ Configuración del Proyecto

### Paso 1: Copiar archivo de entorno

```bash
# Windows (PowerShell)
Copy-Item .env.example .env

# Linux/macOS
cp .env.example .env
```

### Paso 2: Verificar que el archivo .env existe

```bash
# Windows
dir .env

# Linux/macOS
ls -la .env
```

Si el archivo existe, continúa. Si no existe, repite el paso anterior.

---

## 🗄️ Configuración de la Base de Datos

### Paso 1: Crear la base de datos en MySQL

Abre MySQL (puedes usar MySQL Workbench, phpMyAdmin, o la línea de comandos):

```bash
# Conectar a MySQL
mysql -u root -p
```

Luego ejecuta:

```sql
-- Crear la base de datos
CREATE DATABASE odontosuite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Crear un usuario (opcional, pero recomendado)
CREATE USER 'odontosuite_user'@'localhost' IDENTIFIED BY 'tu_password_segura';
GRANT ALL PRIVILEGES ON odontosuite.* TO 'odontosuite_user'@'localhost';
FLUSH PRIVILEGES;

-- Salir de MySQL
EXIT;
```

### Paso 2: Configurar .env con los datos de la base de datos

Abre el archivo `.env` con un editor de texto y modifica estas líneas:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=odontosuite
DB_USERNAME=odontosuite_user
DB_PASSWORD=tu_password_segura
```

**Nota:** Si usas el usuario `root`, cambia `DB_USERNAME=root` y `DB_PASSWORD=tu_password_de_root`.

---

## 📦 Instalación de Dependencias

### Paso 1: Instalar dependencias PHP (Composer)

```bash
# Instalar todas las dependencias
composer install

# Si tienes problemas de memoria, usa:
composer install --no-dev --optimize-autoloader
```

**Tiempo estimado:** 2-5 minutos

### Paso 2: Instalar dependencias JavaScript (npm)

```bash
# Instalar todas las dependencias
npm install

# Si tienes problemas, limpia la caché primero:
npm cache clean --force
npm install
```

**Tiempo estimado:** 3-7 minutos

---

## 🔐 Configuración del Entorno

### Paso 1: Generar la clave de aplicación

```bash
php artisan key:generate
```

Esto generará automáticamente `APP_KEY` en tu archivo `.env`.

### Paso 2: Configurar variables de entorno importantes

Abre el archivo `.env` y verifica/ajusta estas variables:

```env
# Aplicación
APP_NAME="OdontoSuite"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Base de datos (ya configurado anteriormente)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=odontosuite
DB_USERNAME=odontosuite_user
DB_PASSWORD=tu_password_segura

# Broadcasting (Reverb) - Para tiempo real
REVERB_APP_ID=tu_app_id
REVERB_APP_KEY=tu_app_key
REVERB_APP_SECRET=tu_app_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Mail (opcional, para envío de correos)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@odontosuite.com"
MAIL_FROM_NAME="${APP_NAME}"

# OpenAI (opcional, solo si usas análisis de imágenes con IA)
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o
OPENAI_MAX_TOKENS=1000
OPENAI_TIMEOUT=30
```

**Nota:** Para desarrollo local, puedes dejar las variables de Reverb y OpenAI vacías o con valores de prueba.

---

## 🗃️ Crear la Base de Datos

### Verificar conexión a la base de datos

```bash
php artisan db:show
```

Si ves información de tu base de datos, la conexión está correcta. Si hay errores, revisa la configuración en `.env`.

---

## 🔄 Ejecutar Migraciones

Las migraciones crean todas las tablas necesarias en la base de datos.

```bash
# Ejecutar todas las migraciones
php artisan migrate
```

**Qué hace esto:**
- Crea todas las tablas vacías (users, patients, appointments, etc.)
- Establece las relaciones entre tablas
- Crea los índices necesarios

**Tiempo estimado:** 30 segundos - 2 minutos

**Si hay errores:**
- Verifica que la base de datos existe
- Verifica las credenciales en `.env`
- Asegúrate de que MySQL esté corriendo

---

## 🌱 Ejecutar Seeders (Datos Iniciales)

Los seeders insertan los datos esenciales para que el sistema funcione.

### Opción 1: Solo Datos Esenciales (Recomendado para producción)

```bash
php artisan db:seed --class=EssentialDataSeeder
```

**Esto creará:**
- ✅ 3 usuarios básicos (admin, recepcionista, odontólogo)
- ✅ 8 tipos de citas predefinidos
- ✅ 8 ambientes/sillas dentales
- ✅ 6 métodos de pago
- ✅ 1 sucursal principal
- ✅ 32 piezas dentales (sistema de numeración FDI)

**Credenciales por defecto:**
- **Administrador:** `admin@odontosuite.com` / `password`
- **Recepcionista:** `recepcionista@odontosuite.com` / `password`
- **Odontólogo:** `odontologo@odontosuite.com` / `password`

**⚠️ IMPORTANTE:** Cambia estas contraseñas después del primer inicio de sesión.

### Opción 2: Con Datos de Prueba (Solo para desarrollo)

```bash
php artisan db:seed --class=DatabaseSeeder
```

**Esto creará además:**
- 100 pacientes ficticios
- 100 citas de ejemplo
- 30 días de sesiones de caja
- Registros de especialidades de prueba

**⚠️ NO uses esto en producción.**

---

## 🎨 Compilar Assets Frontend

El frontend necesita compilarse para que funcione correctamente.

### Opción 1: Modo Desarrollo (con recarga automática)

```bash
npm run dev
```

Deja este comando corriendo en una terminal. Se recompilará automáticamente cuando hagas cambios.

### Opción 2: Modo Producción (compilación optimizada)

```bash
npm run build
```

Esto compila los assets una vez y los optimiza para producción.

**Tiempo estimado:** 1-3 minutos

---

## 🔒 Configurar Permisos

### Linux/macOS

```bash
# Dar permisos de escritura a storage y bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Si tienes problemas, usa 777 temporalmente
chmod -R 777 storage bootstrap/cache
```

### Windows

En Windows generalmente no hay problemas de permisos, pero si los hay:

1. Click derecho en la carpeta `storage` → Propiedades
2. Pestaña "Seguridad"
3. Click en "Editar"
4. Selecciona "Usuarios" y marca "Control total"
5. Repite para `bootstrap/cache`

---

## 🚀 Iniciar el Servidor

### Paso 1: Iniciar el servidor Laravel

Abre una nueva terminal y ejecuta:

```bash
php artisan serve
```

El servidor estará disponible en: `http://localhost:8000`

### Paso 2: Iniciar Reverb (WebSockets - Opcional pero recomendado)

Abre otra terminal y ejecuta:

```bash
php artisan reverb:start
```

Esto habilita las funcionalidades en tiempo real (notificaciones, actualizaciones en vivo).

### Paso 3: Iniciar Vite (si estás en desarrollo)

Si ejecutaste `npm run dev`, ya está corriendo. Si no, ejecuta:

```bash
npm run dev
```

---

## ✅ Verificar la Instalación

### Paso 1: Abrir el navegador

Ve a: `http://localhost:8000`

Deberías ver la página de login de OdontoSuite.

### Paso 2: Iniciar sesión

Usa las credenciales del administrador:
- **Email:** `admin@odontosuite.com`
- **Contraseña:** `password`

### Paso 3: Verificar funcionalidades básicas

1. ✅ Puedes iniciar sesión
2. ✅ Ves el dashboard
3. ✅ Puedes navegar por el menú
4. ✅ No hay errores en la consola del navegador (F12)

---

## 🔧 Solución de Problemas

### Error: "Class 'PDO' not found"

**Solución:**
```bash
# Linux
sudo apt install php8.2-pdo php8.2-mysql

# macOS
brew install php@8.2
```

### Error: "SQLSTATE[HY000] [2002] No connection could be made"

**Solución:**
1. Verifica que MySQL esté corriendo:
   ```bash
   # Windows
   net start MySQL80
   
   # Linux
   sudo systemctl start mysql
   
   # macOS
   brew services start mysql
   ```

2. Verifica las credenciales en `.env`

### Error: "The stream or file could not be opened"

**Solución:**
```bash
# Linux/macOS
chmod -R 775 storage bootstrap/cache

# Windows: Ver sección de permisos arriba
```

### Error: "Vite manifest not found"

**Solución:**
```bash
# Compila los assets
npm run build

# O inicia Vite en modo desarrollo
npm run dev
```

### Error: "APP_KEY is not set"

**Solución:**
```bash
php artisan key:generate
```

### Error al ejecutar migraciones

**Solución:**
```bash
# Ver estado de migraciones
php artisan migrate:status

# Si hay problemas, resetea y vuelve a ejecutar (CUIDADO: borra datos)
php artisan migrate:fresh
php artisan db:seed --class=EssentialDataSeeder
```

### Puerto 8000 ya está en uso

**Solución:**
```bash
# Usar otro puerto
php artisan serve --port=8001
```

Luego accede a: `http://localhost:8001`

### Error: "npm install" falla

**Solución:**
```bash
# Limpiar caché
npm cache clean --force

# Eliminar node_modules y package-lock.json
rm -rf node_modules package-lock.json

# Reinstalar
npm install
```

---

## 📝 Comandos Útiles

### Base de Datos

```bash
# Ver estado de migraciones
php artisan migrate:status

# Revertir última migración
php artisan migrate:rollback

# Revertir todas las migraciones (CUIDADO: borra datos)
php artisan migrate:reset

# Ejecutar migraciones y seeders juntos
php artisan migrate --seed --class=EssentialDataSeeder
```

### Cache

```bash
# Limpiar toda la caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Desarrollo

```bash
# Ver rutas disponibles
php artisan route:list

# Ver configuración
php artisan config:show

# Abrir Tinker (consola interactiva)
php artisan tinker
```

---

## 🎯 Próximos Pasos

Después de la instalación exitosa:

1. **Cambiar contraseñas por defecto** - Especialmente la del administrador
2. **Configurar correo electrónico** - Si necesitas enviar notificaciones
3. **Configurar Reverb** - Para funcionalidades en tiempo real
4. **Revisar configuración de sucursales** - Ajustar según tu clínica
5. **Personalizar tipos de citas** - Agregar los tipos que uses
6. **Configurar ambientes** - Ajustar según tus sillas/consultorios

---

## 📞 Soporte

Si tienes problemas que no se resuelven con esta guía:

1. Revisa los logs en `storage/logs/laravel.log`
2. Verifica la consola del navegador (F12) para errores JavaScript
3. Asegúrate de tener todas las extensiones PHP requeridas
4. Verifica que todas las dependencias estén instaladas correctamente

---

## ✅ Checklist de Instalación

Marca cada paso cuando lo completes:

- [ ] Requisitos del sistema instalados (PHP, Composer, Node.js, MySQL)
- [ ] Proyecto clonado/descargado
- [ ] Archivo `.env` creado desde `.env.example`
- [ ] Base de datos creada en MySQL
- [ ] Variables de base de datos configuradas en `.env`
- [ ] Dependencias PHP instaladas (`composer install`)
- [ ] Dependencias JavaScript instaladas (`npm install`)
- [ ] `APP_KEY` generado (`php artisan key:generate`)
- [ ] Migraciones ejecutadas (`php artisan migrate`)
- [ ] Seeders ejecutados (`php artisan db:seed --class=EssentialDataSeeder`)
- [ ] Assets compilados (`npm run build` o `npm run dev`)
- [ ] Permisos configurados (storage y bootstrap/cache)
- [ ] Servidor iniciado (`php artisan serve`)
- [ ] Login exitoso con credenciales por defecto

---

**¡Felicitaciones! 🎉 Si completaste todos los pasos, OdontoSuite está listo para usar.**
