<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\AWS;

use Aws\SecretsManager\SecretsManagerClient;
use Aws\Exception\AwsException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Service for retrieving secrets from AWS Secrets Manager
 * 
 * This service handles the communication with AWS Secrets Manager
 * and provides a simple interface to retrieve database credentials
 * and other sensitive configuration.
 */
final class SecretsManagerService
{
    private SecretsManagerClient $client;
    private SecretsCache $cache;
    private LoggerInterface $logger;
    private bool $useAwsSecrets;

    public function __construct(
        string $awsRegion,
        string $awsAccessKeyId,
        string $awsSecretAccessKey,
        SecretsCache $cache,
        LoggerInterface $logger,
        bool $useAwsSecrets = true
    ) {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->useAwsSecrets = $useAwsSecrets;

        if ($this->useAwsSecrets) {
            $this->client = new SecretsManagerClient([
                'version' => 'latest',
                'region' => $awsRegion,
                'credentials' => [
                    'key' => $awsAccessKeyId,
                    'secret' => $awsSecretAccessKey,
                ],
            ]);
        }
    }

    /**
     * Retrieve a secret from AWS Secrets Manager
     * 
     * @param string $secretName The name/ARN of the secret
     * @return array<string, mixed> The secret data as an associative array
     * @throws RuntimeException If secret retrieval fails
     */
    public function getSecret(string $secretName): array
    {
        // Check cache first
        $cachedSecret = $this->cache->get($secretName);
        if ($cachedSecret !== null) {
            $this->logger->info('Secret retrieved from cache', ['secret_name' => $secretName]);
            return $cachedSecret;
        }

        if (!$this->useAwsSecrets) {
            throw new RuntimeException(
                'AWS Secrets Manager is disabled. Set USE_AWS_SECRETS=true to enable.'
            );
        }

        try {
            $this->logger->info('Fetching secret from AWS Secrets Manager', ['secret_name' => $secretName]);

            $result = $this->client->getSecretValue([
                'SecretId' => $secretName,
            ]);

            if (!isset($result['SecretString'])) {
                throw new RuntimeException(
                    sprintf('Secret "%s" does not contain a SecretString', $secretName)
                );
            }

            $secretData = json_decode($result['SecretString'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    sprintf('Failed to decode secret "%s": %s', $secretName, json_last_error_msg())
                );
            }

            // Cache the secret for 1 hour
            $this->cache->set($secretName, $secretData, 3600);

            $this->logger->info('Secret retrieved successfully from AWS', ['secret_name' => $secretName]);

            return $secretData;

        } catch (AwsException $e) {
            $this->logger->error('AWS Secrets Manager error', [
                'secret_name' => $secretName,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            throw new RuntimeException(
                sprintf('Failed to retrieve secret "%s": %s', $secretName, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Invalidate cached secret and fetch fresh data
     * 
     * @param string $secretName The name of the secret to refresh
     * @return array<string, mixed> The fresh secret data
     */
    public function refreshSecret(string $secretName): array
    {
        $this->cache->delete($secretName);
        return $this->getSecret($secretName);
    }

    /**
     * Check if AWS Secrets Manager is enabled
     */
    public function isEnabled(): bool
    {
        return $this->useAwsSecrets;
    }
}
