<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\AWS;

use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;

/**
 * Redis-based cache for AWS Secrets Manager secrets
 * 
 * This cache layer prevents excessive calls to AWS Secrets Manager
 * which can incur costs and rate limiting issues.
 */
final class SecretsCache
{
    private const CACHE_PREFIX = 'aws_secret:';

    private RedisClient $redis;
    private LoggerInterface $logger;

    public function __construct(RedisClient $redis, LoggerInterface $logger)
    {
        $this->redis = $redis;
        $this->logger = $logger;
    }

    /**
     * Get a cached secret
     * 
     * @param string $secretName The secret name
     * @return array<string, mixed>|null The cached secret or null if not found
     */
    public function get(string $secretName): ?array
    {
        $cacheKey = $this->getCacheKey($secretName);

        try {
            $cachedValue = $this->redis->get($cacheKey);

            if ($cachedValue === null) {
                return null;
            }

            $data = json_decode($cachedValue, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('Failed to decode cached secret', [
                    'secret_name' => $secretName,
                    'error' => json_last_error_msg(),
                ]);
                return null;
            }

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Redis cache get error', [
                'secret_name' => $secretName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Store a secret in cache
     * 
     * @param string $secretName The secret name
     * @param array<string, mixed> $data The secret data
     * @param int $ttl Time to live in seconds (default: 1 hour)
     */
    public function set(string $secretName, array $data, int $ttl = 3600): void
    {
        $cacheKey = $this->getCacheKey($secretName);

        try {
            $jsonData = json_encode($data);

            if ($jsonData === false) {
                throw new \RuntimeException('Failed to encode secret data: ' . json_last_error_msg());
            }

            $this->redis->setex($cacheKey, $ttl, $jsonData);

            $this->logger->debug('Secret cached successfully', [
                'secret_name' => $secretName,
                'ttl' => $ttl,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Redis cache set error', [
                'secret_name' => $secretName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete a cached secret
     * 
     * @param string $secretName The secret name to delete
     */
    public function delete(string $secretName): void
    {
        $cacheKey = $this->getCacheKey($secretName);

        try {
            $this->redis->del([$cacheKey]);

            $this->logger->debug('Secret removed from cache', [
                'secret_name' => $secretName,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Redis cache delete error', [
                'secret_name' => $secretName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear all cached secrets
     */
    public function clear(): void
    {
        try {
            $pattern = self::CACHE_PREFIX . '*';
            $keys = $this->redis->keys($pattern);

            if (!empty($keys)) {
                $this->redis->del($keys);
                $this->logger->info('All secrets cleared from cache', ['count' => count($keys)]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Redis cache clear error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate cache key for a secret
     */
    private function getCacheKey(string $secretName): string
    {
        return self::CACHE_PREFIX . $secretName;
    }
}
