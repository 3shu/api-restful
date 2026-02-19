<?php

declare(strict_types=1);

namespace App\Product\Application\UseCase;

use App\Product\Domain\Entity\Product;
use App\Product\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class UpdateProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function execute(string $id, array $data): Product
    {
        $product = $this->productRepository->findById($id);
        
        if ($product === null) {
            throw new \DomainException('Product not found');
        }

        if (isset($data['name'])) {
            $product->setName($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $product->setDescription($data['description']);
        }

        if (isset($data['price'])) {
            $product->setPrice((float) $data['price']);
        }

        if (isset($data['stock'])) {
            $product->setStock((int) $data['stock']);
        }

        if (isset($data['category'])) {
            $product->setCategory($data['category']);
        }

        if (isset($data['active'])) {
            $product->setActive((bool) $data['active']);
        }

        // Validate entity
        $violations = $this->validator->validate($product);
        if (count($violations) > 0) {
            throw new ValidationFailedException($product, $violations);
        }

        $this->productRepository->save($product);

        return $product;
    }
}
