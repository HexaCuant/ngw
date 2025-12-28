# ngw - GenWeb Next Generation

**Sistema de simulación genética educativa** — Aplicación web para crear proyectos de genética, definir caracteres hereditarios y simular cruzamientos genéticos.

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-blue)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-SQLite-green)](https://www.sqlite.org/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## 🎯 Características principales

### ✅ Funcionalidades implementadas

- **Gestión de proyectos genéticos:**
  - Crear y administrar proyectos de simulación
  - Asignar caracteres hereditarios a proyectos
  - Configurar frecuencias alélicas personalizadas

- **Caracteres y genes:**
  - Definir caracteres con genes y alelos
  - Sistema de conexiones epistáticas
  - Caracteres públicos compartibles entre usuarios

- **Simulación de generaciones:**
  - Crear generaciones aleatorias con frecuencias configurables
  - Cruzamientos entre individuos seleccionados
  - Visualización de fenotipos y genotipos

- **Sistema de usuarios:**
  - Roles: administrador, profesor, estudiante
  - Registro con aprobación por administrador
  - Profesores pueden ver proyectos de sus estudiantes

- **Base de datos SQLite:**
  - Sin servidor externo requerido
  - Portable (un solo archivo)
  - Backup fácil

## 📦 Instalación rápida

```bash
git clone https://github.com/HexaCuant/ngw.git
cd ngw
./setup.sh
```

**Credenciales por defecto:**
- Usuario: `admin`
- Contraseña: `admin123`

⚠️ **Cambia la contraseña inmediatamente después del primer login.**

## 🔧 Requisitos

- PHP 8.0+ con extensión SQLite
- Servidor web (Apache/Nginx)
- **gengine** - Motor de simulación genética

### Instalación de gengine

```bash
git clone https://github.com/HexaCuant/gengine.git
cd gengine
./compila
sudo cp gengine /usr/local/bin/ngengine
sudo cp ngen2web /usr/local/bin/
```

## 📚 Documentación

| Documento | Descripción |
|-----------|-------------|
| [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md) | Estructura completa del proyecto |
| [docs/CONEXIONES.md](docs/CONEXIONES.md) | Sistema de conexiones epistáticas |
| [INSTALL.md](INSTALL.md) | Instrucciones de instalación detalladas |
| [CHANGELOG.md](CHANGELOG.md) | Historial de cambios |

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

## � Seguridad

- ✅ Password hashing con bcrypt
- ✅ SQL Injection prevenido (prepared statements)
- ✅ XSS protegido (escape de output)
- ✅ Sistema de aprobación de usuarios

## 🛠️ Tecnologías

- **PHP** >= 8.0 con SQLite
- **SQLite** 3.x
- **gengine** - Motor de simulación genética (C++)

## 🤝 Contribuir

1. Fork el proyecto
2. Crea una rama (`git checkout -b feature/nueva-funcionalidad`)
3. Commit tus cambios
4. Push y abre un Pull Request

## 📄 Licencia

MIT License - Copyright (c) 2025 HexaCuant

---

- **Repositorio NGW:** https://github.com/HexaCuant/ngw
- **Repositorio gengine:** https://github.com/HexaCuant/gengine

