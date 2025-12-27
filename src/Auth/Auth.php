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

        $sql = "SELECT id, username, password, email, is_admin, is_approved FROM users WHERE username = :username";
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
     * Get all users (admin only)
     */
    public function getAllUsers(): array
    {
        $sql = "SELECT id, username, email, is_admin, role, is_approved, created_at, approved_at 
                FROM users 
                ORDER BY created_at DESC";
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
}
