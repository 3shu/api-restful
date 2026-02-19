<?php

declare(strict_types=1);

namespace App\Product\Domain\Entity;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class Product
{
    #[Assert\NotBlank(message: 'Product ID is required')]
    private string $id;

    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Name must be at least {{ limit }} characters',
        maxMessage: 'Name cannot be longer than {{ limit }} characters'
    )]
    private string $name;

    #[Assert\Length(max: 1000, maxMessage: 'Description cannot be longer than {{ limit }} characters')]
    private ?string $description = null;

    #[Assert\NotBlank(message: 'Price is required')]
    #[Assert\Positive(message: 'Price must be greater than 0')]
    private float $price;

    #[Assert\NotBlank(message: 'Stock is required')]
    #[Assert\PositiveOrZero(message: 'Stock cannot be negative')]
    private int $stock;

    #[Assert\NotBlank(message: 'Category is required')]
    #[Assert\Length(max: 100, maxMessage: 'Category cannot be longer than {{ limit }} characters')]
    private string $category;

    private bool $active = true;

    private DateTimeImmutable $createdAt;

    private ?DateTimeImmutable $updatedAt = null;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? uniqid('prod_', true);
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->touch();
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        $this->touch();
        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;
        $this->touch();
        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): self
    {
        $this->stock = $stock;
        $this->touch();
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        $this->touch();
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        $this->touch();
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'stock' => $this->stock,
            'category' => $this->category,
            'active' => $this->active,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
