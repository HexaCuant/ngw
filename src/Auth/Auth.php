<?php

namespace Ngw\Auth;

use Ngw\Database\Database;

/**
 * Secure authentication with password hashing
 */
class Auth
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Authenticate user with username and password
     *
     * @return array|null User data if authenticated, null otherwise
     */
    public function authenticate(string $username, string $password): ?array
    {
        if (empty($username) || empty($password)) {
            return null;
        }

        $sql = "SELECT id, username, password, email, is_admin, role, is_approved, assigned_teacher_id, must_change_password FROM users WHERE username = :username";
        $user = $this->db->fetchOne($sql, ['username' => $username]);

        if (!$user) {
            return null;
        }

        // Check if user is approved
        if (!$user['is_approved']) {
            throw new \RuntimeException("Tu cuenta está pendiente de aprobación");
        }

        // Verify password using password_verify for hashed passwords
        if (password_verify($password, $user['password'])) {
            // Remove password from returned data
            unset($user['password']);
            return $user;
        }

        return null;
    }

    /**
     * Create new user with hashed password (admin only)
     *
     * @return int|null User ID if created, null on failure
     */
    public function createUser(string $username, string $password, string $email = '', bool $isAdmin = false): ?int
    {
        if (empty(trim($username)) || empty(trim($password))) {
            throw new \InvalidArgumentException("Username and password cannot be empty");
        }

        // Check if user already exists
        $sql = "SELECT id FROM users WHERE username = :username";
        $existing = $this->db->fetchOne($sql, ['username' => $username]);

        if ($existing) {
            throw new \RuntimeException("Username already exists");
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user (approved by default if created by admin)
        $sql = "INSERT INTO users (username, password, email, is_admin, is_approved, approved_at) 
                VALUES (:username, :password, :email, :is_admin, 1, datetime('now'))";

        $this->db->execute($sql, [
            'username' => $username,
            'password' => $hashedPassword,
            'email' => $email,
            'is_admin' => $isAdmin ? 1 : 0
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): ?array
    {
        $sql = "SELECT id, username, email, is_admin, is_approved FROM users WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Get user ID by username
     */
    public function getUserIdByUsername(string $username): ?int
    {
        $sql = "SELECT id FROM users WHERE username = :username";
        $result = $this->db->fetchOne($sql, ['username' => $username]);
        return $result ? (int) $result['id'] : null;
    }

    /**
     * Update user password
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = :password, updated_at = datetime('now') WHERE id = :id";
        return $this->db->execute($sql, ['password' => $hashedPassword, 'id' => $userId]) > 0;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(int $userId): bool
    {
        $sql = "SELECT is_admin FROM users WHERE id = :id";
        $result = $this->db->fetchOne($sql, ['id' => $userId]);
        return $result && (int) $result['is_admin'] === 1;
    }

    /**
     * Get all approved users (admin only)
     */
    public function getAllUsers(): array
    {
        $sql = "SELECT id, username, email, is_admin, role, is_approved, assigned_teacher_id, created_at, approved_at 
                FROM users 
                WHERE is_approved = 1
                ORDER BY role, username ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Delete user (admin only, cannot delete self or other admins)
     */
    public function deleteUser(int $userId, int $adminId): bool
    {
        // Cannot delete yourself
        if ($userId === $adminId) {
            throw new \RuntimeException("No puedes eliminarte a ti mismo");
        }

        // Check if target user is admin
        $targetUser = $this->getUserById($userId);
        if (!$targetUser) {
            throw new \RuntimeException("Usuario no encontrado");
        }

        if ((int)$targetUser['is_admin'] === 1) {
            throw new \RuntimeException("No se pueden eliminar otros administradores");
        }

        // Delete user
        $sql = "DELETE FROM users WHERE id = :id";
        return $this->db->execute($sql, ['id' => $userId]) > 0;
    }

    /**
     * Get all teachers
     */
    public function getTeachers(): array
    {
        $sql = "SELECT id, username FROM users WHERE role = 'teacher' AND is_approved = 1 ORDER BY username ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Reset user password (admin/teacher action)
     * @param int $targetUserId User whose password will be reset
     * @param string $newPassword New temporary password
     * @param int $actorId User performing the reset
     * @return bool Success
     */
    public function resetPassword(int $targetUserId, string $newPassword, int $actorId): bool
    {
        // Get target user info
        $targetUser = $this->db->fetchOne(
            "SELECT id, role, assigned_teacher_id FROM users WHERE id = :id",
            ['id' => $targetUserId]
        );

        if (!$targetUser) {
            throw new \RuntimeException("Usuario no encontrado");
        }

        // Get actor info
        $actor = $this->db->fetchOne(
            "SELECT id, role, is_admin FROM users WHERE id = :id",
            ['id' => $actorId]
        );

        if (!$actor) {
            throw new \RuntimeException("Actor no válido");
        }

        // Permission check:
        // - Admin can reset anyone except other admins
        // - Teacher can only reset their assigned students
        $isAdmin = (int)$actor['is_admin'] === 1;
        $isTeacher = $actor['role'] === 'teacher';
        $targetIsStudent = $targetUser['role'] === 'student';
        $isAssignedTeacher = $targetUser['assigned_teacher_id'] === $actorId;

        if ($isAdmin) {
            // Admin can reset anyone except other admins
            $targetIsAdmin = $this->db->fetchOne(
                "SELECT is_admin FROM users WHERE id = :id",
                ['id' => $targetUserId]
            );
            if ((int)$targetIsAdmin['is_admin'] === 1 && $targetUserId !== $actorId) {
                throw new \RuntimeException("No puedes resetear la contraseña de otro administrador");
            }
        } elseif ($isTeacher) {
            // Teacher can only reset their assigned students
            if (!$targetIsStudent || !$isAssignedTeacher) {
                throw new \RuntimeException("Solo puedes resetear contraseñas de tus alumnos asignados");
            }
        } else {
            throw new \RuntimeException("No tienes permisos para resetear contraseñas");
        }

        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password and set must_change_password flag
        $sql = "UPDATE users SET password = :password, must_change_password = 1, updated_at = datetime('now') WHERE id = :id";
        return $this->db->execute($sql, ['password' => $hashedPassword, 'id' => $targetUserId]) > 0;
    }

    /**
     * Change user's own password
     * @param int $userId User ID
     * @param string $currentPassword Current password (for verification, empty if forced change)
     * @param string $newPassword New password
     * @param bool $forcedChange If true, skip current password verification
     * @return bool Success
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword, bool $forcedChange = false): bool
    {
        if (empty(trim($newPassword)) || strlen($newPassword) < 4) {
            throw new \RuntimeException("La nueva contraseña debe tener al menos 4 caracteres");
        }

        // Get current user data
        $user = $this->db->fetchOne(
            "SELECT password, must_change_password FROM users WHERE id = :id",
            ['id' => $userId]
        );

        if (!$user) {
            throw new \RuntimeException("Usuario no encontrado");
        }

        // If not forced change, verify current password
        if (!$forcedChange && (int)$user['must_change_password'] !== 1) {
            if (!password_verify($currentPassword, $user['password'])) {
                throw new \RuntimeException("La contraseña actual es incorrecta");
            }
        }

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update password and clear must_change_password flag
        $sql = "UPDATE users SET password = :password, must_change_password = 0, updated_at = datetime('now') WHERE id = :id";
        return $this->db->execute($sql, ['password' => $hashedPassword, 'id' => $userId]) > 0;
    }

    /**
     * Get users that can be managed by a teacher (their students)
     */
    public function getStudentsByTeacher(int $teacherId): array
    {
        $sql = "SELECT id, username, email, role FROM users WHERE assigned_teacher_id = :teacher_id AND is_approved = 1 ORDER BY username ASC";
        return $this->db->fetchAll($sql, ['teacher_id' => $teacherId]);
    }
}
