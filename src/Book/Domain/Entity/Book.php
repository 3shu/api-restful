<?php

declare(strict_types=1);

namespace App\Book\Domain\Entity;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class Book
{
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Title is required')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Title must be at least {{ limit }} characters',
        maxMessage: 'Title cannot be longer than {{ limit }} characters'
    )]
    private string $title;

    #[Assert\NotBlank(message: 'Author is required')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Author must be at least {{ limit }} characters',
        maxMessage: 'Author cannot be longer than {{ limit }} characters'
    )]
    private string $author;

    #[Assert\Isbn(
        type: Assert\Isbn::ISBN_13,
        message: 'ISBN must be a valid ISBN-13 format'
    )]
    private ?string $isbn = null;

    #[Assert\Date(message: 'Published date must be a valid date')]
    private ?string $publishedDate = null;

    private bool $active = true;

    private DateTimeImmutable $createdAt;

    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->touch();
        return $this;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;
        $this->touch();
        return $this;
    }

    public function getIsbn(): ?string
    {
        return $this->isbn;
    }

    public function setIsbn(?string $isbn): self
    {
        $this->isbn = $isbn;
        $this->touch();
        return $this;
    }

    public function getPublishedDate(): ?string
    {
        return $this->publishedDate;
    }

    public function setPublishedDate(?string $publishedDate): self
    {
        $this->publishedDate = $publishedDate;
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
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'publishedDate' => $this->publishedDate,
            'active' => $this->active,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
