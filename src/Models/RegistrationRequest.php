<?php

namespace Ngw\Models;

use Ngw\Database\Database;

/**
 * Registration request model
 */
class RegistrationRequest
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Create new registration request
     */
    public function create(string $username, string $email = '', string $reason = '', string $password = '', string $role = 'student', ?int $assignedTeacherId = null): int
    {
        // Validate role
        if (!in_array($role, ['student', 'teacher'])) {
            throw new \RuntimeException("Rol inválido");
        }

        // Check if username already exists or has pending request
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = :username",
            ['username' => $username]
        );

        if ($existing) {
            throw new \RuntimeException("El nombre de usuario ya existe");
        }

        $pendingRequest = $this->db->fetchOne(
            "SELECT id FROM registration_requests WHERE username = :username AND status = 'pending'",
            ['username' => $username]
        );

        if ($pendingRequest) {
            throw new \RuntimeException("Ya existe una solicitud pendiente para este nombre de usuario");
        }

        // Validate assigned teacher if provided
        if ($assignedTeacherId !== null) {
            $teacher = $this->db->fetchOne(
                "SELECT id FROM users WHERE id = :id AND role = 'teacher'",
                ['id' => $assignedTeacherId]
            );
            if (!$teacher) {
                throw new \RuntimeException("El profesor especificado no existe o no es válido");
            }
        }

        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO registration_requests (username, email, password, role, reason, status, assigned_teacher_id) 
                VALUES (:username, :email, :password, :role, :reason, 'pending', :assigned_teacher_id)";

        $this->db->execute($sql, [
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'role' => $role,
            'reason' => $reason,
            'assigned_teacher_id' => $assignedTeacherId
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Get all pending requests
     */
    public function getPending(): array
    {
        $sql = "SELECT * FROM registration_requests WHERE status = 'pending' ORDER BY requested_at ASC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Get all requests (for admin)
     */
    public function getAll(?string $status = null): array
    {
        if ($status) {
            $sql = "SELECT * FROM registration_requests WHERE status = :status ORDER BY requested_at DESC";
            return $this->db->fetchAll($sql, ['status' => $status]);
        }

        $sql = "SELECT * FROM registration_requests ORDER BY requested_at DESC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Get request by ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM registration_requests WHERE id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Approve registration request and create user
     */
    public function approve(int $requestId, int $approvedBy): ?int
    {
        $request = $this->getById($requestId);

        if (!$request || $request['status'] !== 'pending') {
            throw new \RuntimeException("Solicitud no encontrada o ya procesada");
        }

        $this->db->beginTransaction();

        try {
            // Create user with password from request and role
            $sql = "INSERT INTO users (username, password, email, role, is_admin, is_approved, approved_at) 
                    VALUES (:username, :password, :email, :role, 0, 1, datetime('now'))";

            $this->db->execute($sql, [
                'username' => $request['username'],
                'password' => $request['password'], // Already hashed
                'email' => $request['email'],
                'role' => $request['role'] ?? 'student'
            ]);

            $userId = (int) $this->db->lastInsertId();

            // Update request status
            $sql = "UPDATE registration_requests 
                    SET status = 'approved', processed_at = datetime('now'), processed_by = :processed_by 
                    WHERE id = :id";

            $this->db->execute($sql, ['id' => $requestId, 'processed_by' => $approvedBy]);

            $this->db->commit();

            return $userId;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Reject registration request
     */
    public function reject(int $requestId, int $rejectedBy): bool
    {
        $sql = "UPDATE registration_requests 
                SET status = 'rejected', processed_at = datetime('now'), processed_by = :processed_by 
                WHERE id = :id AND status = 'pending'";

        return $this->db->execute($sql, ['id' => $requestId, 'processed_by' => $rejectedBy]) > 0;
    }

    /**
     * Get pending count
     */
    public function getPendingCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM registration_requests WHERE status = 'pending'";
        $result = $this->db->fetchOne($sql);
        return (int) ($result['count'] ?? 0);
    }
}
