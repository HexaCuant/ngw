<?php

namespace Ngw\Models;

use Ngw\Database\Database;

/**
 * Project model
 */
class Project
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get all projects for a user
     */
    public function getUserProjects(int $userId): array
    {
        $sql = "SELECT * FROM projects WHERE user_id = :user_id ORDER BY id";
        return $this->db->fetchAll($sql, ['user_id' => $userId]);
    }

    /**
     * Get project by ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM projects WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Create new project
     */
    public function create(string $name, int $userId, string $description = ''): int
    {
        // Validate base path BEFORE creating DB record
        $this->validateProjectsBasePath();

        $sql = "INSERT INTO projects (name, description, user_id) VALUES (:name, :description, :user_id)";
        $this->db->execute($sql, ['name' => $name, 'description' => $description, 'user_id' => $userId]);

        $projectId = (int) $this->db->lastInsertId();

        // Create project directory
        $this->createProjectDirectory($projectId);

        return $projectId;
    }

    /**
     * Delete project
     * @param int $id Project ID
     * @param bool $deleteDirectory Whether to also delete the project directory
     * @return bool True if project was deleted
     */
    public function delete(int $id, bool $deleteDirectory = false): bool
    {
        // Delete from database first
        $sql = "DELETE FROM projects WHERE id = :id";
        $deleted = $this->db->execute($sql, ['id' => $id]) > 0;
        
        // If requested and DB delete succeeded, also delete directory
        if ($deleted && $deleteDirectory) {
            $this->deleteProjectDirectory($id);
        }
        
        return $deleted;
    }
    
    /**
     * Delete project directory from filesystem
     */
    private function deleteProjectDirectory(int $projectId): bool
    {
        $config = parse_ini_file(__DIR__ . '/../../config/config.ini');
        $basePath = $config['PROJECTS_PATH'] ?? '/var/www/proyectosNGengine';
        $projectPath = rtrim($basePath, '/') . '/' . $projectId;
        
        if (!is_dir($projectPath)) {
            return true; // Directory doesn't exist, nothing to delete
        }
        
        // Recursively delete directory
        return $this->recursiveDelete($projectPath);
    }
    
    /**
     * Recursively delete a directory and its contents
     */
    private function recursiveDelete(string $path): bool
    {
        if (!is_dir($path)) {
            return unlink($path);
        }
        
        $items = array_diff(scandir($path), ['.', '..']);
        foreach ($items as $item) {
            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->recursiveDelete($itemPath);
            } else {
                unlink($itemPath);
            }
        }
        
        return rmdir($path);
    }

    /**
     * Check if user owns project
     */
    public function isOwner(int $projectId, int $userId): bool
    {
        $sql = "SELECT user_id FROM projects WHERE id = :id";
        $result = $this->db->fetchOne($sql, ['id' => $projectId]);
        return $result && (int) $result['user_id'] === $userId;
    }

    /**
     * Validate projects base path exists
     */
    private function validateProjectsBasePath(): void
    {
        $config = parse_ini_file(__DIR__ . '/../../config/config.ini.example');
        $basePath = $config['PROJECTS_PATH'] ?? '/var/www/proyectosNGengine';

        if (!is_dir($basePath)) {
            throw new \RuntimeException("Base projects path does not exist: " . $basePath);
        }

        if (realpath($basePath) === false) {
            throw new \RuntimeException("Cannot resolve base projects path");
        }
    }

    /**
     * Create project directory safely
     */
    private function createProjectDirectory(int $projectId): void
    {
        $config = parse_ini_file(__DIR__ . '/../../config/config.ini.example');
        $basePath = $config['PROJECTS_PATH'] ?? '/var/www/proyectosNGengine';
        $projectPath = rtrim($basePath, '/') . '/' . $projectId;

        $realBase = realpath($basePath);
        
        // Ensure we're not creating outside base path
        $realProject = realpath(dirname($projectPath));
        if ($realProject === false || strpos($realProject . '/' . basename($projectPath), $realBase) !== 0) {
            throw new \RuntimeException("Invalid project path");
        }

        if (!is_dir($projectPath)) {
            mkdir($projectPath, 0755, true);
        }
    }

    /**
     * Get project characters
     */
    public function getCharacters(int $projectId): array
    {
        $sql = "SELECT pc.character_id, pc.environment, c.name 
                FROM project_characters pc 
                JOIN characters c ON pc.character_id = c.id 
                WHERE pc.project_id = :project_id 
                ORDER BY pc.character_id";
        return $this->db->fetchAll($sql, ['project_id' => $projectId]);
    }

    /**
     * Add character to project
     */
    public function addCharacter(int $projectId, int $characterId, int $environment = 0): bool
    {
        $sql = "INSERT INTO project_characters (character_id, project_id, environment) 
                VALUES (:character_id, :project_id, :environment)";
        return $this->db->execute($sql, [
            'character_id' => $characterId,
            'project_id' => $projectId,
            'environment' => $environment
        ]) > 0;
    }

    /**
     * Remove character from project
     */
    public function removeCharacter(int $projectId, int $characterId): bool
    {
        $sql = "DELETE FROM project_characters 
                WHERE character_id = :character_id AND project_id = :project_id";
        return $this->db->execute($sql, [
            'character_id' => $characterId,
            'project_id' => $projectId
        ]) > 0;
    }

    /**
     * Update character environment in project
     */
    public function updateCharacterEnvironment(int $projectId, int $characterId, int $environment): bool
    {
        $sql = "UPDATE project_characters 
                SET environment = :environment 
                WHERE character_id = :character_id AND project_id = :project_id";
        return $this->db->execute($sql, [
            'environment' => $environment,
            'character_id' => $characterId,
            'project_id' => $projectId
        ]) > 0;
    }

    /**
     * Validate project genes configuration
     * Ensures all genes have at least one allele
     */
    public function validateProjectGenes(int $projectId): array
    {
        $errors = [];
        
        // Get project characters with their data
        $characters = $this->getCharacters($projectId);
        
        foreach ($characters as $char) {
            $characterId = $char['character_id'];
            
            // Get character name for error messages
            $sqlCharName = "SELECT name FROM characters WHERE id = :id";
            $charName = $this->db->fetchOne($sqlCharName, ['id' => $characterId])['name'] ?? 'Unknown';
            
            // Get genes for this character
            $sqlGenes = "SELECT id, name FROM genes WHERE id IN (
                SELECT gene_id FROM character_genes WHERE character_id = :character_id
            ) ORDER BY id";
            $genes = $this->db->fetchAll($sqlGenes, ['character_id' => $characterId]);

            foreach ($genes as $gene) {
                $geneId = $gene['id'];
                $geneName = $gene['name'] ?? 'Unknown';

                // Check if gene has alleles
                $sqlAlleles = "SELECT COUNT(*) as count FROM gene_alleles WHERE gene_id = :gene_id";
                $result = $this->db->fetchOne($sqlAlleles, ['gene_id' => $geneId]);
                $alleleCount = $result['count'] ?? 0;

                if ($alleleCount === 0) {
                    $errors[] = "Gene '$geneName' in character '$charName' has no alleles defined. Please add at least one allele before generating generations.";
                }
            }
        }

        return $errors;
    }

    /**
     * Generate POC file for gengine
     */
    public function generatePocFile(int $projectId, int $populationSize, int $generationNumber, string $type = 'random'): string
    {
        // Validate project genes before generating POC file
        $validationErrors = $this->validateProjectGenes($projectId);
        if (!empty($validationErrors)) {
            throw new \RuntimeException(implode("\n", $validationErrors));
        }

        $config = parse_ini_file(__DIR__ . '/../../config/config.ini.example');
        $basePath = $config['PROJECTS_PATH'] ?? '/var/www/proyectosNGengine';
        $projectPath = rtrim($basePath, '/') . '/' . $projectId;
        $pocPath = $projectPath . '/' . $projectId . '.poc';

        if (!is_dir($projectPath)) {
            throw new \RuntimeException("Project directory does not exist");
        }

        $fh = fopen($pocPath, 'w');
        if (!$fh) {
            throw new \RuntimeException("Cannot create POC file");
        }

        // Header
        fwrite($fh, "#file created by GenWeb NG\n");
        fwrite($fh, "n" . $populationSize . "\n");
        fwrite($fh, "i" . $generationNumber . "\n");
        fwrite($fh, "*characters\n");

        // Get project characters with their data
        $characters = $this->getCharacters($projectId);
        
        foreach ($characters as $char) {
            $characterId = $char['character_id'];
            $environment = $char['environment'];

            // Get character sex
            $sql = "SELECT sex FROM characters WHERE id = :id";
            $charData = $this->db->fetchOne($sql, ['id' => $characterId]);
            $sex = $charData['sex'] ?? 0;

            // Write character line
            fwrite($fh, $characterId . ":" . $environment . ":");
            if ($sex == 1) {
                fwrite($fh, "0:");
            }

            // Get genes for this character, ordered by their position in the Petri net
            // (i.e., by the state_a from which they receive input in connections)
            $sqlGenes = "SELECT DISTINCT cg.gene_id, COALESCE(MIN(conn.state_a), 999999) as min_state_a
                         FROM character_genes cg
                         LEFT JOIN connections conn ON conn.transition = cg.gene_id AND conn.character_id = :character_id
                         WHERE cg.character_id = :character_id2
                         GROUP BY cg.gene_id
                         ORDER BY min_state_a, cg.gene_id";
            $genes = $this->db->fetchAll($sqlGenes, ['character_id' => $characterId, 'character_id2' => $characterId]);

            foreach ($genes as $gene) {
                $geneId = $gene['gene_id'];

                // Get gene data
                $sqlGeneData = "SELECT chromosome, position, code FROM genes WHERE id = :id";
                $geneData = $this->db->fetchOne($sqlGeneData, ['id' => $geneId]);
                
                // Extract numeric values for POC format
                // Chromosome might be "1 (A, B)" - extract just the number
                $chr = $geneData['chromosome'] ?? '';
                if (preg_match('/^(\d+)/', $chr, $matches)) {
                    $chr = $matches[1];
                } else {
                    $chr = '1'; // Default
                }
                
                // Position might be text - convert to numeric
                $pos = $geneData['position'] ?? '';
                if (!is_numeric($pos)) {
                    $pos = preg_match('/[\d.]+/', $pos, $matches) ? $matches[0] : '1';
                }
                
                // Code must be numeric - use default if empty
                $cod = $geneData['code'] ?? '';
                if (empty($cod) || !is_numeric($cod)) {
                    $cod = '3'; // Default code for additive genes
                }

                fwrite($fh, "\n" . $geneId . "=" . $chr . ":" . $pos . ":" . $cod . ":");

                // Get alleles for this gene
                $sqlAlleles = "SELECT allele_id FROM gene_alleles WHERE gene_id = :gene_id ORDER BY allele_id";
                $alleles = $this->db->fetchAll($sqlAlleles, ['gene_id' => $geneId]);

                foreach ($alleles as $allele) {
                    $alleleId = $allele['allele_id'];

                    // Get allele data
                    $sqlAlleleData = "SELECT value, dominance FROM alleles WHERE id = :id";
                    $alleleData = $this->db->fetchOne($sqlAlleleData, ['id' => $alleleId]);

                    $value = $alleleData['value'] ?? 0;
                    $dominance = $alleleData['dominance'] ?? 0;

                    fwrite($fh, $alleleId . ":" . $value . ":" . $dominance . ":");
                }

                fwrite($fh, "&:");
            }

            // States
            fwrite($fh, "\n$=\nstates");
            
            $sqlSubstrates = "SELECT substrates FROM characters WHERE id = :id";
            $charSubstrates = $this->db->fetchOne($sqlSubstrates, ['id' => $characterId]);
            $numStates = $charSubstrates['substrates'] ?? 1;

            fwrite($fh, "\n0=1");
            for ($i = 1; $i < $numStates; $i++) {
                fwrite($fh, "\n" . $i . "=0");
            }

            // Connections
            fwrite($fh, "\n$=\nconnections");

            $sqlConnections = "SELECT state_a, transition, state_b FROM connections WHERE character_id = :character_id";
            $connections = $this->db->fetchAll($sqlConnections, ['character_id' => $characterId]);

            foreach ($connections as $conn) {
                fwrite($fh, "\n" . $conn['state_a'] . "=" . $conn['transition'] . "=" . $conn['state_b']);
            }

            fwrite($fh, "\n$=\n");
        }

        fwrite($fh, "@:\n");

        // Type of generation
        if ($type == 'random') {
            // Add allele frequencies if configured
            $frequencies = $this->getAllAlleleFrequenciesForProject($projectId);
            if (!empty($frequencies)) {
                fwrite($fh, "*frequencies\n");
                // Format: allele_id:frequency:allele_id:frequency:...
                $freqParts = [];
                foreach ($frequencies as $alleleId => $freq) {
                    $freqParts[] = $alleleId . ":" . $freq;
                }
                fwrite($fh, implode(":", $freqParts) . ":\n");
                fwrite($fh, "\$\n");
            }
            
            fwrite($fh, "*create\n");
        } elseif ($type == 'cross') {
            // For cross, read parent generations and parentals from DB
            // distinct parent_generation_number
            $sqlRead = "SELECT DISTINCT parent_generation_number FROM parentals WHERE project_id = :project_id AND generation_number = :generation_number ORDER BY parent_generation_number";
            $reads = $this->db->fetchAll($sqlRead, ['project_id' => $projectId, 'generation_number' => $generationNumber]);
            foreach ($reads as $r) {
                fwrite($fh, "*read\n");
                fwrite($fh, $r['parent_generation_number'] . "\n");
            }

            // Build cross line: indiv,parent_gen:...=,pop:
            $sqlCross = "SELECT individual_id, parent_generation_number FROM parentals WHERE project_id = :project_id AND generation_number = :generation_number ORDER BY parent_generation_number, individual_id";
            $rows = $this->db->fetchAll($sqlCross, ['project_id' => $projectId, 'generation_number' => $generationNumber]);
            $line = "";
            foreach ($rows as $row) {
                $line .= $row['individual_id'] . "," . $row['parent_generation_number'] . ":";
            }
            fwrite($fh, "*cross\n");
            fwrite($fh, $line . "=," . $populationSize . ":\n");
        }

        fwrite($fh, "*end\n");
        fclose($fh);

        return $pocPath;
    }

    /**
     * Get allele frequencies for a project and character
     * Returns array with allele_id => frequency
     */
    public function getAlleleFrequencies(int $projectId, int $characterId): array
    {
        // Get all allele IDs for this character's genes via character_genes and gene_alleles
        $sql = "SELECT a.id as allele_id, paf.frequency 
                FROM alleles a
                JOIN gene_alleles ga ON a.id = ga.allele_id
                JOIN character_genes cg ON ga.gene_id = cg.gene_id
                LEFT JOIN project_allele_frequencies paf ON paf.allele_id = a.id AND paf.project_id = :project_id
                WHERE cg.character_id = :character_id";
        
        $results = $this->db->fetchAll($sql, [
            'project_id' => $projectId,
            'character_id' => $characterId
        ]);
        
        $frequencies = [];
        foreach ($results as $row) {
            $frequencies[$row['allele_id']] = $row['frequency'];
        }
        
        return $frequencies;
    }

    /**
     * Save allele frequencies for a project
     * @param int $projectId
     * @param array $frequencies Array of ['allele_id' => int, 'frequency' => float]
     */
    public function saveAlleleFrequencies(int $projectId, array $frequencies): void
    {
        // Delete existing frequencies for this project
        $deleteIds = array_column($frequencies, 'allele_id');
        if (!empty($deleteIds)) {
            // Delete only the alleles we're updating
            $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $sql = "DELETE FROM project_allele_frequencies WHERE project_id = ? AND allele_id IN ($placeholders)";
            $params = array_merge([$projectId], $deleteIds);
            $this->db->execute($sql, $params);
        }
        
        // Insert new frequencies
        foreach ($frequencies as $freq) {
            $sql = "INSERT INTO project_allele_frequencies (project_id, allele_id, frequency) 
                    VALUES (:project_id, :allele_id, :frequency)";
            $this->db->execute($sql, [
                'project_id' => $projectId,
                'allele_id' => (int) $freq['allele_id'],
                'frequency' => (float) $freq['frequency']
            ]);
        }
    }

    /**
     * Get all allele frequencies for a project (for POC file generation)
     * Returns array with allele_id => frequency for all configured frequencies
     */
    public function getAllAlleleFrequenciesForProject(int $projectId): array
    {
        $sql = "SELECT allele_id, frequency FROM project_allele_frequencies WHERE project_id = :project_id";
        $results = $this->db->fetchAll($sql, ['project_id' => $projectId]);
        
        $frequencies = [];
        foreach ($results as $row) {
            $frequencies[(int)$row['allele_id']] = (float)$row['frequency'];
        }
        
        return $frequencies;
    }
}
