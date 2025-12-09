<?php
/**
 * Database initialization script
 * Run this once to create the SQLite database with schema
 */

// Load config
$configFile = __DIR__ . '/../config/config.ini';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/../config/config.ini.example';
}
$config = parse_ini_file($configFile);

// Define data directory
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$dbPath = $dataDir . '/ngw.db';

echo "Initializing NGW Database...\n\n";

$schemaFile = __DIR__ . '/schema.sql';

if (!file_exists($schemaFile)) {
    die("Error: schema.sql not found!\n");
}

$schema = file_get_contents($schemaFile);

try {
    // Create SQLite connection directly
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Enable foreign keys
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    // Execute schema
    $pdo->exec($schema);
    
    echo "✓ Database schema created successfully\n";
    echo "✓ Tables created\n";
    echo "✓ Indexes created\n";
    echo "✓ Default admin user created\n\n";
    
    echo "Default admin credentials:\n";
    echo "  Username: admin\n";
    echo "  Password: admin123\n\n";
    
    echo "⚠️  IMPORTANT: Change the admin password immediately!\n";
    echo "   Login and go to your profile to change it.\n\n";
    
    echo "Database file location: " . $dbPath . "\n";
    
} catch (Exception $e) {
    die("Error initializing database: " . $e->getMessage() . "\n");
}

echo "\nDatabase initialization complete!\n";

