<?php

declare(strict_types=1);

namespace App\Book\Application\UseCase;

use App\Book\Domain\Repository\BookRepositoryInterface;

class ListBooksUseCase
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository
    ) {
    }

    public function execute(int $limit = 100, int $offset = 0): array
    {
        return [
            'books' => $this->bookRepository->findAll($limit, $offset),
            'total' => $this->bookRepository->count(),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }
}
