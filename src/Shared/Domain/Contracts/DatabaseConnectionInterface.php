<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Database Connection Interface
 * 
 * Contract for all database connection implementations.
 * Supports multiple database engines through a unified interface.
 */
interface DatabaseConnectionInterface
{
    /**
     * Get the underlying connection object
     * 
     * @return mixed The native connection (PDO, Doctrine Connection, Redis Client, DynamoDB Client, etc.)
     */
    public function getConnection(): mixed;

    /**
     * Test if the connection is alive
     * 
     * @return bool True if connection is active
     */
    public function isConnected(): bool;

    /**
     * Get connection type identifier
     * 
     * @return string The type of connection (mysql, sqlserver, redis, dynamodb, etc.)
     */
    public function getType(): string;

    /**
     * Get connection name/identifier
     * 
     * @return string The name of the connection
     */
    public function getName(): string;

    /**
     * Close the connection
     */
    public function disconnect(): void;
}
