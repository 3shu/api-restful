<?php

declare(strict_types=1);

namespace App\Book\Infrastructure\Persistence\Doctrine;

use App\Book\Domain\Entity\Book;
use App\Book\Domain\Repository\BookRepositoryInterface;
use Doctrine\DBAL\Connection;
use DateTimeImmutable;

class BookRepository implements BookRepositoryInterface
{
    public function __construct(
        private readonly Connection $postgresBooks
    ) {
    }

    public function save(Book $book): void
    {
        if ($book->getId() === null) {
            $this->insert($book);
        } else {
            $this->update($book);
        }
    }

    private function insert(Book $book): void
    {
        $this->postgresBooks->insert('books', [
            'title' => $book->getTitle(),
            'author' => $book->getAuthor(),
            'isbn' => $book->getIsbn(),
            'published_date' => $book->getPublishedDate(),
            'active' => $book->isActive() ? 1 : 0,
            'created_at' => $book->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $book->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->postgresBooks->lastInsertId('books_id_seq');
        $book->setId($id);
    }

    private function update(Book $book): void
    {
        $this->postgresBooks->update('books', [
            'title' => $book->getTitle(),
            'author' => $book->getAuthor(),
            'isbn' => $book->getIsbn(),
            'published_date' => $book->getPublishedDate(),
            'active' => $book->isActive() ? 1 : 0,
            'updated_at' => $book->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ], ['id' => $book->getId()]);
    }

    public function findById(int $id): ?Book
    {
        $data = $this->postgresBooks->fetchAssociative(
            'SELECT * FROM books WHERE id = :id',
            ['id' => $id]
        );

        if ($data === false) {
            return null;
        }

        return $this->hydrate($data);
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $data = $this->postgresBooks->fetchAllAssociative(
            'SELECT * FROM books ORDER BY id DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );

        return array_map(fn(array $row) => $this->hydrate($row), $data);
    }

    public function count(): int
    {
        return (int) $this->postgresBooks->fetchOne('SELECT COUNT(*) FROM books');
    }

    public function delete(Book $book): void
    {
        $this->postgresBooks->delete('books', ['id' => $book->getId()]);
    }

    public function existsByIsbn(string $isbn, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM books WHERE isbn = :isbn';
        $params = ['isbn' => $isbn];

        if ($excludeId !== null) {
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = $excludeId;
        }

        return (int) $this->postgresBooks->fetchOne($sql, $params) > 0;
    }

    private function hydrate(array $data): Book
    {
        $book = new Book();
        $book->setId((int) $data['id']);
        $book->setTitle($data['title']);
        $book->setAuthor($data['author']);
        $book->setIsbn($data['isbn']);
        $book->setPublishedDate($data['published_date']);
        $book->setActive((bool) $data['active']);

        // Use reflection to set readonly properties
        $reflection = new \ReflectionClass($book);
        
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($book, new DateTimeImmutable($data['created_at']));

        if ($data['updated_at'] !== null) {
            $updatedAtProperty = $reflection->getProperty('updatedAt');
            $updatedAtProperty->setAccessible(true);
            $updatedAtProperty->setValue($book, new DateTimeImmutable($data['updated_at']));
        }

        return $book;
    }
}
