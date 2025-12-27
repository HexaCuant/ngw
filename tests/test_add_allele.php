<?php
// Simple integration test: add allele (additive) and verify stored dominance
require __DIR__ . '/../vendor/autoload.php';

use Ngw\Database\Database;
use Ngw\Models\Character;

$dbPath = __DIR__ . '/../data/ngw_test.db';
if (file_exists($dbPath)) {
    unlink($dbPath);
}

$config = [
    'driver' => 'sqlite',
    'path' => $dbPath,
];

$db = new Database($config);
$pdo = $db->getConnection();

// Initialize schema
$schemaFile = __DIR__ . '/../database/schema.sql';
if (!file_exists($schemaFile)) {
    echo "ERROR: schema.sql not found\n";
    exit(2);
}
$schema = file_get_contents($schemaFile);
$pdo->exec($schema);

// Insert a user and create character/gene via model
$pdo->exec("INSERT INTO users (username, password, is_approved) VALUES ('testuser', '', 1)");
$userId = (int) $pdo->lastInsertId();

$characterModel = new Character($db);
$charId = $characterModel->create('TestChar', $userId, false, false);
$geneId = $characterModel->addGene($charId, 'TestGene', 'A', '1', '');

// Add allele with additive = true and dominance = 100
$alleleId = $characterModel->addAllele($geneId, 'Allele1', null, 100, true, null);

// Fetch allele row
$stmt = $pdo->prepare('SELECT id, name, dominance, additive FROM alleles WHERE id = :id');
$stmt->execute(['id' => $alleleId]);
$allele = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$allele) {
    echo "FAILED: allele not found after insert\n";
    exit(2);
}

$storedDominance = (string) $allele['dominance'];
// Expect leading '1' concatenated: e.g. '1100' (may appear as '1100' or '1100.0')
if (strpos($storedDominance, '1100') !== false) {
    echo "OK: Inserted allele id: {$allele['id']}; dominance: {$allele['dominance']}\n";
    exit(0);
} else {
    echo "FAILED: dominance stored was {$allele['dominance']} expected 1100 prefix\n";
    exit(3);
}
