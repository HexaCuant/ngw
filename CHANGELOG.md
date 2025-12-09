# Changelog

## [Unreleased] - 2025-12-09

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
- 🔄 Migrado de `pg_*` functions a PDO
- 🔄 Reemplazados short tags `<?` por `<?php`
- 🔄 Funciones procedurales convertidas a clases
- 🔄 HTML inline convertido a templates separados

### Security
- 🔒 **CRÍTICO:** Contraseñas ya no se almacenan en texto plano
- 🔒 Eliminadas vulnerabilidades de SQL Injection
- 🔒 Protección contra XSS en todas las salidas
- 🔒 Path traversal prevenido en creación de directorios

### Pending (próximos sprints)
- ⏳ Implementación completa de funcionalidad de Generaciones
- ⏳ Tests unitarios con PHPUnit
- ⏳ CI/CD con GitHub Actions
- ⏳ Docker y docker-compose
- ⏳ Migración de script `gen2web` con validación
- ⏳ API REST opcional

### Breaking Changes
⚠️ **IMPORTANTE:** Los usuarios del sistema antiguo con contraseñas en texto plano NO pueden iniciar sesión directamente. Ver `INSTALL.md` para el script de migración.

### Notes
- El código original de `gw/` se mantiene intacto
- Esta versión es compatible con el mismo esquema de base de datos
- Se recomienda ejecutar el script de migración de contraseñas antes de desplegar en producción
