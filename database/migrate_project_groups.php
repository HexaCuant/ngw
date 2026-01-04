<?php
/**
 * Migration script to add project groups support
 * Run this once to update existing databases
 * 
 * Usage: php database/migrate_project_groups.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Ngw\Database\Database;

echo "Starting migration: Adding project groups support...\n";

try {
    $config = parse_ini_file(__DIR__ . '/../config/config.ini');
    if (!$config) {
        throw new \RuntimeException("Cannot read config.ini");
    }
    
    $dbConfig = [
        'driver' => 'sqlite',
        'path' => $config['DB_PATH'] ?? __DIR__ . '/../data/ngw.db'
    ];
    
    $db = new Database($dbConfig);
    
    // Check if project_groups table already exists
    $tables = $db->fetchAll("SELECT name FROM sqlite_master WHERE type='table' AND name='project_groups'");
    
    if (empty($tables)) {
        echo "Creating project_groups table...\n";
        
        $sql = "CREATE TABLE IF NOT EXISTS project_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            color TEXT DEFAULT '#6366f1',
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $db->execute($sql);
        echo "✓ project_groups table created\n";
    } else {
        echo "✓ project_groups table already exists\n";
    }
    
    // Check if projects table has group_id column
    $columns = $db->fetchAll("PRAGMA table_info(projects)");
    $hasGroupId = false;
    
    foreach ($columns as $col) {
        if ($col['name'] === 'group_id') {
            $hasGroupId = true;
            break;
        }
    }
    
    if (!$hasGroupId) {
        echo "Adding group_id column to projects table...\n";
        
        $sql = "ALTER TABLE projects ADD COLUMN group_id INTEGER REFERENCES project_groups(id) ON DELETE SET NULL";
        $db->execute($sql);
        
        echo "✓ group_id column added to projects table\n";
    } else {
        echo "✓ projects table already has group_id column\n";
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
