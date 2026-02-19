<?php

declare(strict_types=1);

namespace App\Product\Application\UseCase;

use App\Product\Domain\Entity\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface;

class FindProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function execute(string $id): Product
    {
        $product = $this->productRepository->findById($id);
        
        if ($product === null) {
            throw new \DomainException('Product not found');
        }

        return $product;
    }
}
