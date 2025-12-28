# GenWeb NG - Guía de Instalación

Guía rápida para instalar GenWeb NG. Para documentación completa, ver [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md).

## Requisitos

- PHP >= 8.0 con extensión SQLite (`php-sqlite3`)
- Servidor web (Apache/Nginx)
- **gengine** - Motor de simulación genética

## Instalación Rápida

### 1. Clonar e instalar NGW

```bash
git clone https://github.com/HexaCuant/ngw.git
cd ngw
./setup.sh
```

El script automáticamente:
- Copia la configuración de ejemplo
- Inicializa la base de datos SQLite
- Crea el usuario admin por defecto

### 2. Instalar gengine

```bash
git clone https://github.com/HexaCuant/gengine.git
cd gengine
./compila
sudo cp gengine /usr/local/bin/ngengine
sudo cp ngen2web /usr/local/bin/
sudo chmod +x /usr/local/bin/ngen2web
```

### 3. Configurar el script ngen2web

El script `ngen2web` es un wrapper que ejecuta ngengine con el entorno correcto.
Edita `/usr/local/bin/ngen2web` para configurar la ruta del directorio de proyectos:

```bash
#!/bin/bash
export HOME=/var/www/proyectosNGengine/
nice ngengine $1 > /dev/null 2>&1
```

⚠️ **Importante:** La variable `HOME` debe apuntar al directorio de proyectos donde ngengine
buscará el archivo de semilla para el generador de números aleatorios.

### 4. Crear directorio de proyectos

```bash
# Crear directorio principal
sudo mkdir -p /var/www/proyectosNGengine

# Crear directorio de configuración de gengine y archivo de semilla
sudo mkdir -p /var/www/proyectosNGengine/.gengine
echo "12345" | sudo tee /var/www/proyectosNGengine/.gengine/seed

# Configurar propietario y grupo
# Nota: Tanto el usuario del servidor web (http) como los desarrolladores
# deben poder escribir en este directorio. Recomendamos crear un grupo 'web'
# y añadir ambos usuarios.
sudo groupadd -f web
sudo usermod -aG web http
sudo usermod -aG web $USER

# Aplicar permisos
sudo chown -R http:web /var/www/proyectosNGengine
sudo chmod -R g+rwX /var/www/proyectosNGengine
```

**Estructura resultante:**
```
/var/www/proyectosNGengine/
├── .gengine/
│   └── seed          # Archivo de semilla para números aleatorios
└── {project_id}/     # Directorios de proyectos (se crean automáticamente)
    ├── {id}.poc      # Archivo de configuración del proyecto
    ├── {id}.g1       # Resultados de la generación 1
    └── {id}.dat1     # Datos estadísticos de la generación 1
```

### 5. Configurar (opcional)

Edita `config/config.ini`:

```ini
[database]
DB_PATH=/srv/http/ngw/data/ngw.db

[paths]
PROJECTS_PATH=/var/www/proyectosNGengine
GENGINE_SCRIPT=/usr/local/bin/ngen2web
```

⚠️ **Nota:** La ruta `PROJECTS_PATH` debe coincidir con el directorio configurado
en el script `ngen2web` y con el directorio creado en el paso 4.

### 6. Configurar servidor web

#### Apache (Alias)

```apache
Alias /ngw /srv/http/ngw

<Directory /srv/http/ngw>
    Options -Indexes +FollowSymLinks
    AllowOverride None
    Require all granted
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

## Credenciales por defecto

- **Usuario:** `admin`
- **Contraseña:** `admin123`

⚠️ **Cambia la contraseña inmediatamente después del primer login.**

## Permisos

```bash
chown -R http:http data/
chmod 755 data/
chmod 664 data/ngw.db
```

## Verificar instalación

1. Accede a `https://tu-servidor/ngw/`
2. Inicia sesión como admin
3. Crea un proyecto de prueba
4. Añade un carácter y crea una generación

## Solución de problemas

### Error: Base de datos no encontrada

```bash
php database/init.php
```

### Error: gengine no encontrado

Verifica que `ngen2web` esté instalado:
```bash
which ngen2web
# Debe mostrar: /usr/local/bin/ngen2web
```

### Error: Cannot create/open seed file

ngengine necesita un archivo de semilla para el generador de números aleatorios.
Verifica que existe el directorio `.gengine` y el archivo `seed`:

```bash
# Verificar que existe
ls -la /var/www/proyectosNGengine/.gengine/seed

# Si no existe, crearlo
sudo mkdir -p /var/www/proyectosNGengine/.gengine
echo "12345" | sudo tee /var/www/proyectosNGengine/.gengine/seed
sudo chown -R http:web /var/www/proyectosNGengine/.gengine
sudo chmod -R g+rwX /var/www/proyectosNGengine/.gengine
```

### Error: Cannot open gen file (código 1)

ngengine no puede escribir los archivos de resultado. Verifica los permisos:

```bash
# El grupo 'web' debe tener permisos de escritura
sudo chown -R http:web /var/www/proyectosNGengine
sudo chmod -R g+rwX /var/www/proyectosNGengine
```

### Error: Permisos en directorio de proyectos

```bash
sudo chown -R http:web /var/www/proyectosNGengine
sudo chmod -R g+rwX /var/www/proyectosNGengine
```

### Probar ngengine manualmente

Para verificar que ngengine funciona correctamente:

```bash
cd /var/www/proyectosNGengine
HOME=/var/www/proyectosNGengine ngengine {project_id}
echo "Exit code: $?"
# Debe mostrar: Exit code: 0
```

## Documentación adicional

- [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md) - Estructura completa del proyecto
- [docs/CONEXIONES.md](docs/CONEXIONES.md) - Sistema de conexiones epistáticas
- [CHANGELOG.md](CHANGELOG.md) - Historial de cambios

## Soporte

- Issues: https://github.com/HexaCuant/ngw/issues
- gengine: https://github.com/HexaCuant/gengine

