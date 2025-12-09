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

        $sql = "SELECT id, username, pass, cod_auth FROM users WHERE username = :username";
        $user = $this->db->fetchOne($sql, ['username' => $username]);

        if (!$user) {
            return null;
        }

        // Verify password using password_verify for hashed passwords
        if (password_verify($password, $user['pass'])) {
            // Remove password from returned data
            unset($user['pass']);
            return $user;
        }

        return null;
    }

    /**
     * Create new user with hashed password
     * 
     * @return int|null User ID if created, null on failure
     */
    public function createUser(string $username, string $password): ?int
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

        // Insert new user
        $sql = "INSERT INTO users (cod_auth, username, pass) VALUES (:cod_auth, :username, :pass)";
        $this->db->execute($sql, [
            'cod_auth' => 1,
            'username' => $username,
            'pass' => $hashedPassword
        ]);

        return (int) $this->db->lastInsertId('users_id_seq');
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): ?array
    {
        $sql = "SELECT id, username, cod_auth FROM users WHERE id = :id";
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
        $sql = "UPDATE users SET pass = :pass WHERE id = :id";
        return $this->db->execute($sql, ['pass' => $hashedPassword, 'id' => $userId]) > 0;
    }
}
