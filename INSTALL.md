# GenWeb NG - Installation & Setup

## Requisitos

- PHP >= 8.0 with SQLite support (php-sqlite3)
- Servidor web (Apache/Nginx)
- (Opcional) Composer - o usa el autoloader incluido

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/HexaCuant/ngw.git
cd ngw
```

### 2. Instalar dependencias (Opcional)

Si tienes Composer instalado:

```bash
composer install
```

Si no tienes Composer, ya hay un autoloader simple incluido en `vendor/autoload.php` que funciona perfectamente.

### 3. Configurar la aplicación

Copia el archivo de configuración ejemplo:

```bash
cp config/config.ini.example config/config.ini
```

Edita `config/config.ini` si necesitas cambiar rutas o configuración:

```ini
[database]
DB_DRIVER=sqlite  ; Usa SQLite por defecto (también soporta pgsql)
DB_PATH=data/ngw.db

[app]
SESSION_LIFETIME=86400
PROJECTS_PATH=/var/www/proyectosGengine
```

### 4. Inicializar base de datos SQLite

Ejecuta el script de inicialización:

```bash
php database/init.php
```

Esto creará:
- El directorio `data/`
- La base de datos SQLite `data/ngw.db`
- Todas las tablas necesarias
- Un usuario administrador por defecto

**Credenciales del admin por defecto:**
- Usuario: `admin`
- Contraseña: `admin123`

⚠️ **IMPORTANTE:** Cambia la contraseña del admin inmediatamente después del primer login.

### 5. Configurar permisos

```bash
# Permisos para el directorio de datos
chmod 755 data
chmod 664 data/ngw.db

# Directorio de proyectos (si usas la funcionalidad de proyectos)
mkdir -p /var/www/proyectosGengine
chown -R www-data:www-data /var/www/proyectosGengine
chmod 755 /var/www/proyectosGengine
```

### 6. Configurar servidor web

#### Apache

Crea un VirtualHost apuntando a `public/`:

```apache
<VirtualHost *:80>
    ServerName ngw.local
    DocumentRoot /srv/http/ngw/public
    
    <Directory /srv/http/ngw/public>
        AllowOverride All
        Require all granted
        
        # Rewrite rules para URLs limpias
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
    
    # Proteger archivos sensibles
    <FilesMatch "\.ini$">
        Require all denied
    </FilesMatch>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name ngw.local;
    root /srv/http/ngw/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    # Proteger archivos sensibles
    location ~ /\.ini$ {
        deny all;
    }
}
```

### 7. Sistema de registro con aprobación de administrador

NGW implementa un sistema de registro donde nuevos usuarios deben ser aprobados por un administrador:

1. **Usuarios nuevos** hacen clic en "Solicitar nueva cuenta" en la página de login
2. Llenan el formulario de registro (usuario, email, contraseña)
3. La solicitud queda **pendiente de aprobación**
4. El **administrador** accede al panel de administración y:
   - Ve todas las solicitudes pendientes
   - Puede **aprobar** o **rechazar** cada solicitud
5. Una vez aprobada, el usuario puede iniciar sesión normalmente

Para acceder al panel de administración:
- Inicia sesión como admin
- Verás un badge "Admin" en la navegación
- Haz clic en "Panel Admin" para gestionar solicitudes

## Uso

Accede a `http://ngw.local` (o tu dominio configurado).

### Primer uso:

1. Inicia sesión con las credenciales del admin por defecto
2. **Cambia la contraseña del admin inmediatamente**
3. Los nuevos usuarios pueden solicitar cuentas que tú aprobarás

### Usuarios nuevos:

1. Haz clic en "Solicitar nueva cuenta"
2. Llena el formulario de registro
3. Espera a que el administrador apruebe tu cuenta
4. Recibirás acceso una vez aprobado (implementación de notificaciones pendiente)

## Mejoras implementadas

✅ **Seguridad (Sprint A + B):**
- Password hashing con `password_hash()`/`password_verify()`
- Prepared statements (PDO) para prevenir SQL Injection
- Escape de output con `htmlspecialchars()`
- Sesiones seguras con regeneración en login
- Sistema de aprobación de usuarios por administrador

✅ **Arquitectura (Sprint A + B):**
- PSR-4 autoloading
- Separación MVC (Models, Controllers, Templates)
- Database wrapper con PDO (soporta SQLite y PostgreSQL)
- SessionManager con API limpia
- Models: Project, Character, RegistrationRequest

✅ **Base de datos (Sprint B):**
- SQLite para máxima portabilidad (sin servidor de BD)
- Esquema normalizado con foreign keys
- Soporte para PostgreSQL como fallback
- Índices optimizados

✅ **UI/UX (Sprint A):**
- CSS moderno y responsive
- Diseño mobile-first
- Accesibilidad mejorada
- Contraste de colores adecuado (WCAG AA)
- Panel de administración intuitivo

## Migración desde el sistema antiguo (gw)

Si tienes datos en el sistema antiguo con PostgreSQL, puedes migrarlos:

1. Exporta tus datos desde PostgreSQL
2. Adapta el formato al esquema SQLite (ver `database/schema.sql`)
3. Importa a SQLite usando el cliente `sqlite3`

Nota: Las contraseñas del sistema antiguo (texto plano) deberán ser regeneradas. Contacta al administrador para que apruebe tu nueva cuenta.

## Testing

```bash
composer test  # Si usas Composer
# O implementa tests manualmente
```

## Estructura del proyecto

```
ngw/
├── config/              # Configuración
│   └── config.ini.example
├── data/                # Base de datos SQLite (creado al inicializar)
│   └── ngw.db
├── database/            # Scripts de BD
│   ├── schema.sql
│   └── init.php
├── public/              # Document root
│   ├── css/
│   │   └── style.css
│   └── index.php
├── src/                 # Código fuente
│   ├── Auth/
│   │   ├── Auth.php
│   │   └── SessionManager.php
│   ├── Database/
│   │   └── Database.php
│   ├── Models/
│   │   ├── Character.php
│   │   ├── Project.php
│   │   └── RegistrationRequest.php
│   └── bootstrap.php
├── templates/           # Plantillas
│   └── pages/
│       ├── admin.php
│       ├── characters.php
│       ├── projects.php
│       └── generations.php
├── vendor/              # Autoloader
│   └── autoload.php
├── composer.json
├── CHANGELOG.md
└── README.md
```

## Ventajas de usar SQLite

- ✅ **Sin servidor de BD:** No necesitas instalar PostgreSQL/MySQL
- ✅ **Portabilidad:** Todo en un solo archivo `ngw.db`
- ✅ **Backups fáciles:** Copia el archivo `.db`
- ✅ **Ideal para desarrollo:** Setup instantáneo
- ✅ **Suficiente para la mayoría de casos:** Hasta 1TB de datos

## Próximos pasos

- [ ] Sistema de notificaciones (email al aprobar registro)
- [ ] Recuperación de contraseña
- [ ] Implementar funcionalidad completa de Generaciones
- [ ] Tests unitarios (PHPUnit)
- [ ] CI/CD (GitHub Actions)
- [ ] Docker/docker-compose
- [ ] API REST opcional

## Soporte

Para problemas o sugerencias, abre un issue en: https://github.com/HexaCuant/ngw/issues

