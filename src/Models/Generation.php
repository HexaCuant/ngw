<?php

namespace Ngw\Models;

use Ngw\Database\Database;

/**
 * Generation model
 */
class Generation
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get next generation number for a project
     */
    public function getNextGenerationNumber(int $projectId): int
    {
        $sql = "SELECT MAX(generation_number) as max_gen FROM generations WHERE project_id = :project_id";
        $result = $this->db->fetchOne($sql, ['project_id' => $projectId]);
        return ($result['max_gen'] ?? 0) + 1;
    }

    /**
     * Create new generation record
     */
    public function create(int $projectId, int $generationNumber, int $populationSize, string $type = 'random'): int
    {
        $sql = "INSERT INTO generations (project_id, generation_number, population_size, type) 
                VALUES (:project_id, :generation_number, :population_size, :type)";
        
        $this->db->execute($sql, [
            'project_id' => $projectId,
            'generation_number' => $generationNumber,
            'population_size' => $populationSize,
            'type' => $type
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get generations for a project
     */
    public function getProjectGenerations(int $projectId): array
    {
        $sql = "SELECT * FROM generations WHERE project_id = :project_id ORDER BY generation_number DESC";
        return $this->db->fetchAll($sql, ['project_id' => $projectId]);
    }

    /**
     * Add parentals for a target generation
     * individualIds: array of individual IDs from parentGenerationNumber
     */
    public function addParentals(int $projectId, int $targetGeneration, int $parentGenerationNumber, array $individualIds): int
    {
        $inserted = 0;
        foreach ($individualIds as $indiv) {
            $sqlCheck = "SELECT id FROM parentals WHERE project_id = :project_id AND generation_number = :generation_number AND individual_id = :individual_id AND parent_generation_number = :parent_generation_number";
            $exists = $this->db->fetchOne($sqlCheck, [
                'project_id' => $projectId,
                'generation_number' => $targetGeneration,
                'individual_id' => $indiv,
                'parent_generation_number' => $parentGenerationNumber
            ]);
            if ($exists) continue;

            $sql = "INSERT INTO parentals (project_id, generation_number, individual_id, parent_generation_number) VALUES (:project_id, :generation_number, :individual_id, :parent_generation_number)";
            $this->db->execute($sql, [
                'project_id' => $projectId,
                'generation_number' => $targetGeneration,
                'individual_id' => $indiv,
                'parent_generation_number' => $parentGenerationNumber
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Get parentals for a target generation
     */
    public function getParentals(int $projectId, int $targetGeneration): array
    {
        $sql = "SELECT individual_id, parent_generation_number FROM parentals WHERE project_id = :project_id AND generation_number = :generation_number ORDER BY parent_generation_number, individual_id";
        return $this->db->fetchAll($sql, ['project_id' => $projectId, 'generation_number' => $targetGeneration]);
    }

    /**
     * Delete a parental
     */
    public function deleteParental(int $projectId, int $targetGeneration, int $parentGenerationNumber, int $individualId): bool
    {
        $sql = "DELETE FROM parentals WHERE project_id = :project_id AND generation_number = :generation_number AND parent_generation_number = :parent_generation_number AND individual_id = :individual_id";
        return $this->db->execute($sql, [
            'project_id' => $projectId,
            'generation_number' => $targetGeneration,
            'parent_generation_number' => $parentGenerationNumber,
            'individual_id' => $individualId
        ]) > 0;
    }

    /**
     * Execute gengine for a project
     */
    public function executeGengine(int $projectId): array
    {
        $config = parse_ini_file(__DIR__ . '/../../config/config.ini.example');
        $gengineScript = $config['GENGINE_SCRIPT'] ?? '/srv/http/gw/bin/gen2web';
        $projectsPath = $config['PROJECTS_PATH'] ?? '/var/www/proyectosGengine';

        // Execute gengine with proper HOME environment and redirect all output to /dev/null
        $command = 'HOME=' . escapeshellarg($projectsPath) . ' ' . escapeshellcmd($gengineScript) . ' ' . escapeshellarg($projectId) . ' > /dev/null 2>&1';
        $returnCode = 0;
        exec($command, $output, $returnCode);

        return [
            'success' => $returnCode === 0,
            'return_code' => $returnCode,
            'output' => ''
        ];
    }

    /**
     * Parse generation output file
     */
    public function parseGenerationOutput(int $projectId, int $generationNumber): array
    {
        $config = parse_ini_file(__DIR__ . '/../../config/config.ini.example');
        $basePath = $config['PROJECTS_PATH'] ?? '/var/www/proyectosGengine';
        $outputFile = rtrim($basePath, '/') . '/' . $projectId . '/' . $projectId . '.dat' . $generationNumber;

        if (!file_exists($outputFile)) {
            throw new \RuntimeException("Output file not found: " . $outputFile);
        }

        // Get project characters ordered by character ID
        $sql = "SELECT c.id, c.name 
                FROM characters c
                JOIN project_characters pc ON c.id = pc.character_id
                WHERE pc.project_id = :project_id
                ORDER BY c.id ASC";
            $characters = $this->db->fetchAll($sql, ['project_id' => $projectId]);
            $characterNames = [];
            foreach ($characters as $char) {
                $characterNames[$char['id']] = $char['name'];
            }

        $individuals = [];

        $fh = fopen($outputFile, 'r');
        if (!$fh) {
            throw new \RuntimeException("Cannot open output file");
        }

        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }

            // Parse line format: id=char_id:phenotype:char_id:phenotype:...$
            $parts = explode('=', $line);
            
            if (count($parts) < 2) {
                continue;
            }

            $individualId = (int) $parts[0];
            
            // Skip invalid lines
            if ($individualId <= 0 || substr($parts[0], 0, 1) === 'n') {
                continue;
            }

            $phenotypeData = explode(':', $parts[1]);
            $rawPhenotypes = [];
            
            $i = 0;
            while (isset($phenotypeData[$i]) && $phenotypeData[$i] !== '$') {
                $characterId = (int) $phenotypeData[$i];
                $phenotypeValue = isset($phenotypeData[$i + 1]) ? (float) $phenotypeData[$i + 1] : 0;
                $rawPhenotypes[$characterId] = $phenotypeValue;
                $i += 2;
            }

            // Build phenotypes array in character ID order
                // Build phenotypes array preserving the order in the file
                $phenotypes = [];
                foreach ($rawPhenotypes as $charId => $value) {
                    $name = $characterNames[$charId] ?? ('Caracter ' . $charId);
                    $phenotypes[$name] = $value;
                }

            $individuals[$individualId] = $phenotypes;
        }

        fclose($fh);

        // Sort individuals by first phenotype value (descending), then by ID (ascending)
        uksort($individuals, function ($idA, $idB) use ($individuals) {
            $phenotypesA = array_values($individuals[$idA]);
            $phenotypesB = array_values($individuals[$idB]);
            
            // Compare by first phenotype value (descending)
            $cmp = ($phenotypesB[0] ?? 0) <=> ($phenotypesA[0] ?? 0);
            
            // If equal, compare by ID (ascending)
            return $cmp !== 0 ? $cmp : $idA <=> $idB;
        });

        return $individuals;
    }

    /**
     * Calculate mean and variance for phenotypes
     */
    public function calculateStatistics(array $individuals): array
    {
        if (empty($individuals)) {
            return [];
        }

        $stats = [];
        $sums = [];
        $sumsSq = [];
        $counts = [];

        foreach ($individuals as $indiv) {
            // Handle both [id => phenotypes] and ['id' => id, 'phenotypes' => phenotypes] formats
            $phenotypes = isset($indiv['phenotypes']) ? $indiv['phenotypes'] : $indiv;
            
            foreach ($phenotypes as $name => $value) {
                if (!isset($sums[$name])) {
                    $sums[$name] = 0;
                    $sumsSq[$name] = 0;
                    $counts[$name] = 0;
                }
                $sums[$name] += $value;
                $sumsSq[$name] += ($value * $value);
                $counts[$name]++;
            }
        }

        foreach ($sums as $name => $sum) {
            $n = $counts[$name];
            if ($n > 0) {
                $mean = $sum / $n;
                // Population variance: (sum(x^2) / n) - mean^2
                $variance = ($sumsSq[$name] / $n) - ($mean * $mean);
                $stats[$name] = [
                    'mean' => round($mean, 4),
                    'variance' => round($variance, 4)
                ];
            }
        }

        return $stats;
    }

    /**
     * Get summary data for all generations in a project
     */
    public function getProjectSummary(int $projectId): array
    {
        $generations = $this->getProjectGenerations($projectId);
        // Sort by generation number ascending for the summary table
        usort($generations, function($a, $b) {
            return $a['generation_number'] <=> $b['generation_number'];
        });

        $summary = [];
        foreach ($generations as $gen) {
            try {
                $genNum = (int)$gen['generation_number'];
                $individuals = $this->parseGenerationOutput($projectId, $genNum);
                $stats = $this->calculateStatistics($individuals);

                // Calculate parental statistics if it's a cross generation
                $parentalStats = [];
                $parentalCount = 0;
                $parentalSourceGen = null;

                if ($gen['type'] === 'cross') {
                    $parentals = $this->getParentals($projectId, $genNum);
                    $parentalCount = count($parentals);
                    $groupedParentals = [];
                    foreach ($parentals as $p) {
                        $groupedParentals[$p['parent_generation_number']][] = (int)$p['individual_id'];
                    }

                    if (!empty($groupedParentals)) {
                        $sourceGens = array_keys($groupedParentals);
                        $parentalSourceGen = implode(', ', $sourceGens);

                        $parentalIndividuals = [];
                        foreach ($groupedParentals as $pgNum => $ids) {
                            try {
                                $pgIndividuals = $this->parseGenerationOutput($projectId, (int)$pgNum);
                                foreach ($ids as $id) {
                                    if (isset($pgIndividuals[$id])) {
                                        $parentalIndividuals[] = $pgIndividuals[$id];
                                    }
                                }
                            } catch (\Exception $e) {
                                // Skip if parent generation file not found
                            }
                        }
                        if (!empty($parentalIndividuals)) {
                            $parentalStats = $this->calculateStatistics($parentalIndividuals);
                        }
                    }
                }
                
                $summary[] = [
                    'generation_number' => $gen['generation_number'],
                    'type' => $gen['type'],
                    'population_size' => $gen['population_size'],
                    'created_at' => $gen['created_at'],
                    'stats' => $stats,
                    'parental_stats' => $parentalStats,
                    'parental_count' => $parentalCount,
                    'parental_source_gen' => $parentalSourceGen
                ];
            } catch (\Exception $e) {
                // Skip if file not found or error parsing
                $summary[] = [
                    'generation_number' => $gen['generation_number'],
                    'type' => $gen['type'],
                    'population_size' => $gen['population_size'],
                    'created_at' => $gen['created_at'],
                    'stats' => [],
                    'parental_stats' => [],
                    'error' => $e->getMessage()
                ];
            }
        }

        return $summary;
    }

    /**
     * Get generation by number
     */
    public function getByNumber(int $projectId, int $generationNumber): ?array
    {
        $sql = "SELECT * FROM generations WHERE project_id = :project_id AND generation_number = :generation_number";
        $result = $this->db->fetchOne($sql, [
            'project_id' => $projectId,
            'generation_number' => $generationNumber
        ]);
        return $result ?: null;
    }

    /**
     * Delete generation by project and generation number
     */
    public function delete(int $projectId, int $generationNumber): bool
    {
        // Delete parentals if any
        $sql = "DELETE FROM parentals WHERE project_id = :project_id AND generation_number = :generation_number";
        $this->db->execute($sql, [
            'project_id' => $projectId,
            'generation_number' => $generationNumber
        ]);

        // Delete generation record
        $sql = "DELETE FROM generations WHERE project_id = :project_id AND generation_number = :generation_number";
        return $this->db->execute($sql, [
            'project_id' => $projectId,
            'generation_number' => $generationNumber
        ]) > 0;
    }
}
