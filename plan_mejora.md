# Plan de mejora para GenWeb (proyecto `gw`)

Fecha: 9 de diciembre de 2025

Resumen
-------
Analicé rápidamente el proyecto `gw` (PHP procedural, PostgreSQL, scripts y CSS clásicos). Identifiqué problemas importantes de seguridad, arquitectura y experiencia de usuario. Este documento describe un plan de mejoras prioritarias y detalladas para código, seguridad, arquitectura, interfaz visual y flujo de despliegue.

Objetivos
---------
- Asegurar la aplicación reduciendo riesgos críticos (inyecciones SQL, contraseñas en claro, ejecución de comandos inseguros)
- Organizar código para facilitar mantenimiento (separación de responsabilidades, uso de autoload/composer)
- Mejorar la experiencia de usuario (accesibilidad, responsive design, estilo y estructura HTML)
- Poner la base para CI/CD, tests y despliegue reproducible

Hallazgos (resumen rápido)
---------------------------
- Uso extensivo de SQL con concatenación directa => vulnerabilidad a SQL Injection
- Contraseñas almacenadas en claro y comparación en claro => riesgo serio de seguridad
- Uso de PHP short tags `<?` en algunos archivos => compatibilidad
- Uso de `system()`/escritura de archivos con datos sin sanitizar => riesgo de ejecución remota y path injection
- HTML mezclado con lógica en muchos puntos => difícil mantenimiento, falta de estructura (no hay layout/plantillas)
- Sin CSRF tokens ni validaciones (forms) -> vulnerable
- CSS antiguo con contrastes de color pobres y reglas repetidas; no responsive
- Gestión de sesiones ad-hoc y uso de variables `$_SESSION` sin un wrapper
- Falta de tests, CI, scripts reproducibles (no docker, no composer)

Plan de mejora detallado (por prioridades)
-----------------------------------------
1) Urgente: Seguridad y estabilidad (Prioridad: Alta, Tiempo estimado: 1–2 días)
  - Migrar autenticación a un sistema seguro
    - Reemplazar inserción/consulta de password en claro por `password_hash()` y `password_verify()`.
    - Cambiar el flujo de login para usar prepared statements o PDO con parámetros.
    - Uso de env/archivo de configuración para credenciales DB (no hardcode).
  - Prevención de SQL Injection
    - Reemplazar todas las consultas concatenadas por consultas preparadas (pg_prepare/pg_execute o migración a PDO).
  - Evitar ejecución de comandos shell inseguros
    - Revisar `system()` y `gen2web` commands; asegurarse de que ningún argumento provenga de input no validado.
  - Escapar todo output (XSS) - `htmlspecialchars()` en las salidas de datos del usuario.
  - Añadir validación y saneamiento en todas las entradas POST/GET y validación de tipos (int/strings/flags).

2) Diseño de código y arquitectura (Prioridad: Alta, Tiempo estimado: 3–6 días)
  - Reorganizar el código hacia un patrón (mínimo: Front Controller + Controllers), o usar un micro-framework.
    - Crear carpeta `src/` o `app/` con controladores y clases (DB, Auth, User, Project, Character, Generation).
    - Mantener `index.php` como punto de entrada mínimo; mover lógica a controllers.
  - Usar autoloading/composer
    - Crear `composer.json` (PSR-4) y mover funciones a clases.
  - Establecer wrappers de DB y sesiones
    - Implementar clase `DB` para consultas usando prepared statements o PDO.
    - Implementar `SessionManager` para regeneración de sesión en login y logout.
  - Mejora del manejo de errores y logging
    - Introducir PSR-3 logger (monolog) y convertir mensajes debug en logs, no echo.

3) Calidad del Código y Mejores Prácticas (Prioridad: Media, Tiempo estimado: 3–5 días)
  - Reescribir funciones usando `<?php` (no short tags)
  - Aplicar PSR-12/PSR-1 style; correr linter (PHP_CodeSniffer)
  - Añadir tests unitarios (PHPUnit) para componentes críticos: auth, DB, file I/O, generación.
  - Añadir validación de entrada en controladores y sanitizar SQL y rutas de archivos.

4) UI/UX y Diseño (Prioridad: Media, Tiempo estimado: 4–6 días)
  - Rehacer la base `genweb.css`
    - Modernizar colores, definir variables, contraste accesible (contraste >= 4.5:1), simplificar reglas.
  - Accesibilidad y semántica
    - Agregar etiquetas `label` a inputs, ARIA roles donde proceda.
    - Asegurar el site sea usable en móvil (responsive) y añadir viewport meta tag.
  - UX: Formularios y validaciones visuales; mejorar mensajes de error; usar modales para confirmaciones
  - Considerar usar Bootstrap (o similar) para acelerar el diseño moderno y responsive.

5) Archivos & Permisos (Prioridad: Media, Tiempo estimado: 1–2 días)
  - Validación de rutas y permiso de creación de carpetas bajo `/var/www/proyectosGengine/`.
  - Evitar que un usuario cree directorios arbitrarios fuera del path configurado.
  - Asegurar permisos de ficheros y propiedad.

6) Automatización, Tests y DevOps (Prioridad: Baja→Media, Tiempo estimado: 3–5 días)
  - Añadir `Dockerfile` y `docker-compose` para entorno reproducible (Postgres + PHP-FPM + Nginx)
  - Añadir CI (GitHub Actions) para ejecutar linter y tests, y realizar análisis básico de seguridad.
  - Añadir scripts para instalación y migración de BD (migrations).

7) Mejoras a largo plazo y features sugeridas (Prioridad: Baja, Tiempo: variable)
  - API REST para la aplicación (JSON) y separación del cliente del servidor
  - Reescritura completa en framework moderno (Laravel, Symfony, Slim) si se planen mejoras mayores
  - Implementar roles y permisos más finos (admin, owner, viewer)
  - Auditoría y control de cambios en DB (audit logs)

Entregables por sprint (sugerencia)
----------------------------------
Sprint A (2–3 días): Parches críticos y seguridad
  - Autenticación con hashing
  - Reescritura de consultas críticas con prepared statements
  - Escape de contenidos y validación de input
  - Primeras pruebas unitarias para auth & DB

Sprint B (4–5 días): Refactor de arquitectura y pruebas
  - Migración de funciones a clases y autoloading
  - DB wrapper y SessionManager
  - Introducción a logging y manejo de errores

Sprint C (4–6 días): UI/UX y diseño
  - Nuevo CSS, responsive layout, mejoras de accesibilidad
  - Reestructuración de templates y forms

Sprint D (3–5 días): CI/CD y Docker
  - Docker, composer, GitHub Actions, tests en CI

Checklist (OK/NO OK)
--------------------
- [ ] Hash de contraseñas y verificación con password_verify
- [ ] Todas las consultas principales usan prepared statements
- [ ] No hay `system()` con variables sin sanitizar
- [ ] Página responsive y accesible
- [ ] Test coverage básico (auth + DB CRUD)
- [ ] Autoload y PSR compliance
- [ ] Documentación: README con steps locales, Diagramas básicos y DB schema

Riesgos & Notas
---------------
- Migración de contraseña: si hay usuarios existentes, necesitarás forzar reseteo o migrar hashes; no puedes recuperar passwords en claro.
- Refactor grande: hay extensas dependencias implícitas vía `$_SESSION` y globales; planifica refactor con pruebas y feature-branches.
- Integración con `gen2web` (binario): validar su interfaz de ejecución y asegurarse de que inputs estén validados y contenidos en directorios controlados.

Siguientes pasos (concretos, ejecutables)
----------------------------------------
1. Implementar cambios de seguridad mínimos (password hashing y prepared statements) en `func.php` + `func_*` (1–2 días).
2. Añadir validación y escape en todas las salidas (XSS) y formularios (CSRF tokens).
3. Iniciar reorganización: crear `src/` y mover wrappers DB, Auth y Session y crear `composer.json`.
4. Crear PR grande y tomarlo en revisión; aplicar tests unitarios y CI.
5. Diseñar prototipo de UI (HTML + CSS) para una pantalla (ej: `index.php`) y revisar con el equipo.

---

Para cualquier tarea específica (ej: "refactor auth" o "rediseño de UI"), puedo crear un plan de subtareas más detallado, y proponer los cambios concretos en archivos y commits a la rama `old-master`.

Contacto
--------
- Autor: Análisis automático con Copilot
- Siguientes pasos sugeridos: dime cuál tarea priorizas y genero un PR con los cambios iniciales (p. ej. aplicar password hashing y prepared statements).