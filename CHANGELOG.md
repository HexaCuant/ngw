# Changelog

## [1.1.0] - 2025-12-28

### Added - Frecuencias Alélicas y Limpieza

#### Frecuencias Alélicas
- ✅ Nueva tabla `project_allele_frequencies` para frecuencias por proyecto
- ✅ Interfaz de configuración de frecuencias en la página de proyectos
- ✅ Integración con gengine para generaciones con distribución no uniforme
- ✅ Validación de frecuencias (suma = 1.0 por gen)

#### Documentación
- ✅ Nueva documentación técnica completa (`docs/ESTRUCTURA.md`)
- ✅ README.md actualizado y simplificado
- ✅ Instrucciones de instalación de gengine

#### Limpieza del Proyecto
- ✅ Eliminados archivos de test y debug
- ✅ Eliminados directorios vacíos
- ✅ Agregado `index.php` en raíz para redirección limpia
- ✅ Acceso simplificado: `/ngw/` redirige a `/ngw/public/`

### Changed
- 🔄 Actualizado dump de base de datos (`data/ngw.sql`)
- 🔄 Reorganizada documentación bajo `docs/`

---

## [1.0.0] - 2025-12-09

### Added - Sprint B: Independencia Total y Sistema de Aprobación

#### Base de Datos
- ✅ Migración completa de PostgreSQL a SQLite
- ✅ Esquema SQLite con 11 tablas normalizadas
- ✅ Foreign keys y constraints habilitados
- ✅ Índices optimizados para queries frecuentes
- ✅ Script de inicialización `database/init.php`
- ✅ Soporte dual: SQLite (primario) y PostgreSQL (fallback)
- ✅ Nombres de tablas y columnas estandarizados en inglés

#### Sistema de Registro con Aprobación
- ✅ Nueva tabla `registration_requests` para solicitudes
- ✅ Modelo `RegistrationRequest` con métodos CRUD
- ✅ Flujo de registro: solicitud → aprobación admin → activación
- ✅ Panel de administración para gestionar solicitudes
- ✅ Vista de solicitudes pendientes, aprobadas y rechazadas
- ✅ Usuario admin por defecto (username: admin, password: admin123)
- ✅ Badge "Admin" visible para administradores
- ✅ Campo `is_approved` en tabla users
- ✅ Campo `is_admin` para roles administrativos

#### Modelos actualizados
- ✅ `Database.php`: Soporte para SQLite y PostgreSQL
- ✅ `Auth.php`: Validación de usuarios aprobados
- ✅ `SessionManager.php`: Método `isAdmin()` añadido
- ✅ `Project.php`: Nombres de columnas SQLite (name, user_id, environment)
- ✅ `Character.php`: Nombres de columnas SQLite (is_visible, is_public, creator_id)
- ✅ Todos los modelos usan prepared statements con PDO

#### Templates actualizados
- ✅ Nueva página `admin.php` para panel administrativo
- ✅ `characters.php`: Actualizado para campos SQLite (is_visible, is_public)
- ✅ `projects.php`: Actualizado para campos SQLite (name, character_id, environment)
- ✅ `index.php`: Añadida ruta de solicitud de cuenta y admin
- ✅ Navegación con badge admin y enlace a panel

#### Infraestructura
- ✅ Autoloader simple incluido en `vendor/autoload.php` (no requiere Composer)
- ✅ Directorio `data/` para base de datos SQLite
- ✅ Configuración actualizada con `DB_DRIVER=sqlite`
- ✅ PHP 8.0+ type hints (nullable types)

### Added - Sprint A: Seguridad y Arquitectura Base

#### Seguridad
- ✅ Implementado password hashing con `password_hash()` y `password_verify()`
- ✅ Reemplazadas todas las consultas SQL por prepared statements (PDO)
- ✅ Añadido escape de output con `htmlspecialchars()` en todas las vistas
- ✅ Sesiones seguras con regeneración de ID en login/logout
- ✅ Validación de rutas para creación de directorios de proyectos

#### Arquitectura
- ✅ Creada estructura PSR-4 con autoloading (Composer)
- ✅ Implementada clase `Database` con wrapper PDO
- ✅ Implementada clase `Auth` con métodos seguros de autenticación
- ✅ Implementada clase `SessionManager` para gestión de sesiones
- ✅ Creadas clases de modelo: `Project`, `Character`
- ✅ Separación clara entre lógica, vistas y datos

#### UI/UX
- ✅ Nuevo CSS moderno y responsive (mobile-first)
- ✅ Variables CSS para consistencia de diseño
- ✅ Mejoras de accesibilidad (labels, contraste WCAG AA)
- ✅ Diseño con tarjetas (cards), alertas y tablas mejoradas
- ✅ Navegación clara y responsive
- ✅ Formularios mejorados con validación visual

#### Infraestructura
- ✅ Archivo `composer.json` con autoloading PSR-4
- ✅ Archivo de configuración `config.ini` separado de código
- ✅ Bootstrap centralizado (`src/bootstrap.php`)
- ✅ Estructura de directorios organizada

### Changed

#### Sprint B
- 🔄 Cambiado de PostgreSQL a SQLite como base de datos primaria
- 🔄 Nombres de tablas: proyectos→projects, caracteres→characters, usuarios→users
- 🔄 Nombres de columnas: proname→name, creatorid→creator_id, userid→user_id
- 🔄 Campos booleanos: 't'/'f' strings → 0/1 integers
- 🔄 Campos en español → inglés (ambiente→environment, sustratos→substrates)
- 🔄 Genes: chr→chromosome, pos→position, cod→code
- 🔄 Autoloader: Composer opcional, incluido vendor/autoload.php simple

#### Sprint A
- 🔄 Migrado de `pg_*` functions a PDO
- 🔄 Reemplazados short tags `<?` por `<?php`
- 🔄 Funciones procedurales convertidas a clases
- 🔄 HTML inline convertido a templates separados

### Security

#### Sprint B
- 🔒 Sistema de aprobación de usuarios previene registros no autorizados
- 🔒 Roles de administrador para gestión de usuarios
- 🔒 SQLite con foreign keys y constraints habilitados

#### Sprint A
- 🔒 **CRÍTICO:** Contraseñas ya no se almacenan en texto plano
- 🔒 Eliminadas vulnerabilidades de SQL Injection
- 🔒 Protección contra XSS en todas las salidas
- 🔒 Path traversal prevenido en creación de directorios

### Benefits of Sprint B

#### Independencia Total de `gw`
- ✅ No requiere servidor PostgreSQL externo
- ✅ Base de datos en un solo archivo (`data/ngw.db`)
- ✅ Portabilidad máxima (copiar el archivo = backup)
- ✅ Setup instantáneo con `php database/init.php`

#### Control de Usuarios
- ✅ Administrador aprueba cada nuevo usuario
- ✅ Evita registros spam o no autorizados
- ✅ Panel intuitivo para gestión de solicitudes
- ✅ Estados claros: pendiente, aprobado, rechazado

### Pending (próximos sprints)
- ⏳ Notificaciones por email al aprobar/rechazar registros
- ⏳ Recuperación de contraseña
- ⏳ Implementación completa de funcionalidad de Generaciones
- ⏳ Tests unitarios con PHPUnit
- ⏳ CI/CD con GitHub Actions
- ⏳ Docker y docker-compose
- ⏳ Migración de script `gen2web` con validación
- ⏳ API REST opcional

### Breaking Changes

#### Sprint B
⚠️ **Base de datos:** Ahora usa SQLite en lugar de PostgreSQL. Si tienes datos existentes en PostgreSQL del sistema `gw`, necesitarás migrarlos manualmente al nuevo esquema SQLite.

⚠️ **Nombres de campos:** Todos los nombres de tablas y columnas cambiaron al inglés. Las plantillas y modelos se actualizaron, pero cualquier código personalizado necesitará adaptarse.

#### Sprint A
⚠️ **IMPORTANTE:** Los usuarios del sistema antiguo con contraseñas en texto plano NO pueden iniciar sesión directamente. Deben solicitar nueva cuenta y esperar aprobación del administrador.

### Notes
- El código original de `gw/` se mantiene completamente intacto
- `ngw` es ahora 100% independiente de `gw`
- SQLite es suficiente para proyectos pequeños y medianos (hasta 1TB)
- Si necesitas PostgreSQL, puedes cambiar `DB_DRIVER=pgsql` en config.ini
- Usuario admin por defecto: `admin` / `admin123` - **cámbialo inmediatamente**

### Migration Guide from `gw`

Si tienes datos en el sistema antiguo:

1. Exporta datos de PostgreSQL (`pg_dump`)
2. Adapta nombres de tablas/columnas al nuevo esquema (ver `database/schema.sql`)
3. Importa a SQLite usando `sqlite3`
4. Los usuarios deben solicitar nuevas cuentas (las contraseñas antiguas no son válidas)
5. Administrador aprueba las nuevas solicitudes

