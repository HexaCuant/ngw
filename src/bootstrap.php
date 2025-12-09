<?php

/**
 * Bootstrap file - initializes autoloader, config, and dependencies
 */

// Start output buffering
ob_start();

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$configFile = __DIR__ . '/../config/config.ini';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/../config/config.ini.example';
}
$config = parse_ini_file($configFile, true);

// Initialize database
$dbConfig = $config['database'] ?? [];
$db = new \Ngw\Database\Database([
    'host' => $dbConfig['DB_HOST'] ?? 'localhost',
    'port' => $dbConfig['DB_PORT'] ?? 5432,
    'name' => $dbConfig['DB_NAME'] ?? 'genweb',
    'user' => $dbConfig['DB_USER'] ?? 'genweb',
    'password' => $dbConfig['DB_PASSWORD'] ?? 'genweb',
]);

// Initialize session manager
$session = new \Ngw\Auth\SessionManager();
$session->start();

// Initialize auth
$auth = new \Ngw\Auth\Auth($db);

// Helper function to escape output
function e(?string $string): string
{
    return $string !== null ? htmlspecialchars($string, ENT_QUOTES, 'UTF-8') : '';
}

// Helper function to redirect
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

// Helper function to get base URL
function baseUrl(string $path = ''): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host . '/' . ltrim($path, '/');
}

return [
    'db' => $db,
    'session' => $session,
    'auth' => $auth,
    'config' => $config,
];
