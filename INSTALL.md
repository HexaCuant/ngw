# GenWeb NG - Installation & Setup

## Requisitos

- PHP >= 8.0
- PostgreSQL >= 12
- Composer
- Servidor web (Apache/Nginx)

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/HexaCuant/ngw.git
cd ngw
```

### 2. Instalar dependencias

```bash
composer install
```

### 3. Configurar base de datos

Copia el archivo de configuración ejemplo:

```bash
cp config/config.ini.example config/config.ini
```

Edita `config/config.ini` con tus credenciales de PostgreSQL:

```ini
[database]
DB_HOST=localhost
DB_PORT=5432
DB_NAME=genweb
DB_USER=genweb
DB_PASSWORD=tu_password_aqui
```

### 4. Importar esquema de base de datos

Usa el dump del proyecto original (desde `gw/genweb-schema.dump`):

```bash
psql -U genweb -d genweb -f ../gw/genweb-schema.dump
```

### 5. Configurar permisos

```bash
chmod -R 755 public
mkdir -p /var/www/proyectosGengine
chown -R www-data:www-data /var/www/proyectosGengine
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
    </Directory>
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
}
```

### 7. Migrar usuarios existentes (IMPORTANTE)

**Atención:** Esta versión usa password hashing seguro con `password_hash()`. Los usuarios del sistema antiguo tienen contraseñas en texto plano y **NO** podrán iniciar sesión directamente.

Opciones:

**A) Forzar reset de contraseñas:**
- Los usuarios deberán crear nuevas cuentas

**B) Script de migración (recomendado si tienes usuarios existentes):**

```php
// migrate_passwords.php - ejecutar UNA VEZ
<?php
require_once 'src/bootstrap.php';

$db = $app['db'];
$users = $db->fetchAll("SELECT id, username, pass FROM users");

foreach ($users as $user) {
    // Si la contraseña NO está hasheada (longitud < 60)
    if (strlen($user['pass']) < 60) {
        $hashed = password_hash($user['pass'], PASSWORD_DEFAULT);
        $db->execute(
            "UPDATE users SET pass = :pass WHERE id = :id",
            ['pass' => $hashed, 'id' => $user['id']]
        );
        echo "Migrated user: {$user['username']}\n";
    }
}
```

## Uso

Accede a `http://ngw.local` (o tu dominio configurado).

- **Primera vez:** Crea un nuevo usuario con "Nueva Carpeta"
- **Usuarios migrados:** Inicia sesión con tus credenciales

## Mejoras implementadas

✅ **Seguridad:**
- Password hashing con `password_hash()`/`password_verify()`
- Prepared statements (PDO) para prevenir SQL Injection
- Escape de output con `htmlspecialchars()`
- Sesiones seguras con regeneración en login

✅ **Arquitectura:**
- PSR-4 autoloading con Composer
- Separación MVC (Models, Controllers, Templates)
- Database wrapper con PDO
- SessionManager con API limpia

✅ **UI/UX:**
- CSS moderno y responsive
- Diseño mobile-first
- Accesibilidad mejorada
- Contraste de colores adecuado (WCAG AA)

## Testing

```bash
composer test
```

## Estructura del proyecto

```
ngw/
├── config/              # Configuración
├── public/              # Document root
│   ├── css/
│   └── index.php
├── src/                 # Código fuente
│   ├── Auth/
│   ├── Database/
│   ├── Models/
│   └── bootstrap.php
├── templates/           # Plantillas
│   └── pages/
├── composer.json
└── README.md
```

## Próximos pasos

- [ ] Implementar funcionalidad completa de Generaciones
- [ ] Tests unitarios (PHPUnit)
- [ ] CI/CD (GitHub Actions)
- [ ] Docker/docker-compose
- [ ] API REST opcional

## Soporte

Para problemas o sugerencias, abre un issue en GitHub.
