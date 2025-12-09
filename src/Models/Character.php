<?php

namespace Ngw\Models;

use Ngw\Database\Database;

/**
 * Character model
 */
class Character
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get all characters for a user (owned or public)
     */
    public function getAvailableCharacters(int $userId): array
    {
        $sql = "SELECT id, name, creatorid, public, visible, sexo, sustratos 
                FROM caracteres 
                WHERE creatorid = :userid OR public = true 
                ORDER BY id";
        return $this->db->fetchAll($sql, ['userid' => $userId]);
    }

    /**
     * Get character by ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM caracteres WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Create new character
     */
    public function create(string $name, int $creatorId, bool $public = false, bool $visible = false): int
    {
        $sql = "INSERT INTO caracteres (name, creatorid, public, visible) 
                VALUES (:name, :creatorid, :public, :visible)";
        $this->db->execute($sql, [
            'name' => $name,
            'creatorid' => $creatorId,
            'public' => $public ? 't' : 'f',
            'visible' => $visible ? 't' : 'f'
        ]);
        
        return (int) $this->db->lastInsertId('caracteres_id_seq');
    }

    /**
     * Update character
     */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id];
        
        if (isset($data['visible'])) {
            $sets[] = "visible = :visible";
            $params['visible'] = $data['visible'] ? 't' : 'f';
        }
        if (isset($data['public'])) {
            $sets[] = "public = :public";
            $params['public'] = $data['public'] ? 't' : 'f';
        }
        if (isset($data['sexo'])) {
            $sets[] = "sexo = :sexo";
            $params['sexo'] = $data['sexo'] ? 't' : 'f';
        }
        if (isset($data['sustratos'])) {
            $sets[] = "sustratos = :sustratos";
            $params['sustratos'] = (int) $data['sustratos'];
        }
        
        if (empty($sets)) {
            return false;
        }
        
        $sql = "UPDATE caracteres SET " . implode(', ', $sets) . " WHERE id = :id";
        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Delete character
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM caracteres WHERE id = :id";
        return $this->db->execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Check if user owns character
     */
    public function isOwner(int $characterId, int $userId): bool
    {
        $sql = "SELECT creatorid FROM caracteres WHERE id = :id";
        $result = $this->db->fetchOne($sql, ['id' => $characterId]);
        return $result && (int) $result['creatorid'] === $userId;
    }

    /**
     * Get character genes
     */
    public function getGenes(int $characterId): array
    {
        $sql = "SELECT g.idglobal, g.name, g.chr, g.pos, g.cod 
                FROM genes_car gc 
                JOIN genes g ON gc.gen_id = g.idglobal 
                WHERE gc.car_id = :car_id 
                ORDER BY g.idglobal";
        return $this->db->fetchAll($sql, ['car_id' => $characterId]);
    }

    /**
     * Add gene to character
     */
    public function addGene(int $characterId, string $name, string $chr, string $pos, string $code = ''): int
    {
        // Insert gene
        $sql = "INSERT INTO genes (name, chr, pos, cod) VALUES (:name, :chr, :pos, :cod)";
        $this->db->execute($sql, [
            'name' => $name,
            'chr' => $chr,
            'pos' => $pos,
            'cod' => $code
        ]);
        
        $geneId = (int) $this->db->lastInsertId('gen_id');
        
        // Link to character
        $sql = "INSERT INTO genes_car (gen_id, car_id) VALUES (:gen_id, :car_id)";
        $this->db->execute($sql, ['gen_id' => $geneId, 'car_id' => $characterId]);
        
        return $geneId;
    }

    /**
     * Remove gene from character
     */
    public function removeGene(int $characterId, int $geneId): bool
    {
        // Delete from genes_car
        $sql = "DELETE FROM genes_car WHERE gen_id = :gen_id AND car_id = :car_id";
        $this->db->execute($sql, ['gen_id' => $geneId, 'car_id' => $characterId]);
        
        // Delete gene itself
        $sql = "DELETE FROM genes WHERE idglobal = :id";
        return $this->db->execute($sql, ['id' => $geneId]) > 0;
    }

    /**
     * Get character connections
     */
    public function getConnections(int $characterId): array
    {
        $sql = "SELECT id, estadoa, transicion, estadob 
                FROM conexiones 
                WHERE car_id = :car_id";
        return $this->db->fetchAll($sql, ['car_id' => $characterId]);
    }

    /**
     * Add connection
     */
    public function addConnection(int $characterId, int $stateA, int $transition, int $stateB): int
    {
        $sql = "INSERT INTO conexiones (estadoa, transicion, estadob, car_id) 
                VALUES (:estadoa, :transicion, :estadob, :car_id)";
        $this->db->execute($sql, [
            'estadoa' => $stateA,
            'transicion' => $transition,
            'estadob' => $stateB,
            'car_id' => $characterId
        ]);
        
        return (int) $this->db->lastInsertId('conexiones_id_seq');
    }

    /**
     * Remove connection
     */
    public function removeConnection(int $connectionId): bool
    {
        $sql = "DELETE FROM conexiones WHERE id = :id";
        return $this->db->execute($sql, ['id' => $connectionId]) > 0;
    }
}
