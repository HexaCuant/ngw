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

### 3. Crear directorio de proyectos

```bash
sudo mkdir -p /var/www/proyectosNGengine
sudo chown http:http /var/www/proyectosNGengine
```

### 4. Configurar (opcional)

Edita `config/config.ini`:

```ini
[database]
DB_PATH=/srv/http/ngw/data/ngw.db

[paths]
PROJECTS_PATH=/var/www/proyectosNGengine
GENGINE_SCRIPT=/usr/local/bin/ngen2web
```

### 5. Configurar servidor web

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

### Error: Permisos en directorio de proyectos

```bash
sudo chown -R http:http /var/www/proyectosNGengine
sudo chmod 755 /var/www/proyectosNGengine
```

## Documentación adicional

- [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md) - Estructura completa del proyecto
- [docs/CONEXIONES.md](docs/CONEXIONES.md) - Sistema de conexiones epistáticas
- [CHANGELOG.md](CHANGELOG.md) - Historial de cambios

## Soporte

- Issues: https://github.com/HexaCuant/ngw/issues
- gengine: https://github.com/HexaCuant/gengine

