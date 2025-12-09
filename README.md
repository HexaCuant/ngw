# ngw - GenWeb Next Generation

**Versión mejorada y refactorizada de GenWeb** — Sistema de gestión de generaciones genéticas con seguridad reforzada, arquitectura moderna y diseño responsive.

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## 🎯 Características principales

### ✅ Sprint A completado (9 dic 2025)

- **Seguridad reforzada:**
  - Password hashing con `password_hash()`/`password_verify()`
  - Prepared statements (PDO) para prevenir SQL Injection
  - Escape de output contra XSS
  - Sesiones seguras con regeneración

- **Arquitectura moderna:**
  - PSR-4 autoloading con Composer
  - Patrón MVC con separación clara
  - Clases: Database, Auth, SessionManager, Models
  - Bootstrap centralizado

- **UI/UX mejorada:**
  - CSS moderno y responsive (mobile-first)
  - Diseño accesible (WCAG AA)
  - Navegación intuitiva
  - Formularios mejorados

## 📦 Instalación rápida

```bash
git clone https://github.com/HexaCuant/ngw.git
cd ngw
composer install
cp config/config.ini.example config/config.ini
# Edita config.ini con tus credenciales de DB
```

Ver [INSTALL.md](INSTALL.md) para instrucciones detalladas.

## 🚀 Uso

```bash
# Servidor de desarrollo PHP
php -S localhost:8000 -t public/

# Accede a http://localhost:8000
```

## 📁 Estructura

```
ngw/
├── config/           # Configuración
├── public/           # Document root (index.php, CSS)
├── src/              # Código fuente (Auth, Database, Models)
├── templates/        # Plantillas de vistas
├── composer.json     # Dependencias y autoloading
└── README.md
```

## 📚 Documentación

- [Plan de mejoras](plan_mejora.md) - Análisis detallado y roadmap
- [Guía de instalación](INSTALL.md) - Setup paso a paso
- [Changelog](CHANGELOG.md) - Historial de cambios

## 🔒 Seguridad

⚠️ **Importante:** Esta versión usa password hashing. Los usuarios del sistema antiguo deben migrar sus contraseñas. Ver [INSTALL.md](INSTALL.md).

## 🛠️ Tecnologías

- PHP >= 8.0
- PostgreSQL >= 12
- PDO (abstracción de base de datos)
- Composer (autoloading PSR-4)

## 🗺️ Roadmap

- [x] Sprint A: Seguridad y arquitectura base
- [ ] Sprint B: Tests unitarios y CI/CD
- [ ] Sprint C: Funcionalidad completa de Generaciones
- [ ] Sprint D: Docker y deployment

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor abre un issue antes de hacer cambios mayores.

## 📄 Licencia

MIT License - Copyright (c) 2025 HexaCuant

---

**Nota:** Este proyecto es una refactorización del proyecto `gw` original, manteniendo compatibilidad con el esquema de base de datos existente.
