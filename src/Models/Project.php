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
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM projects WHERE id = :id";
        return $this->db->execute($sql, ['id' => $id]) > 0;
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
        $basePath = $config['PROJECTS_PATH'] ?? '/var/www/proyectosGengine';

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
        $basePath = $config['PROJECTS_PATH'] ?? '/var/www/proyectosGengine';
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
     * Generate POC file for gengine
     */
    public function generatePocFile(int $projectId, int $populationSize, int $generationNumber, string $type = 'random'): string
    {
        $config = parse_ini_file(__DIR__ . '/../../config/config.ini.example');
        $basePath = $config['PROJECTS_PATH'] ?? '/var/www/proyectosGengine';
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

            // Get genes for this character
            $sqlGenes = "SELECT gene_id FROM character_genes WHERE character_id = :character_id ORDER BY gene_id";
            $genes = $this->db->fetchAll($sqlGenes, ['character_id' => $characterId]);

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
            fwrite($fh, "*create\n");
        }
        // TODO: implement cross type when needed

        fwrite($fh, "*end\n");
        fclose($fh);

        return $pocPath;
    }
}
