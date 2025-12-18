<?php

namespace Ngw\Database;

use PDO;
use PDOException;

/**
 * Database connection and query wrapper using PDO with prepared statements
 */
class Database
{
    private static ?PDO $connection = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get PDO connection (singleton pattern)
     */
    public function getConnection(): PDO
    {
        if (self::$connection === null) {
            $this->connect();
        }
        return self::$connection;
    }

    /**
     * Establish database connection
     */
    private function connect(): void
    {
        try {
            $driver = $this->config['driver'] ?? 'sqlite';

            if ($driver === 'sqlite') {
                $dbPath = $this->config['path'] ?? __DIR__ . '/../../data/ngw.db';
                $dsn = "sqlite:" . $dbPath;

                // Ensure data directory exists
                $dataDir = dirname($dbPath);
                if (!is_dir($dataDir)) {
                    mkdir($dataDir, 0755, true);
                }

                self::$connection = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                // Enable foreign keys for SQLite
                self::$connection->exec('PRAGMA foreign_keys = ON');
            } else {
                // PostgreSQL fallback (for compatibility)
                $dsn = sprintf(
                    "pgsql:host=%s;port=%s;dbname=%s",
                    $this->config['host'] ?? 'localhost',
                    $this->config['port'] ?? 5432,
                    $this->config['name'] ?? 'genweb'
                );

                self::$connection = new PDO(
                    $dsn,
                    $this->config['user'] ?? 'genweb',
                    $this->config['password'] ?? '',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            }
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new \RuntimeException("Could not connect to database");
        }
    }

    /**
     * Execute a prepared query and return statement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch one row
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute an insert/update/delete and return affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(?string $name = null): string
    {
        // SQLite doesn't use sequences, ignore $name parameter
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->getConnection()->rollBack();
    }

    /**
     * Close connection
     */
    public function close(): void
    {
        self::$connection = null;
    }
}
