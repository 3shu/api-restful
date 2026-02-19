<?php

declare(strict_types=1);

namespace App\Product\Application\UseCase;

use App\Product\Domain\Repository\ProductRepositoryInterface;

class ListProductsUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function execute(int $limit = 100, ?string $lastKey = null, ?string $category = null): array
    {
        if ($category !== null) {
            $products = $this->productRepository->findByCategory($category, $limit);
            return [
                'products' => $products,
                'total' => count($products),
                'limit' => $limit,
                'category' => $category,
            ];
        }

        $products = $this->productRepository->findAll($limit, $lastKey);
        
        return [
            'products' => $products,
            'total' => count($products),
            'limit' => $limit,
        ];
    }
}
