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
        $sql = "SELECT id, name, creator_id, is_public, is_visible, sex, substrates 
                FROM characters 
                WHERE creator_id = :user_id OR is_public = 1 
                ORDER BY id";
        return $this->db->fetchAll($sql, ['user_id' => $userId]);
    }

    /**
     * Get character by ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM characters WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Create new character
     */
    public function create(string $name, int $creatorId, bool $public = false, bool $visible = false): int
    {
        $sql = "INSERT INTO characters (name, creator_id, is_public, is_visible) 
                VALUES (:name, :creator_id, :is_public, :is_visible)";
        $this->db->execute($sql, [
            'name' => $name,
            'creator_id' => $creatorId,
            'is_public' => $public ? 1 : 0,
            'is_visible' => $visible ? 1 : 0
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update character
     */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id];

        if (isset($data['visible'])) {
            $sets[] = "is_visible = :is_visible";
            $params['is_visible'] = $data['visible'] ? 1 : 0;
        }
        if (isset($data['public'])) {
            $sets[] = "is_public = :is_public";
            $params['is_public'] = $data['public'] ? 1 : 0;
        }
        if (isset($data['sex'])) {
            $sets[] = "sex = :sex";
            $params['sex'] = $data['sex'] ? 1 : 0;
        }
        if (isset($data['substrates'])) {
            $sets[] = "substrates = :substrates";
            $params['substrates'] = (int) $data['substrates'];
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = "updated_at = datetime('now')";
        $sql = "UPDATE characters SET " . implode(', ', $sets) . " WHERE id = :id";
        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Delete character
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM characters WHERE id = :id";
        return $this->db->execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Check if user owns character
     */
    public function isOwner(int $characterId, int $userId): bool
    {
        $sql = "SELECT creator_id FROM characters WHERE id = :id";
        $result = $this->db->fetchOne($sql, ['id' => $characterId]);
        return $result && (int) $result['creator_id'] === $userId;
    }

    /**
     * Get character genes
     */
    public function getGenes(int $characterId): array
    {
        $sql = "SELECT g.id, g.name, g.chromosome, g.position, g.code 
                FROM character_genes cg 
                JOIN genes g ON cg.gene_id = g.id 
                WHERE cg.character_id = :character_id 
                ORDER BY g.id";
        return $this->db->fetchAll($sql, ['character_id' => $characterId]);
    }

    /**
     * Get gene by id
     */
    public function getGeneById(int $geneId): ?array
    {
        $sql = "SELECT id, name, chromosome, position, code FROM genes WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $geneId]);
    }

    /**
     * Get alleles for a gene
     */
    public function getAlleles(int $geneId): array
    {
        $sql = "SELECT a.id, a.name, a.value, a.dominance, a.additive, a.epistasis 
                FROM gene_alleles ga 
                JOIN alleles a ON ga.allele_id = a.id 
                WHERE ga.gene_id = :gene_id 
                ORDER BY a.id";
        return $this->db->fetchAll($sql, ['gene_id' => $geneId]);
    }

    /**
     * Add allele and link to gene
     */
    public function addAllele(int $geneId, string $name, ?float $value = null, ?float $dominance = null, bool $additive = false, ?string $epistasis = null): int
    {
        // If allele is additive, concatenate a leading '1' to the dominance value (e.g. dominance=100, additive=1 -> stored=1100)
        $dominanceToStore = null;
        if ($dominance !== null) {
            if ($additive) {
                // Use string concatenation to preserve decimals if any, then cast to float
                $dominanceToStore = (float) ('1' . strval($dominance));
            } else {
                $dominanceToStore = $dominance;
            }
        }

        $sql = "INSERT INTO alleles (name, value, dominance, additive, epistasis) VALUES (:name, :value, :dominance, :additive, :epistasis)";
        $this->db->execute($sql, [
            'name' => $name,
            'value' => $value,
            'dominance' => $dominanceToStore,
            'additive' => $additive ? 1 : 0,
            'epistasis' => $epistasis
        ]);

        $alleleId = (int) $this->db->lastInsertId();

        $sql = "INSERT INTO gene_alleles (gene_id, allele_id) VALUES (:gene_id, :allele_id)";
        $this->db->execute($sql, ['gene_id' => $geneId, 'allele_id' => $alleleId]);

        return $alleleId;
    }

    /**
     * Remove allele from gene (and allele record)
     */
    public function removeAllele(int $geneId, int $alleleId): bool
    {
        $sql = "DELETE FROM gene_alleles WHERE gene_id = :gene_id AND allele_id = :allele_id";
        $this->db->execute($sql, ['gene_id' => $geneId, 'allele_id' => $alleleId]);

        $sql = "DELETE FROM alleles WHERE id = :id";
        return $this->db->execute($sql, ['id' => $alleleId]) > 0;
    }

    /**
     * Add gene to character
     */
    public function addGene(int $characterId, string $name, string $chr, string $pos, string $code = ''): int
    {
        // Insert gene
        $sql = "INSERT INTO genes (name, chromosome, position, code) VALUES (:name, :chromosome, :position, :code)";
        $this->db->execute($sql, [
            'name' => $name,
            'chromosome' => $chr,
            'position' => $pos,
            'code' => $code
        ]);

        $geneId = (int) $this->db->lastInsertId();

        // Link to character
        $sql = "INSERT INTO character_genes (gene_id, character_id) VALUES (:gene_id, :character_id)";
        $this->db->execute($sql, ['gene_id' => $geneId, 'character_id' => $characterId]);

        return $geneId;
    }

    /**
     * Remove gene from character
     */
    public function removeGene(int $characterId, int $geneId): bool
    {
        // Use transaction to ensure consistent cleanup
        $this->db->beginTransaction();
        try {
            // Remove connections that reference this gene for the character
            $sql = "DELETE FROM connections WHERE transition = :transition AND character_id = :character_id";
            $this->db->execute($sql, ['transition' => $geneId, 'character_id' => $characterId]);

            // Find allele ids linked to this gene
            $sql = "SELECT allele_id FROM gene_alleles WHERE gene_id = :gene_id";
            $alleles = $this->db->fetchAll($sql, ['gene_id' => $geneId]);
            foreach ($alleles as $a) {
                $aid = (int) $a['allele_id'];
                // Remove link rows
                $this->db->execute("DELETE FROM gene_alleles WHERE allele_id = :id", ['id' => $aid]);
                // Remove allele record
                $this->db->execute("DELETE FROM alleles WHERE id = :id", ['id' => $aid]);
            }

            // Remove the character-gene link
            $sql = "DELETE FROM character_genes WHERE gene_id = :gene_id AND character_id = :character_id";
            $this->db->execute($sql, ['gene_id' => $geneId, 'character_id' => $characterId]);

            // Delete gene itself
            $sql = "DELETE FROM genes WHERE id = :id";
            $affected = $this->db->execute($sql, ['id' => $geneId]);

            $this->db->commit();
            return $affected > 0;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Get character connections
     */
    public function getConnections(int $characterId): array
    {
        $sql = "SELECT id, state_a, transition, state_b 
                FROM connections 
                WHERE character_id = :character_id";
        return $this->db->fetchAll($sql, ['character_id' => $characterId]);
    }

    /**
     * Add connection
     */
    public function addConnection(int $characterId, int $stateA, int $transition, int $stateB): int
    {
        $sql = "INSERT INTO connections (state_a, transition, state_b, character_id) 
                VALUES (:state_a, :transition, :state_b, :character_id)";
        $this->db->execute($sql, [
            'state_a' => $stateA,
            'transition' => $transition,
            'state_b' => $stateB,
            'character_id' => $characterId
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Remove connection
     */
    public function removeConnection(int $connectionId): bool
    {
        $sql = "DELETE FROM connections WHERE id = :id";
        return $this->db->execute($sql, ['id' => $connectionId]) > 0;
    }
}
