<?php

namespace Ngw\Auth;

/**
 * Session management with security features
 */
class SessionManager
{
    private bool $started = false;

    /**
     * Start session with security settings
     */
    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure', '0'); // Set to 1 if using HTTPS
        ini_set('session.cookie_samesite', 'Lax');

        session_start();
        $this->started = true;
    }

    /**
     * Set session value
     */
    public function set(string $key, $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     */
    public function get(string $key, $default = null)
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session key exists
     */
    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value
     */
    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data
     */
    public function clear(): void
    {
        $this->start();
        $_SESSION = [];
    }

    /**
     * Destroy session completely
     */
    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
        $this->started = false;
    }

    /**
     * Regenerate session ID (call on login/privilege change)
     */
    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->has('user_id') && $this->get('authenticated') === true;
    }

    /**
     * Set authenticated user
     */
    public function setUser(array $userData): void
    {
        $this->regenerate();
        $this->set('authenticated', true);
        $this->set('user_id', $userData['id']);
        $this->set('username', $userData['username']);
        $this->set('is_admin', $userData['is_admin'] ?? 0);
        $this->set('role', $userData['role'] ?? 'student'); // student, teacher, admin
        $this->set('assigned_teacher_id', $userData['assigned_teacher_id'] ?? null);
        $this->set('must_change_password', $userData['must_change_password'] ?? 0);
    }

    /**
     * Check if user must change password
     */
    public function mustChangePassword(): bool
    {
        return $this->isAuthenticated() && (int) $this->get('must_change_password') === 1;
    }

    /**
     * Clear the must_change_password flag in session
     */
    public function clearMustChangePassword(): void
    {
        $this->set('must_change_password', 0);
    }

    /**
     * Get current user ID
     */
    public function getUserId(): ?int
    {
        return $this->isAuthenticated() ? $this->get('user_id') : null;
    }

    /**
     * Get current username
     */
    public function getUsername(): ?string
    {
        return $this->isAuthenticated() ? $this->get('username') : null;
    }

    /**
     * Check if current user is admin
     */
    public function isAdmin(): bool
    {
        return $this->isAuthenticated() && (int) $this->get('is_admin') === 1;
    }

    /**
     * Get current user role
     */
    public function getRole(): ?string
    {
        return $this->isAuthenticated() ? $this->get('role') : null;
    }

    /**
     * Check if current user is teacher
     */
    public function isTeacher(): bool
    {
        return $this->isAuthenticated() && $this->get('role') === 'teacher';
    }

    /**
     * Check if current user is student
     */
    public function isStudent(): bool
    {
        return $this->isAuthenticated() && $this->get('role') === 'student';
    }

    /**
     * Get assigned teacher ID for students
     */
    public function getAssignedTeacherId(): ?int
    {
        $teacherId = $this->get('assigned_teacher_id');
        return $teacherId !== null ? (int) $teacherId : null;
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        $this->destroy();
    }
}
