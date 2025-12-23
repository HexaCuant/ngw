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
}
