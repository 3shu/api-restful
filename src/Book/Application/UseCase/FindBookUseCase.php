<?php

declare(strict_types=1);

namespace App\Book\Application\UseCase;

use App\Book\Domain\Entity\Book;
use App\Book\Domain\Repository\BookRepositoryInterface;

class FindBookUseCase
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository
    ) {
    }

    public function execute(int $id): Book
    {
        $book = $this->bookRepository->findById($id);
        
        if ($book === null) {
            throw new \DomainException('Book not found');
        }

        return $book;
    }
}
