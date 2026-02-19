<?php

declare(strict_types=1);

namespace App\Product\Application\UseCase;

use App\Product\Domain\Entity\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class CreateProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function execute(array $data): Product
    {
        $product = new Product();
        $product->setName($data['name'] ?? '');
        $product->setDescription($data['description'] ?? null);
        $product->setPrice($data['price'] ?? 0.0);
        $product->setStock($data['stock'] ?? 0);
        $product->setCategory($data['category'] ?? '');

        // Validate entity
        $violations = $this->validator->validate($product);
        if (count($violations) > 0) {
            throw new ValidationFailedException($product, $violations);
        }

        $this->productRepository->save($product);

        return $product;
    }
}
