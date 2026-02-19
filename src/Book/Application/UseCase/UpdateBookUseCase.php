<?php

declare(strict_types=1);

namespace App\Book\Application\UseCase;

use App\Book\Domain\Entity\Book;
use App\Book\Domain\Repository\BookRepositoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class UpdateBookUseCase
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function execute(int $id, array $data): Book
    {
        $book = $this->bookRepository->findById($id);
        
        if ($book === null) {
            throw new \DomainException('Book not found');
        }

        // Check ISBN uniqueness if being updated
        if (isset($data['isbn']) && $data['isbn'] !== $book->getIsbn()) {
            if ($this->bookRepository->existsByIsbn($data['isbn'], $id)) {
                throw new \DomainException('ISBN already exists');
            }
        }

        if (isset($data['title'])) {
            $book->setTitle($data['title']);
        }

        if (isset($data['author'])) {
            $book->setAuthor($data['author']);
        }

        if (array_key_exists('isbn', $data)) {
            $book->setIsbn($data['isbn']);
        }

        if (array_key_exists('publishedDate', $data)) {
            $book->setPublishedDate($data['publishedDate']);
        }

        if (isset($data['active'])) {
            $book->setActive((bool) $data['active']);
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
