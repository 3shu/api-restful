<?php

declare(strict_types=1);

namespace App\Book\Application\UseCase;

use App\Book\Domain\Entity\Book;
use App\Book\Domain\Repository\BookRepositoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class CreateBookUseCase
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function execute(array $data): Book
    {
        // Check ISBN uniqueness if provided
        if (isset($data['isbn']) && $this->bookRepository->existsByIsbn($data['isbn'])) {
            throw new \DomainException('ISBN already exists');
        }

        $book = new Book();
        $book->setTitle($data['title'] ?? '');
        $book->setAuthor($data['author'] ?? '');
        
        if (isset($data['isbn'])) {
            $book->setIsbn($data['isbn']);
        }
        
        if (isset($data['publishedDate'])) {
            $book->setPublishedDate($data['publishedDate']);
        }

        // Validate entity
        $violations = $this->validator->validate($book);
        if (count($violations) > 0) {
            throw new ValidationFailedException($book, $violations);
        }

        $this->bookRepository->save($book);

        return $book;
    }
}
