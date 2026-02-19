<?php

declare(strict_types=1);

namespace App\Product\Application\UseCase;

use App\Product\Domain\Repository\ProductRepositoryInterface;

class DeleteProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function execute(string $id): void
    {
        if (!$this->productRepository->existsById($id)) {
            throw new \DomainException('Product not found');
        }

        $this->productRepository->delete($id);
    }
}
