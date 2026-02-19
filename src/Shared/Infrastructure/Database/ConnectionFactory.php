<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

use App\Shared\Domain\Contracts\DatabaseConnectionInterface;
use App\Shared\Infrastructure\Database\Connectors\MySQLConnector;
use App\Shared\Infrastructure\Database\Connectors\PostgreSQLConnector;
use App\Shared\Infrastructure\Database\Connectors\RedisConnector;
use App\Shared\Infrastructure\Database\Connectors\DynamoDBConnector;
use App\Shared\Infrastructure\Database\Exceptions\UnsupportedDatabaseException;

/**
 * Connection Factory
 * 
 * Factory class responsible for creating database connections
 * based on the driver type. Implements Factory and Strategy patterns.
 */
final class ConnectionFactory
{
    /**
     * Create a database connection based on driver type
     * 
     * @param string $driver The database driver (mysql, postgres, redis, dynamodb)
     * @param array<string, mixed> $config Connection configuration
     * @param string $connectionName Identifier for this connection
     * @return DatabaseConnectionInterface
     * @throws UnsupportedDatabaseException
     */
    public function create(string $driver, array $config, string $connectionName): DatabaseConnectionInterface
    {
        return match (strtolower($driver)) {
            'mysql', 'pdo_mysql' => new MySQLConnector($config, $connectionName),
            'postgres', 'postgresql', 'pgsql', 'pdo_pgsql' => new PostgreSQLConnector($config, $connectionName),
            'redis' => new RedisConnector($config, $connectionName),
            'dynamodb' => new DynamoDBConnector($config, $connectionName),
            default => throw new UnsupportedDatabaseException(
                sprintf('Database driver "%s" is not supported', $driver)
            ),
        };
    }

    /**
     * Get list of supported database drivers
     * 
     * @return array<string>
     */
    public function getSupportedDrivers(): array
    {
        return [
            'mysql',
            'pdo_mysql',
            'postgres',
            'postgresql',
            'pgsql',
            'pdo_pgsql',
            'redis',
            'dynamodb',
        ];
    }
}
