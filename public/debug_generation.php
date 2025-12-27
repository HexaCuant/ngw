<?php
// Test script to debug generation creation from web context

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

use Ngw\Database\Database;
use Ngw\Models\Project;
use Ngw\Models\Generation;
use Ngw\Auth\SessionManager;

// Initialize
$config = parse_ini_file(__DIR__ . '/../config/config.ini.example');
$db = new Database($config);
$session = new SessionManager();

echo "Content-Type: text/plain\n\n";
echo "=== Debug Generation Creation ===\n\n";

try {
    // Check active project
    $projectId = $session->get('active_project_id');
    echo "Active project ID: " . ($projectId ?: 'NONE') . "\n";
    
    if (!$projectId) {
        echo "ERROR: No active project. Please open a project first.\n";
        exit(1);
    }
    
    // Initialize models
    $projectModel = new Project($db);
    $generationModel = new Generation($db);
    
    // Get project info
    $project = $projectModel->getById($projectId);
    echo "Project name: " . $project['name'] . "\n\n";
    
    // Check project has characters
    $characters = $projectModel->getCharacters($projectId);
    echo "Characters in project: " . count($characters) . "\n";
    if (empty($characters)) {
        echo "ERROR: Project has no characters!\n";
        exit(1);
    }
    
    foreach ($characters as $char) {
        echo "  - Character {$char['character_id']}: {$char['name']} (env: {$char['environment']})\n";
    }
    echo "\n";
    
    // Test parameters
    $populationSize = 5;
    $generationNumber = $generationModel->getNextGenerationNumber($projectId);
    
    echo "Next generation number: $generationNumber\n";
    echo "Population size: $populationSize\n\n";
    
    // Generate POC
    echo "Step 1: Generating POC file...\n";
    try {
        $pocPath = $projectModel->generatePocFile($projectId, $populationSize, $generationNumber, 'random');
    } catch (\RuntimeException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
    echo "POC created: $pocPath\n";
    
    if (!file_exists($pocPath)) {
        echo "ERROR: POC file was not created!\n";
        exit(1);
    }
    
    $pocSize = filesize($pocPath);
    echo "POC file size: $pocSize bytes\n\n";
    
    // Show POC content
    echo "POC content:\n";
    echo str_repeat('-', 60) . "\n";
    echo file_get_contents($pocPath);
    echo str_repeat('-', 60) . "\n\n";
    
    // Execute gengine
    echo "Step 2: Executing gengine...\n";
    $result = $generationModel->executeGengine($projectId);
    
    echo "Return code: " . $result['return_code'] . "\n";
    echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "Output:\n" . $result['output'] . "\n\n";
    
    if (!$result['success']) {
        echo "ERROR: gengine execution failed!\n";
        exit(1);
    }
    
    // Check output file
    $datFile = "/var/www/proyectosGengine/$projectId/$projectId.dat$generationNumber";
    echo "Step 3: Checking output file...\n";
    echo "Expected file: $datFile\n";
    
    if (!file_exists($datFile)) {
        echo "ERROR: Output .dat file not found!\n";
        exit(1);
    }
    
    echo "✓ Output file exists\n";
    echo "File size: " . filesize($datFile) . " bytes\n\n";
    
    // Create database record
    echo "Step 4: Creating database record...\n";
    $generationModel->create($projectId, $generationNumber, $populationSize, 'random');
    echo "✓ Database record created\n\n";
    
    // Parse output
    echo "Step 5: Parsing output...\n";
    $individuals = $generationModel->parseGenerationOutput($projectId, $generationNumber);
    echo "✓ Found " . count($individuals) . " individuals\n\n";
    
    // Show first 3 individuals
    $count = 0;
    foreach ($individuals as $id => $phenotypes) {
        echo "Individual $id: ";
        foreach ($phenotypes as $name => $value) {
            echo "$name=$value ";
        }
        echo "\n";
        if (++$count >= 3) break;
    }
    
    echo "\n=== SUCCESS! ===\n";
    
} catch (Exception $e) {
    echo "\n=== EXCEPTION ===\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
