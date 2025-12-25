# ngw - GenWeb Next Generation

**Versión mejorada y refactorizada de GenWeb** — Sistema de gestión de generaciones genéticas con seguridad reforzada, arquitectura moderna, diseño responsive y **totalmente independiente**.

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-blue)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-SQLite-green)](https://www.sqlite.org/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## 🎯 Características principales

### ✅ Sprint B completado (9 dic 2025) - **Independencia Total**

- **Base de datos SQLite:**
  - ✨ Sin servidor de base de datos externo
  - 📦 Todo en un solo archivo portable
  - 🚀 Setup instantáneo con script de inicialización
  - 💾 Backups tan fáciles como copiar un archivo

- **Sistema de aprobación de usuarios:**
  - 🔐 Registro controlado por administrador
  - 👤 Usuario admin por defecto incluido
  - 📋 Panel de administración intuitivo
  - ✅ Aprobación/rechazo de solicitudes de registro

### ✅ Sprint A completado (9 dic 2025)

- **Seguridad reforzada:**
  - Password hashing con `password_hash()`/`password_verify()`
  - Prepared statements (PDO) para prevenir SQL Injection
  - Escape de output contra XSS
  - Sesiones seguras con regeneración

- **Arquitectura moderna:**
  - PSR-4 autoloading (Composer opcional)
  - Patrón MVC con separación clara
  - Clases: Database, Auth, SessionManager, Models
  - Bootstrap centralizado

- **UI/UX mejorada:**
  - CSS moderno y responsive (mobile-first)
  - Diseño accesible (WCAG AA)
  - Navegación intuitiva
  - Formularios mejorados

## 📦 Instalación rápida

### Método 1: Script automático (recomendado)

```bash
git clone https://github.com/HexaCuant/ngw.git
cd ngw
./setup.sh
```

El script `setup.sh` automáticamente:
- Crea el autoloader si no existe
- Copia la configuración de ejemplo
- Inicializa la base de datos SQLite
- Crea el usuario admin por defecto

### Método 2: Manual

```bash
git clone https://github.com/HexaCuant/ngw.git
cd ngw
cp config/config.ini.example config/config.ini
php database/init.php
```

### Credenciales por defecto

- **Usuario:** admin
- **Contraseña:** admin123

⚠️ **IMPORTANTE:** Cambia la contraseña del admin inmediatamente después del primer login.

Ver [INSTALL.md](INSTALL.md) para instrucciones detalladas.

## 🚀 Uso

### Servidor de desarrollo PHP

```bash
php -S localhost:8000 -t public/
# Accede a http://localhost:8000
```

### Producción

Configura tu servidor web (Apache/Nginx) para apuntar al directorio `public/`. Ver [INSTALL.md](INSTALL.md) para ejemplos de configuración.

## 🔑 Sistema de usuarios

### Para administradores

1. Inicia sesión como admin
2. Verás un badge "Admin" en la navegación
3. Accede al "Panel Admin" para:
   - Ver solicitudes pendientes de registro
   - Aprobar o rechazar nuevas cuentas
   - Gestionar usuarios

### Para nuevos usuarios

1. Haz clic en "Solicitar nueva cuenta"
2. Llena el formulario de registro
3. Espera a que un administrador apruebe tu solicitud
4. Una vez aprobado, podrás iniciar sesión

## 📁 Estructura

```
ngw/
├── config/           # Configuración (config.ini)
├── data/             # Base de datos SQLite
│   └── ngw.db
├── database/         # Scripts de BD
│   ├── schema.sql
│   └── init.php
├── public/           # Document root (index.php, CSS)
├── src/              # Código fuente (Auth, Database, Models)
│   ├── Auth/
│   ├── Database/
│   └── Models/
├── templates/        # Plantillas de vistas
│   └── pages/
├── vendor/           # Autoloader
├── setup.sh          # Script de instalación
└── README.md
```

## 📚 Documentación

- [Plan de mejoras](plan_mejora.md) - Análisis detallado y roadmap
- [Guía de instalación](INSTALL.md) - Setup paso a paso
- [Changelog](CHANGELOG.md) - Historial de cambios detallado

## 🔒 Seguridad

✅ **Password hashing:** Todas las contraseñas usan `password_hash()` de PHP  
✅ **SQL Injection:** Prevenido con prepared statements  
✅ **XSS:** Output escapado con `htmlspecialchars()`  
✅ **Control de acceso:** Sistema de aprobación por administrador  

⚠️ Los usuarios del sistema antiguo (`gw`) deben solicitar nuevas cuentas.

## 🛠️ Tecnologías

- **PHP** >= 8.0 con extensión SQLite
- **SQLite** 3.x (incluido en PHP)
- **PDO** (abstracción de base de datos)
- **Composer** (opcional - incluye autoloader simple)

## 💡 Ventajas sobre el sistema original (gw)

---

## 🗃️ Database dump for git (SQL snapshot)

To keep a readable, versionable snapshot of the development database in the repository, this project provides a script that exports a normalized SQL dump suitable for git.

- The binary DB file (`data/ngw.db`) is ignored in `.gitignore` (committed to the repo). Use the dump instead when you want to track schema or seed data changes.
- Generate a normalized dump with:

```bash
./bin/dump-db.sh [path/to/db] [path/to/output.sql]
# defaults: ./bin/dump-db.sh data/ngw.db data/ngw.sql
```

What the script does:
- Exports schema and data by table
- Orders rows by primary key where possible to make output deterministic
- Normalizes timestamp literals to a constant (`1970-01-01 00:00:00`) to reduce noisy diffs
- Removes SQLite internal sequences (`sqlite_sequence`)

Important notes:
- Do **not** commit production or sensitive data: **always** review `data/ngw.sql` before committing/pushing.
- The script is intended for development/seed snapshots and not as a replacement for a proper migration system. For structural changes prefer creating migration scripts.

### Quick helper: generate and commit dump

A convenience script is provided to generate the normalized dump and optionally commit it:

```bash
# generate dump (dry-run, will not commit)
./bin/dump-and-commit.sh

# generate dump and commit with default message
./bin/dump-and-commit.sh --commit -m "Update DB dump"

# generate dump, commit and push
./bin/dump-and-commit.sh --commit --push -m "Update DB dump"
```

The script will:
- Run `./bin/dump-db.sh` to produce `data/ngw.sql` (by default).
- If `--commit` is passed and the dump changed, it will `git add` and `git commit` the file with the provided message.
- Pass `--push` to also `git push` the new commit.

Always inspect the generated `data/ngw.sql` before committing to ensure no sensitive or unintended data is included.

---

## 💡 Ventajas sobre el sistema original (gw)

| Característica | gw (original) | ngw (mejorado) |
|---|---|---|
| Base de datos | PostgreSQL (servidor externo) | SQLite (archivo local) |
| Contraseñas | Texto plano | Hasheadas (bcrypt) |
| SQL Injection | Vulnerable | Protegido (prepared statements) |
| XSS | Sin protección | Protegido (escape de output) |
| Arquitectura | Procedural, mezclado | MVC, PSR-4 |
| Registro usuarios | Libre | Con aprobación de admin |
| Setup | Complejo (BD externa) | Simple (1 script) |
| Portabilidad | Baja | Alta (1 archivo DB) |

## 🗺️ Roadmap

- [x] Sprint A: Seguridad y arquitectura base
- [x] Sprint B: SQLite y sistema de aprobación de usuarios
- [ ] Sprint C: Notificaciones por email
- [ ] Sprint D: Funcionalidad completa de Generaciones
- [ ] Sprint E: Tests unitarios y CI/CD
- [ ] Sprint F: Docker y deployment

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor abre un issue antes de hacer cambios mayores.

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

MIT License - Copyright (c) 2025 HexaCuant

---

**Nota:** Este proyecto es una refactorización **completa e independiente** del proyecto `gw` original. No requiere el sistema antiguo para funcionar.

