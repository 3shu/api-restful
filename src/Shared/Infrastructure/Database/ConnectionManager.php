<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

use App\Shared\Domain\Contracts\DatabaseConnectionInterface;
use App\Shared\Infrastructure\AWS\SecretsManagerService;
use App\Shared\Infrastructure\Database\Exceptions\ConnectionNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Connection Manager
 * 
 * Manages multiple database connections with lazy loading and caching.
 * Acts as a connection pool and provides centralized access to all database connections.
 */
final class ConnectionManager
{
    /** @var array<string, DatabaseConnectionInterface> */
    private array $connections = [];

    /** @var array<string, array<string, mixed>> */
    private array $localConfigurations = [];

    private ConnectionFactory $factory;
    private SecretsManagerService $secretsManager;
    private LoggerInterface $logger;
    private bool $useAwsSecrets;

    public function __construct(
        ConnectionFactory $factory,
        SecretsManagerService $secretsManager,
        LoggerInterface $logger,
        bool $useAwsSecrets
    ) {
        $this->factory = $factory;
        $this->secretsManager = $secretsManager;
        $this->logger = $logger;
        $this->useAwsSecrets = $useAwsSecrets;
    }

    /**
     * Register a local configuration (fallback when AWS Secrets is disabled)
     * 
     * @param string $connectionName The connection identifier
     * @param array<string, mixed> $config Connection configuration
     */
    public function registerLocalConfiguration(string $connectionName, array $config): void
    {
        $this->localConfigurations[$connectionName] = $config;
    }

    /**
     * Get or create a database connection
     * 
     * @param string $connectionName The connection identifier
     * @return DatabaseConnectionInterface
     * @throws ConnectionNotFoundException
     */
    public function getConnection(string $connectionName): DatabaseConnectionInterface
    {
        // Return cached connection if exists
        if (isset($this->connections[$connectionName])) {
            $connection = $this->connections[$connectionName];
            
            if ($connection->isConnected()) {
                return $connection;
            }
            
            // Connection lost, remove from cache and recreate
            $this->logger->warning('Connection lost, recreating', ['connection' => $connectionName]);
            unset($this->connections[$connectionName]);
        }

        // Create new connection
        $config = $this->getConfiguration($connectionName);
        
        if (!isset($config['driver'])) {
            throw new ConnectionNotFoundException(
                sprintf('Driver not specified for connection "%s"', $connectionName)
            );
        }

        $this->logger->info('Creating database connection', [
            'connection' => $connectionName,
            'driver' => $config['driver'],
        ]);

        $connection = $this->factory->create($config['driver'], $config, $connectionName);
        $this->connections[$connectionName] = $connection;

        return $connection;
    }

    /**
     * Get configuration for a connection from AWS Secrets or local config
     * 
     * @param string $connectionName
     * @return array<string, mixed>
     * @throws ConnectionNotFoundException
     */
    private function getConfiguration(string $connectionName): array
    {
        if ($this->useAwsSecrets && $this->secretsManager->isEnabled()) {
            try {
                return $this->secretsManager->getSecret($connectionName);
            } catch (\Exception $e) {
                $this->logger->error('Failed to get secret from AWS, falling back to local config', [
                    'connection' => $connectionName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback to local configuration
        if (!isset($this->localConfigurations[$connectionName])) {
            throw new ConnectionNotFoundException(
                sprintf('No configuration found for connection "%s"', $connectionName)
            );
        }

        return $this->localConfigurations[$connectionName];
    }

    /**
     * Check if a connection exists in the pool
     * 
     * @param string $connectionName
     * @return bool
     */
    public function hasConnection(string $connectionName): bool
    {
        return isset($this->connections[$connectionName]);
    }

    /**
     * Disconnect a specific connection
     * 
     * @param string $connectionName
     */
    public function disconnect(string $connectionName): void
    {
        if (isset($this->connections[$connectionName])) {
            $this->connections[$connectionName]->disconnect();
            unset($this->connections[$connectionName]);
            
            $this->logger->info('Connection disconnected', ['connection' => $connectionName]);
        }
    }

    /**
     * Disconnect all connections
     */
    public function disconnectAll(): void
    {
        foreach (array_keys($this->connections) as $connectionName) {
            $this->disconnect($connectionName);
        }
    }

    /**
     * Get all active connection names
     * 
     * @return array<string>
     */
    public function getActiveConnections(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Refresh a connection (disconnect and reconnect)
     * 
     * @param string $connectionName
     * @return DatabaseConnectionInterface
     */
    public function refreshConnection(string $connectionName): DatabaseConnectionInterface
    {
        $this->disconnect($connectionName);
        return $this->getConnection($connectionName);
    }
}
