<?php

declare(strict_types=1);

namespace App\Product\Domain\Repository;

use App\Product\Domain\Entity\Product;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findById(string $id): ?Product;

    /**
     * @return Product[]
     */
    public function findAll(int $limit = 100, ?string $lastEvaluatedKey = null): array;

    /**
     * @return Product[]
     */
    public function findByCategory(string $category, int $limit = 100): array;

    public function delete(string $id): void;

    public function existsById(string $id): bool;
}
