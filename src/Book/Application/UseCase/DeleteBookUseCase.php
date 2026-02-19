<?php

declare(strict_types=1);

namespace App\Book\Application\UseCase;

use App\Book\Domain\Repository\BookRepositoryInterface;

class DeleteBookUseCase
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository
    ) {
    }

    public function execute(int $id): void
    {
        $book = $this->bookRepository->findById($id);
        
        if ($book === null) {
            throw new \DomainException('Book not found');
        }

        $this->bookRepository->delete($book);
    }
}
