# GenWeb NG - Documentación Técnica

## Descripción General

**GenWeb NG** (Next Generation) es una aplicación web para simulación genética educativa. Permite a estudiantes y profesores crear proyectos de genética, definir caracteres hereditarios con sus genes y alelos, y simular cruzamientos genéticos con generaciones descendientes.

La aplicación funciona en conjunto con **gengine**, un motor de simulación genética escrito en C++ que realiza los cálculos de herencia y genera las poblaciones.

---

## Requisitos del Sistema

### Software necesario

| Componente | Versión mínima | Descripción |
|------------|----------------|-------------|
| PHP | 8.0+ | Lenguaje del backend |
| SQLite3 | 3.x | Base de datos (extensión PHP) |
| Apache/Nginx | 2.4+ / 1.18+ | Servidor web |
| Composer | 2.x | Gestor de dependencias PHP |
| gengine | Última | Motor de simulación genética (C++) |

### Extensiones PHP requeridas

- `pdo_sqlite` - Acceso a SQLite
- `json` - Manipulación de JSON
- `session` - Gestión de sesiones
- `mbstring` - Soporte de cadenas multibyte

---

## Estructura del Proyecto

```
ngw/
├── bin/                    # Scripts de utilidad
├── config/                 # Configuración
├── data/                   # Base de datos y dumps
├── database/               # Esquema e inicialización
├── docs/                   # Documentación
├── public/                 # Punto de entrada web (DocumentRoot)
│   ├── css/               # Estilos
│   ├── js/                # JavaScript
│   └── index.php          # Controlador principal
├── src/                    # Código fuente PHP
│   ├── Auth/              # Autenticación y sesiones
│   ├── Database/          # Capa de acceso a datos
│   └── Models/            # Modelos de dominio
├── templates/              # Vistas/plantillas
│   ├── pages/             # Páginas principales
│   └── partials/          # Componentes reutilizables
├── vendor/                 # Dependencias (Composer)
├── index.php               # Redirección a public/
├── composer.json           # Dependencias PHP
└── setup.sh                # Script de instalación
```

---

## Descripción de Archivos

### Raíz del proyecto

| Archivo | Descripción |
|---------|-------------|
| `index.php` | Redirección automática a `public/index.php` |
| `composer.json` | Definición de dependencias y autoload PSR-4 |
| `composer.lock` | Versiones fijadas de dependencias |
| `setup.sh` | Script de instalación inicial |
| `fix-permissions.sh` | Corrige permisos de archivos |
| `.gitignore` | Archivos excluidos de Git |
| `README.md` | Documentación general |
| `INSTALL.md` | Instrucciones de instalación |
| `CHANGELOG.md` | Historial de cambios |
| `plan_mejora.md` | Plan de mejoras futuras |
| `gw.code-workspace` | Configuración de VS Code |
| `phpcs.xml.dist` | Configuración de PHP CodeSniffer |
| `phpstan.neon` | Configuración de PHPStan |

### Directorio `bin/`

Scripts de utilidad para mantenimiento:

| Archivo | Descripción |
|---------|-------------|
| `dump-db.sh` | Genera dump normalizado de la BD (`data/ngw.sql`) |
| `dump-and-commit.sh` | Dump + commit automático a Git |

**Uso:**
```bash
./bin/dump-db.sh                    # Genera data/ngw.sql
./bin/dump-and-commit.sh            # Dump + git commit + push
```

### Directorio `config/`

| Archivo | Descripción |
|---------|-------------|
| `config.ini` | Configuración activa (no versionado) |
| `config.ini.example` | Plantilla de configuración |

**Configuración (`config.ini`):**

```ini
[database]
DB_DRIVER=sqlite
DB_PATH=/srv/http/ngw/data/ngw.db

[app]
APP_ENV=production
APP_DEBUG=false
APP_NAME=GenWeb NG

[paths]
PROJECTS_PATH=/var/www/proyectosNGengine
GENGINE_SCRIPT=/usr/local/bin/ngen2web

[admin]
ADMIN_USERNAME=admin
```

| Variable | Descripción |
|----------|-------------|
| `DB_DRIVER` | Driver de BD: `sqlite` o `pgsql` |
| `DB_PATH` | Ruta absoluta al archivo SQLite |
| `PROJECTS_PATH` | Directorio donde gengine guarda los proyectos |
| `GENGINE_SCRIPT` | Ruta al script wrapper de gengine |

### Directorio `data/`

| Archivo | Descripción |
|---------|-------------|
| `ngw.db` | Base de datos SQLite activa |
| `ngw.sql` | Dump SQL para versionado en Git |

> **Nota:** `ngw.db` está en `.gitignore`. Solo `ngw.sql` se versiona.

### Directorio `database/`

| Archivo | Descripción |
|---------|-------------|
| `schema.sql` | Esquema completo de la BD (para instalación limpia) |
| `init.php` | Script de inicialización de la BD |

**Crear BD desde cero:**
```bash
sqlite3 data/ngw.db < database/schema.sql
```

### Directorio `public/`

Punto de entrada web. Debe ser el DocumentRoot o accesible vía alias.

| Archivo/Directorio | Descripción |
|--------------------|-------------|
| `index.php` | Controlador frontal (toda la lógica de rutas) |
| `favicon.ico` | Icono del sitio |
| `css/style.css` | Estilos CSS (tema oscuro GTK-like) |
| `css/*.png` | Imágenes del interfaz |
| `js/ajax-handlers.js` | Handlers AJAX globales |
| `js/project-handlers.js` | Lógica de proyectos y frecuencias alélicas |

### Directorio `src/`

Código PHP siguiendo PSR-4 bajo namespace `Ngw\`.

#### `src/Auth/`

| Archivo | Clase | Descripción |
|---------|-------|-------------|
| `Auth.php` | `Ngw\Auth\Auth` | Autenticación de usuarios |
| `SessionManager.php` | `Ngw\Auth\SessionManager` | Gestión de sesiones PHP |

#### `src/Database/`

| Archivo | Clase | Descripción |
|---------|-------|-------------|
| `Database.php` | `Ngw\Database\Database` | Wrapper PDO para SQLite/PostgreSQL |

#### `src/Models/`

| Archivo | Clase | Descripción |
|---------|-------|-------------|
| `Character.php` | `Ngw\Models\Character` | Modelo de caracteres genéticos |
| `Generation.php` | `Ngw\Models\Generation` | Modelo de generaciones |
| `Project.php` | `Ngw\Models\Project` | Modelo de proyectos |
| `RegistrationRequest.php` | `Ngw\Models\RegistrationRequest` | Solicitudes de registro |

### Directorio `templates/`

Vistas PHP con HTML embebido.

#### `templates/pages/`

| Archivo | Descripción |
|---------|-------------|
| `admin.php` | Panel de administración (usuarios, aprobaciones) |
| `characters.php` | Gestión de caracteres genéticos |
| `generations.php` | Creación y visualización de generaciones |
| `projects.php` | Listado y gestión de proyectos |
| `summary.php` | Resumen/estadísticas de proyecto |
| `_generation_parentals.js` | JavaScript para selección de parentales |

#### `templates/partials/`

| Archivo | Descripción |
|---------|-------------|
| `character_details.php` | Detalle de un carácter (genes, alelos) |

---

## Esquema de Base de Datos

### Tablas principales

```
users                    - Usuarios del sistema
registration_requests    - Solicitudes de registro pendientes
projects                 - Proyectos de simulación
characters               - Caracteres genéticos definidos
project_characters       - Relación proyecto-carácter
genes                    - Genes definidos
character_genes          - Relación carácter-gen
alleles                  - Alelos de genes
gene_alleles             - Relación gen-alelo
connections              - Conexiones epistáticas
generations              - Generaciones creadas
parentals                - Individuos parentales seleccionados
project_allele_frequencies - Frecuencias alélicas por proyecto
```

### Diagrama de relaciones

```
users ─────────────────┬──────────────────────┐
   │                   │                      │
   ▼                   ▼                      ▼
projects          characters         registration_requests
   │                   │
   ▼                   ▼
project_characters ◄──┘
   │
   ▼
generations ──► parentals
   │
   ▼
project_allele_frequencies

characters ──► character_genes ──► genes ──► gene_alleles ──► alleles
                                     │
                                     ▼
                                connections
```

---

## Integración con gengine

### Descripción

**gengine** es el motor de simulación genética que realiza los cálculos de herencia mendeliana. NGW genera archivos de configuración (POC) y gengine produce los archivos de generación (.g#).

### Repositorio

- GitHub: `https://github.com/HexaCuant/gengine`
- Rama principal: `main`

### Instalación de gengine

```bash
# Clonar repositorio
git clone https://github.com/HexaCuant/gengine.git
cd gengine

# Compilar
./compila

# Instalar binario
sudo cp gengine /usr/local/bin/ngengine

# Instalar script wrapper
sudo cp ngen2web /usr/local/bin/
sudo chmod +x /usr/local/bin/ngen2web
```

### Script wrapper (`ngen2web`)

```bash
#!/bin/bash
export HOME=/var/www/proyectosNGengine/
nice ngengine $1 > /dev/null 2>&1
```

El script:
1. Establece `HOME` al directorio de proyectos
2. Ejecuta ngengine con prioridad baja (`nice`)
3. Suprime salida (para uso desde PHP)

### Directorio de proyectos

```
/var/www/proyectosNGengine/
├── 1/                      # Proyecto ID 1
│   └── .1/                 # Directorio oculto de datos
│       ├── 1.poc1          # Configuración generación 1
│       ├── 1.g1            # Datos generación 1
│       ├── 1.poc2          # Configuración generación 2
│       └── 1.g2            # Datos generación 2
├── 2/                      # Proyecto ID 2
│   └── .2/
└── ...
```

### Formato de archivo POC

```
#file created by GenWeb NG
n100                        # número de individuos
i1                          # número de generación
*characters
3:0:                        # carácter ID 3, ambiente 0
6=1:1:3:23:50:1100:24:20:1100:&:    # gen 6, alelos 23,24
12=2:1:3:28:50:1100:29:20:1100:&:   # gen 12, alelos 28,29
$=
states
0=1
1=0
2=0
$=
connections
0=6=1
1=12=2
$=
@:
*frequencies
23:0.6:24:0.4:28:0.7:29:0.3:       # frecuencias alélicas
$
*create                     # comando para crear generación
*end
```

### Permisos

```bash
# El usuario del servidor web necesita permisos
sudo chown -R http:http /var/www/proyectosNGengine
sudo chmod -R 755 /var/www/proyectosNGengine
```

---

## Instalación Completa

### 1. Clonar repositorio

```bash
cd /srv/http
git clone https://github.com/HexaCuant/ngw.git
cd ngw
```

### 2. Instalar dependencias PHP

```bash
composer install --no-dev
```

### 3. Configurar

```bash
cp config/config.ini.example config/config.ini
# Editar config/config.ini con rutas correctas
```

### 4. Crear base de datos

```bash
mkdir -p data
sqlite3 data/ngw.db < database/schema.sql
```

### 5. Configurar permisos

```bash
./fix-permissions.sh
# O manualmente:
chown -R http:http data/
chmod 755 data/
chmod 664 data/ngw.db
```

### 6. Instalar gengine

```bash
cd /tmp
git clone https://github.com/HexaCuant/gengine.git
cd gengine
./compila
sudo cp gengine /usr/local/bin/ngengine
sudo cp ngen2web /usr/local/bin/
sudo chmod +x /usr/local/bin/ngen2web
```

### 7. Crear directorio de proyectos

```bash
sudo mkdir -p /var/www/proyectosNGengine
sudo chown http:http /var/www/proyectosNGengine
sudo chmod 755 /var/www/proyectosNGengine
```

### 8. Configurar servidor web

#### Apache

```apache
# /etc/httpd/conf/extra/ngw.conf
Alias /ngw /srv/http/ngw

<Directory /srv/http/ngw>
    Options -Indexes +FollowSymLinks
    AllowOverride None
    Require all granted
</Directory>

<Directory /srv/http/ngw/public>
    DirectoryIndex index.php
</Directory>
```

#### Nginx

```nginx
location /ngw {
    alias /srv/http/ngw;
    index index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        include fastcgi_params;
    }
}
```

### 9. Acceder

- URL: `https://tu-servidor/ngw/`
- Usuario inicial: `admin`
- Contraseña: `admin123` (¡cambiar inmediatamente!)

---

## Mantenimiento

### Backup de base de datos

```bash
cd /srv/http/ngw
./bin/dump-db.sh
# Genera data/ngw.sql
```

### Restaurar base de datos

```bash
cd /srv/http/ngw
rm data/ngw.db
sqlite3 data/ngw.db < data/ngw.sql
```

### Actualizar desde Git

```bash
cd /srv/http/ngw
git pull origin main
composer install --no-dev
```

### Logs de errores

```bash
# Apache
tail -f /var/log/httpd/error_log

# Nginx
tail -f /var/log/nginx/error.log

# PHP-FPM
tail -f /var/log/php-fpm/error.log
```

---

## Roles de Usuario

| Rol | Permisos |
|-----|----------|
| `admin` | Todo: gestión de usuarios, aprobar registros, ver todos los proyectos |
| `teacher` | Crear caracteres públicos, ver proyectos de sus estudiantes |
| `student` | Crear proyectos propios, usar caracteres públicos |

---

## API AJAX

Los endpoints AJAX están en `public/index.php` bajo el parámetro `ajax=1`.

### Endpoints principales

| Acción | Método | Descripción |
|--------|--------|-------------|
| `get_allele_frequencies` | GET | Obtener frecuencias de un proyecto |
| `save_allele_frequencies` | POST | Guardar frecuencias alélicas |
| `create_random_generation` | POST | Crear generación aleatoria |
| `create_generation` | POST | Crear generación por cruzamiento |
| `get_generation_individuals` | GET | Obtener individuos de generación |

---

## Licencia

MIT License - Ver archivo LICENSE en el repositorio.

---

## Soporte

- Repositorio NGW: https://github.com/HexaCuant/ngw
- Repositorio gengine: https://github.com/HexaCuant/gengine
- Issues: https://github.com/HexaCuant/ngw/issues
