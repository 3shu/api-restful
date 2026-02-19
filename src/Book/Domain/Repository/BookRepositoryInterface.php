<?php

declare(strict_types=1);

namespace App\Book\Domain\Repository;

use App\Book\Domain\Entity\Book;

interface BookRepositoryInterface
{
    public function save(Book $book): void;

    public function findById(int $id): ?Book;

    /**
     * @return Book[]
     */
    public function findAll(int $limit = 100, int $offset = 0): array;

    public function count(): int;

    public function delete(Book $book): void;

    public function existsByIsbn(string $isbn, ?int $excludeId = null): bool;
}
