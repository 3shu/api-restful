<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Connectors;

use App\Shared\Domain\Contracts\DatabaseConnectionInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PDO;

/**
 * SQL Server Database Connector
 * 
 * Creates and manages SQL Server database connections using Doctrine DBAL
 */
final class SQLServerConnector implements DatabaseConnectionInterface
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
        if ($this->connection === null) {
            return false;
        }

        try {
            return $this->connection->isConnected();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getType(): string
    {
        return 'sqlserver';
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
     * Establish SQL Server connection
     */
    private function connect(): void
    {
        $connectionParams = [
            'driver' => 'pdo_sqlsrv',
            'host' => $this->config['host'] ?? 'localhost',
            'port' => $this->config['port'] ?? 1433,
            'dbname' => $this->config['database'] ?? $this->config['dbname'] ?? '',
            'user' => $this->config['user'] ?? $this->config['username'] ?? 'sa',
            'password' => $this->config['password'] ?? '',
            'driverOptions' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ];

        // Add TrustServerCertificate for development
        if (isset($this->config['trust_server_certificate']) && $this->config['trust_server_certificate']) {
            $connectionParams['driverOptions']['TrustServerCertificate'] = 'yes';
        }

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
