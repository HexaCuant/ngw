#!/bin/bash
# Setup script for NGW
# This script ensures the project is ready to run

echo "NGW Setup Script"
echo "================"
echo ""

# Check if vendor/autoload.php exists
if [ ! -f "vendor/autoload.php" ]; then
    echo "Creating vendor directory and autoloader..."
    mkdir -p vendor
    
    cat > vendor/autoload.php << 'AUTOLOADER'
<?php
/**
 * Simple PSR-4 autoloader
 * This is a minimal replacement for Composer's autoloader
 */

spl_autoload_register(function ($class) {
    // Project namespace prefix
    $prefix = 'Ngw\\';
    
    // Base directory for the namespace prefix
    $baseDir = __DIR__ . '/../src/';
    
    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Not our namespace, move to the next autoloader
        return;
    }
    
    // Get the relative class name
    $relativeClass = substr($class, $len);
    
    // Replace namespace separators with directory separators
    // and append .php
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
AUTOLOADER

    echo "✓ Autoloader created"
else
    echo "✓ Autoloader already exists"
fi

# Check if config.ini exists
if [ ! -f "config/config.ini" ]; then
    echo ""
    echo "Creating config.ini from example..."
    cp config/config.ini.example config/config.ini
    echo "✓ Config file created"
else
    echo "✓ Config file already exists"
fi

# Check if database exists
if [ ! -f "data/ngw.db" ]; then
    echo ""
    echo "Initializing SQLite database..."
    php database/init.php
    echo ""
    echo "✓ Database initialized"
else
    echo "✓ Database already exists"
fi

echo ""
echo "Setup complete!"
echo ""
echo "Next steps:"
echo "1. Configure your web server to point to the 'public/' directory"
echo "2. Access the application in your browser"
echo "3. Login with default admin credentials:"
echo "   Username: admin"
echo "   Password: admin123"
echo "4. ⚠️  CHANGE THE ADMIN PASSWORD IMMEDIATELY!"
echo ""
