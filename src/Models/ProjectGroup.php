<?php

namespace Ngw\Models;

use Ngw\Database\Database;

/**
 * ProjectGroup model for organizing projects into groups/folders
 */
class ProjectGroup
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get all groups for a user
     */
    public function getUserGroups(int $userId): array
    {
        $sql = "SELECT g.*, 
                       (SELECT COUNT(*) FROM projects p WHERE p.group_id = g.id) as project_count
                FROM project_groups g 
                WHERE g.user_id = :user_id 
                ORDER BY g.sort_order, g.name";
        return $this->db->fetchAll($sql, ['user_id' => $userId]);
    }

    /**
     * Get group by ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM project_groups WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Create new group
     */
    public function create(string $name, int $userId, string $color = '#6366f1'): int
    {
        // Get next sort order
        $sql = "SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order 
                FROM project_groups WHERE user_id = :user_id";
        $result = $this->db->fetchOne($sql, ['user_id' => $userId]);
        $sortOrder = $result['next_order'] ?? 1;

        $sql = "INSERT INTO project_groups (name, user_id, color, sort_order) 
                VALUES (:name, :user_id, :color, :sort_order)";
        $this->db->execute($sql, [
            'name' => $name,
            'user_id' => $userId,
            'color' => $color,
            'sort_order' => $sortOrder
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update group name and/or color
     */
    public function update(int $id, string $name, string $color = null): bool
    {
        if ($color !== null) {
            $sql = "UPDATE project_groups SET name = :name, color = :color WHERE id = :id";
            return $this->db->execute($sql, ['name' => $name, 'color' => $color, 'id' => $id]) > 0;
        } else {
            $sql = "UPDATE project_groups SET name = :name WHERE id = :id";
            return $this->db->execute($sql, ['name' => $name, 'id' => $id]) > 0;
        }
    }

    /**
     * Delete group (projects will have group_id set to NULL)
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM project_groups WHERE id = :id";
        return $this->db->execute($sql, ['id' => $id]) > 0;
    }

    /**
     * Check if user owns group
     */
    public function isOwner(int $groupId, int $userId): bool
    {
        $sql = "SELECT user_id FROM project_groups WHERE id = :id";
        $result = $this->db->fetchOne($sql, ['id' => $groupId]);
        return $result && (int) $result['user_id'] === $userId;
    }

    /**
     * Get projects in a specific group
     */
    public function getGroupProjects(int $groupId): array
    {
        $sql = "SELECT * FROM projects WHERE group_id = :group_id ORDER BY name";
        return $this->db->fetchAll($sql, ['group_id' => $groupId]);
    }

    /**
     * Get ungrouped projects for a user (projects not in any group)
     */
    public function getUngroupedProjects(int $userId): array
    {
        $sql = "SELECT * FROM projects WHERE user_id = :user_id AND group_id IS NULL ORDER BY name";
        return $this->db->fetchAll($sql, ['user_id' => $userId]);
    }

    /**
     * Count ungrouped projects for a user
     */
    public function countUngroupedProjects(int $userId): int
    {
        $sql = "SELECT COUNT(*) as count FROM projects WHERE user_id = :user_id AND group_id IS NULL";
        $result = $this->db->fetchOne($sql, ['user_id' => $userId]);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Move project to a group
     */
    public function moveProjectToGroup(int $projectId, ?int $groupId): bool
    {
        $sql = "UPDATE projects SET group_id = :group_id WHERE id = :project_id";
        return $this->db->execute($sql, ['group_id' => $groupId, 'project_id' => $projectId]) > 0;
    }

    /**
     * Update sort order for groups
     */
    public function updateSortOrder(array $groupIds): bool
    {
        $order = 0;
        foreach ($groupIds as $groupId) {
            $sql = "UPDATE project_groups SET sort_order = :order WHERE id = :id";
            $this->db->execute($sql, ['order' => $order, 'id' => $groupId]);
            $order++;
        }
        return true;
    }

    /**
     * Get available colors for groups
     */
    public static function getAvailableColors(): array
    {
        return [
            '#6366f1' => 'Índigo',
            '#8b5cf6' => 'Violeta',
            '#ec4899' => 'Rosa',
            '#ef4444' => 'Rojo',
            '#f97316' => 'Naranja',
            '#eab308' => 'Amarillo',
            '#22c55e' => 'Verde',
            '#14b8a6' => 'Turquesa',
            '#06b6d4' => 'Cian',
            '#3b82f6' => 'Azul',
            '#64748b' => 'Gris'
        ];
    }
}
