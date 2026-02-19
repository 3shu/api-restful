<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Connectors;

use App\Shared\Domain\Contracts\DatabaseConnectionInterface;
use Predis\Client;

/**
 * Redis Connector
 * 
 * Creates and manages Redis connections using Predis client
 */
final class RedisConnector implements DatabaseConnectionInterface
{
    private ?Client $client = null;
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

    public function getConnection(): Client
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
            $this->client->ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getType(): string
    {
        return 'redis';
    }

    public function getName(): string
    {
        return $this->connectionName;
    }

    public function disconnect(): void
    {
        if ($this->client !== null) {
            $this->client->disconnect();
            $this->client = null;
        }
    }

    /**
     * Establish Redis connection
     */
    private function connect(): void
    {
        $parameters = [
            'scheme' => $this->config['scheme'] ?? 'tcp',
            'host' => $this->config['host'] ?? '127.0.0.1',
            'port' => $this->config['port'] ?? 6379,
        ];

        if (isset($this->config['password']) && !empty($this->config['password'])) {
            $parameters['password'] = $this->config['password'];
        }

        if (isset($this->config['database'])) {
            $parameters['database'] = (int) $this->config['database'];
        }

        $options = [];
        
        if (isset($this->config['timeout'])) {
            $options['timeout'] = (float) $this->config['timeout'];
        }

        $this->client = new Client($parameters, $options);
    }
}
