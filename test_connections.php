<?php
/**
 * Script de prueba para verificar la funcionalidad de conexiones
 * Este script puede ejecutarse desde línea de comandos para probar las funciones
 */

require_once __DIR__ . '/src/bootstrap.php';

use Ngw\Database\Database;
use Ngw\Models\Character;

echo "=== Test de Conexiones ===\n\n";

try {
    // Leer configuración
    $configFile = __DIR__ . '/config/config.ini';
    if (!file_exists($configFile)) {
        $configFile = __DIR__ . '/config/config.ini.example';
    }
    
    if (!file_exists($configFile)) {
        throw new \Exception("Archivo de configuración no encontrado");
    }
    
    $config = parse_ini_file($configFile, true);
    $dbConfig = [
        'driver' => $config['database']['DB_DRIVER'] ?? 'sqlite',
        'path' => __DIR__ . '/' . ($config['database']['DB_PATH'] ?? 'data/ngw.db')
    ];
    
    $db = new Database($dbConfig);
    $characterModel = new Character($db);
    
    // Verificar si existe un carácter de prueba
    $sql = "SELECT id, name, substrates FROM characters LIMIT 1";
    $character = $db->fetchOne($sql, []);
    
    if ($character) {
        echo "Carácter encontrado: {$character['name']} (ID: {$character['id']})\n";
        echo "Sustratos: {$character['substrates']}\n\n";
        
        // Obtener genes del carácter
        $genes = $characterModel->getGenes((int)$character['id']);
        echo "Genes del carácter: " . count($genes) . "\n";
        foreach ($genes as $gene) {
            echo "  - {$gene['name']} (ID: {$gene['id']})\n";
        }
        echo "\n";
        
        // Obtener conexiones existentes
        $connections = $characterModel->getConnections((int)$character['id']);
        echo "Conexiones existentes: " . count($connections) . "\n";
        if (!empty($connections)) {
            foreach ($connections as $conn) {
                $transGene = $characterModel->getGeneById((int)$conn['transition']);
                $geneName = $transGene ? $transGene['name'] : 'Gen #' . $conn['transition'];
                echo "  - S{$conn['state_a']} -> {$geneName} -> S{$conn['state_b']}\n";
            }
        } else {
            echo "  No hay conexiones definidas.\n";
        }
        
        echo "\n=== Métodos disponibles ===\n";
        echo "✓ addConnection(characterId, stateA, transition, stateB)\n";
        echo "✓ getConnections(characterId)\n";
        echo "✓ removeConnection(connectionId)\n";
        
    } else {
        echo "No se encontró ningún carácter en la base de datos.\n";
        echo "Crea un carácter primero desde la interfaz web.\n";
    }
    
    echo "\n=== Test completado ===\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
