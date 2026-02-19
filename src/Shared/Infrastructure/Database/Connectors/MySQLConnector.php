<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Connectors;

use App\Shared\Domain\Contracts\DatabaseConnectionInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PDO;

/**
 * MySQL Database Connector
 * 
 * Creates and manages MySQL database connections using Doctrine DBAL
 */
final class MySQLConnector implements DatabaseConnectionInterface
{
    private ?Connection $connection = null;
    private string $connectionName;
    
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     * @param string $connectionName
     */
    public function __construct(array $config, string $connectionName)
    {
        $this->config = $config;
        $this->connectionName = $connectionName;
    }

    public function getConnection(): Connection
    {
        if ($this->connection === null) {
            $this->connect();
        }

        return $this->connection;
    }

    public function isConnected(): bool
    {
        try {
            if ($this->connection === null) {
                $this->connect();
            }
            
            // Doctrine DBAL doesn't actually connect until a query is executed
            // So we force a connection by executing a simple query
            $this->connection->executeQuery('SELECT 1');
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getType(): string
    {
        return 'mysql';
    }

    public function getName(): string
    {
        return $this->connectionName;
    }

    public function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Establish MySQL connection
     */
    private function connect(): void
    {
        $connectionParams = [
            'driver' => 'pdo_mysql',
            'host' => $this->config['host'] ?? 'localhost',
            'port' => $this->config['port'] ?? 3306,
            'dbname' => $this->config['database'] ?? $this->config['dbname'] ?? '',
            'user' => $this->config['user'] ?? $this->config['username'] ?? 'root',
            'password' => $this->config['password'] ?? '',
            'charset' => $this->config['charset'] ?? 'utf8mb4',
            'driverOptions' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ];

        $this->connection = DriverManager::getConnection($connectionParams);
    }

    /**
     * Get native PDO connection for direct access
     * 
     * @return PDO
     */
    public function getNativeConnection(): PDO
    {
        return $this->getConnection()->getNativeConnection();
    }
}
