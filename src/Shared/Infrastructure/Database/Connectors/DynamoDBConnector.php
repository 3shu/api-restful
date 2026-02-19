<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Connectors;

use App\Shared\Domain\Contracts\DatabaseConnectionInterface;
use Aws\DynamoDb\DynamoDbClient;

/**
 * DynamoDB Connector
 * 
 * Creates and manages AWS DynamoDB connections
 * This will be fully implemented in Phase 5
 */
final class DynamoDBConnector implements DatabaseConnectionInterface
{
    private ?DynamoDbClient $client = null;
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

    public function getConnection(): DynamoDbClient
    {
        if ($this->client === null) {
            $this->connect();
        }

        return $this->client;
    }

    public function isConnected(): bool
    {
        if ($this->client === null) {
            return false;
        }

        try {
            $this->client->listTables(['Limit' => 1]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getType(): string
    {
        return 'dynamodb';
    }

    public function getName(): string
    {
        return $this->connectionName;
    }

    public function disconnect(): void
    {
        // DynamoDB client doesn't need explicit disconnect
        $this->client = null;
    }

    /**
     * Establish DynamoDB connection
     */
    private function connect(): void
    {
        $clientConfig = [
            'version' => $this->config['version'] ?? 'latest',
            'region' => $this->config['region'] ?? 'us-east-1',
        ];

        // Support for LocalStack or custom endpoint (for development)
        if (isset($this->config['endpoint'])) {
            $clientConfig['endpoint'] = $this->config['endpoint'];
        }

        // AWS Credentials
        if (isset($this->config['key']) && isset($this->config['secret'])) {
            $clientConfig['credentials'] = [
                'key' => $this->config['key'],
                'secret' => $this->config['secret'],
            ];
        }

        $this->client = new DynamoDbClient($clientConfig);
    }
}
