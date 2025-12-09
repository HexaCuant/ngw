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
        $sql = "SELECT * FROM proyectos WHERE userid = :userid ORDER BY id";
        return $this->db->fetchAll($sql, ['userid' => $userId]);
    }

    /**
     * Get project by ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM proyectos WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Create new project
     */
    public function create(string $name, int $userId): int
    {
        $sql = "INSERT INTO proyectos (proname, userid) VALUES (:proname, :userid)";
        $this->db->execute($sql, ['proname' => $name, 'userid' => $userId]);
        
        $projectId = (int) $this->db->lastInsertId('proyecto_id');
        
        // Create project directory
        $this->createProjectDirectory($projectId);
        
        return $projectId;
    }

    /**
     * Delete project
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM proyectos WHERE id = :id";
        return $this->db->execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Check if user owns project
     */
    public function isOwner(int $projectId, int $userId): bool
    {
        $sql = "SELECT userid FROM proyectos WHERE id = :id";
        $result = $this->db->fetchOne($sql, ['id' => $projectId]);
        return $result && (int) $result['userid'] === $userId;
    }

    /**
     * Create project directory safely
     */
    private function createProjectDirectory(int $projectId): void
    {
        // Load config for projects path
        $config = parse_ini_file(__DIR__ . '/../../config/config.ini.example');
        $basePath = $config['PROJECTS_PATH'] ?? '/var/www/proyectosGengine';
        
        // Sanitize and validate path
        $projectPath = rtrim($basePath, '/') . '/' . $projectId;
        
        // Ensure we're not creating outside base path
        $realBase = realpath($basePath);
        if ($realBase === false || strpos($projectPath, $realBase) !== 0) {
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
        $sql = "SELECT cp.caracter_id, cp.ambiente, c.name 
                FROM caracteres_proy cp 
                JOIN caracteres c ON cp.caracter_id = c.id 
                WHERE cp.proyecto_id = :proyecto_id 
                ORDER BY cp.caracter_id";
        return $this->db->fetchAll($sql, ['proyecto_id' => $projectId]);
    }

    /**
     * Add character to project
     */
    public function addCharacter(int $projectId, int $characterId, int $ambiente = 0): bool
    {
        $sql = "INSERT INTO caracteres_proy (caracter_id, proyecto_id, ambiente) 
                VALUES (:caracter_id, :proyecto_id, :ambiente)";
        return $this->db->execute($sql, [
            'caracter_id' => $characterId,
            'proyecto_id' => $projectId,
            'ambiente' => $ambiente
        ]) > 0;
    }

    /**
     * Remove character from project
     */
    public function removeCharacter(int $projectId, int $characterId): bool
    {
        $sql = "DELETE FROM caracteres_proy 
                WHERE caracter_id = :caracter_id AND proyecto_id = :proyecto_id";
        return $this->db->execute($sql, [
            'caracter_id' => $characterId,
            'proyecto_id' => $projectId
        ]) > 0;
    }

    /**
     * Update character ambiente in project
     */
    public function updateCharacterAmbiente(int $projectId, int $characterId, int $ambiente): bool
    {
        $sql = "UPDATE caracteres_proy 
                SET ambiente = :ambiente 
                WHERE caracter_id = :caracter_id AND proyecto_id = :proyecto_id";
        return $this->db->execute($sql, [
            'ambiente' => $ambiente,
            'caracter_id' => $characterId,
            'proyecto_id' => $projectId
        ]) > 0;
    }
}
