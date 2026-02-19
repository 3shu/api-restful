<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Persistence\DynamoDB;

use App\Product\Domain\Entity\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use DateTimeImmutable;

class ProductRepository implements ProductRepositoryInterface
{
    private const TABLE_NAME = 'products';

    public function __construct(
        private readonly DynamoDbClient $dynamoDb
    ) {
        $this->ensureTableExists();
    }

    public function save(Product $product): void
    {
        $item = [
            'id' => ['S' => $product->getId()],
            'name' => ['S' => $product->getName()],
            'description' => ['S' => $product->getDescription() ?? ''],
            'price' => ['N' => (string) $product->getPrice()],
            'stock' => ['N' => (string) $product->getStock()],
            'category' => ['S' => $product->getCategory()],
            'active' => ['BOOL' => $product->isActive()],
            'createdAt' => ['S' => $product->getCreatedAt()->format('Y-m-d H:i:s')],
            'updatedAt' => ['S' => $product->getUpdatedAt()?->format('Y-m-d H:i:s') ?? ''],
        ];

        $this->dynamoDb->putItem([
            'TableName' => self::TABLE_NAME,
            'Item' => $item,
        ]);
    }

    public function findById(string $id): ?Product
    {
        try {
            $result = $this->dynamoDb->getItem([
                'TableName' => self::TABLE_NAME,
                'Key' => [
                    'id' => ['S' => $id],
                ],
            ]);

            if (!isset($result['Item'])) {
                return null;
            }

            return $this->hydrateFromDynamoDb($result['Item']);
        } catch (DynamoDbException $e) {
            return null;
        }
    }

    public function findAll(int $limit = 100, ?string $lastEvaluatedKey = null): array
    {
        $params = [
            'TableName' => self::TABLE_NAME,
            'Limit' => $limit,
        ];

        if ($lastEvaluatedKey !== null) {
            $params['ExclusiveStartKey'] = ['id' => ['S' => $lastEvaluatedKey]];
        }

        try {
            $result = $this->dynamoDb->scan($params);
            
            if (!isset($result['Items']) || empty($result['Items'])) {
                return [];
            }

            return array_map(
                fn(array $item) => $this->hydrateFromDynamoDb($item),
                $result['Items']
            );
        } catch (DynamoDbException $e) {
            return [];
        }
    }

    public function findByCategory(string $category, int $limit = 100): array
    {
        try {
            $result = $this->dynamoDb->query([
                'TableName' => self::TABLE_NAME,
                'IndexName' => 'CategoryIndex',
                'KeyConditionExpression' => 'category = :category',
                'ExpressionAttributeValues' => [
                    ':category' => ['S' => $category],
                ],
                'Limit' => $limit,
            ]);

            if (!isset($result['Items']) || empty($result['Items'])) {
                return [];
            }

            return array_map(
                fn(array $item) => $this->hydrateFromDynamoDb($item),
                $result['Items']
            );
        } catch (DynamoDbException $e) {
            return [];
        }
    }

    public function delete(string $id): void
    {
        $this->dynamoDb->deleteItem([
            'TableName' => self::TABLE_NAME,
            'Key' => [
                'id' => ['S' => $id],
            ],
        ]);
    }

    public function existsById(string $id): bool
    {
        return $this->findById($id) !== null;
    }

    private function hydrateFromDynamoDb(array $item): Product
    {
        $product = new Product($item['id']['S']);
        
        $product->setName($item['name']['S']);
        $product->setDescription($item['description']['S'] !== '' ? $item['description']['S'] : null);
        $product->setPrice((float) $item['price']['N']);
        $product->setStock((int) $item['stock']['N']);
        $product->setCategory($item['category']['S']);
        $product->setActive($item['active']['BOOL']);

        // Use reflection to set readonly properties
        $reflection = new \ReflectionClass($product);
        
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($product, new DateTimeImmutable($item['createdAt']['S']));

        if ($item['updatedAt']['S'] !== '') {
            $updatedAtProperty = $reflection->getProperty('updatedAt');
            $updatedAtProperty->setAccessible(true);
            $updatedAtProperty->setValue($product, new DateTimeImmutable($item['updatedAt']['S']));
        }

        return $product;
    }

    private function ensureTableExists(): void
    {
        try {
            $this->dynamoDb->describeTable(['TableName' => self::TABLE_NAME]);
        } catch (DynamoDbException $e) {
            // Table doesn't exist, create it
            $this->createTable();
        }
    }

    private function createTable(): void
    {
        $this->dynamoDb->createTable([
            'TableName' => self::TABLE_NAME,
            'KeySchema' => [
                ['AttributeName' => 'id', 'KeyType' => 'HASH'],
            ],
            'AttributeDefinitions' => [
                ['AttributeName' => 'id', 'AttributeType' => 'S'],
                ['AttributeName' => 'category', 'AttributeType' => 'S'],
            ],
            'GlobalSecondaryIndexes' => [
                [
                    'IndexName' => 'CategoryIndex',
                    'KeySchema' => [
                        ['AttributeName' => 'category', 'KeyType' => 'HASH'],
                    ],
                    'Projection' => ['ProjectionType' => 'ALL'],
                    'ProvisionedThroughput' => [
                        'ReadCapacityUnits' => 5,
                        'WriteCapacityUnits' => 5,
                    ],
                ],
            ],
            'ProvisionedThroughput' => [
                'ReadCapacityUnits' => 5,
                'WriteCapacityUnits' => 5,
            ],
        ]);

        // Wait for table to be active
        $this->dynamoDb->waitUntil('TableExists', ['TableName' => self::TABLE_NAME]);
    }
}
