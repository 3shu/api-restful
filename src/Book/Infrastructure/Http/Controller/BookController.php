<?php

declare(strict_types=1);

namespace App\Book\Infrastructure\Http\Controller;

use App\Book\Application\UseCase\CreateBookUseCase;
use App\Book\Application\UseCase\UpdateBookUseCase;
use App\Book\Application\UseCase\DeleteBookUseCase;
use App\Book\Application\UseCase\FindBookUseCase;
use App\Book\Application\UseCase\ListBooksUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use OpenApi\Attributes as OA;

#[Route('/api/books')]
#[OA\Tag(name: 'Books', description: 'Book management endpoints (PostgreSQL)')]
class BookController extends AbstractController
{
    #[Route('', name: 'api_books_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/books',
        summary: 'List all books',
        tags: ['Books'],
        parameters: [
            new OA\Parameter(
                name: 'limit',
                description: 'Maximum number of books to return',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 100, example: 10)
            ),
            new OA\Parameter(
                name: 'offset',
                description: 'Number of books to skip',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 0, example: 0)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Book')
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 100),
                                new OA\Property(property: 'limit', type: 'integer', example: 10),
                                new OA\Property(property: 'offset', type: 'integer', example: 0)
                            ],
                            type: 'object'
                        )
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function list(Request $request, ListBooksUseCase $listBooks): JsonResponse
    {
        try {
            $limit = (int) $request->query->get('limit', 100);
            $offset = (int) $request->query->get('offset', 0);

            $result = $listBooks->execute($limit, $offset);

            return $this->json([
                'success' => true,
                'data' => array_map(fn($book) => $book->toArray(), $result['books']),
                'meta' => [
                    'total' => $result['total'],
                    'limit' => $result['limit'],
                    'offset' => $result['offset'],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error fetching books: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'api_books_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/books',
        summary: 'Create a new book',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'author'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Clean Code'),
                    new OA\Property(property: 'author', type: 'string', example: 'Robert C. Martin'),
                    new OA\Property(property: 'isbn', type: 'string', example: '978-0132350884', nullable: true),
                    new OA\Property(property: 'publishedDate', type: 'string', format: 'date', example: '2008-08-01', nullable: true)
                ],
                type: 'object'
            )
        ),
        tags: ['Books'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Book created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Book'),
                        new OA\Property(property: 'message', type: 'string', example: 'Book created successfully')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 409, description: 'ISBN already exists'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    public function create(Request $request, CreateBookUseCase $createBook): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid JSON payload',
                ], Response::HTTP_BAD_REQUEST);
            }

            $book = $createBook->execute($data);

            return $this->json([
                'success' => true,
                'data' => $book->toArray(),
                'message' => 'Book created successfully',
            ], Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        } catch (ValidationFailedException $e) {
            $errors = [];
            foreach ($e->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error creating book: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_books_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/books/{id}',
        summary: 'Get a book by ID',
        tags: ['Books'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Book ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', pattern: '\d+', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Book found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Book')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Book not found'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function show(int $id, FindBookUseCase $findBook): JsonResponse
    {
        try {
            $book = $findBook->execute($id);

            return $this->json([
                'success' => true,
                'data' => $book->toArray(),
            ]);
        } catch (\DomainException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error fetching book: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_books_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/books/{id}',
        summary: 'Update a book',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Clean Code: Updated', nullable: true),
                    new OA\Property(property: 'author', type: 'string', example: 'Robert C. Martin', nullable: true),
                    new OA\Property(property: 'isbn', type: 'string', example: '978-0132350884', nullable: true),
                    new OA\Property(property: 'publishedDate', type: 'string', format: 'date', example: '2008-08-01', nullable: true),
                    new OA\Property(property: 'active', type: 'boolean', example: true, nullable: true)
                ],
                type: 'object'
            )
        ),
        tags: ['Books'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Book ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', pattern: '\d+', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Book updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Book'),
                        new OA\Property(property: 'message', type: 'string', example: 'Book updated successfully')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 404, description: 'Book not found'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    #[OA\Patch(
        path: '/api/books/{id}',
        summary: 'Partially update a book',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Clean Code: Updated', nullable: true),
                    new OA\Property(property: 'author', type: 'string', example: 'Robert C. Martin', nullable: true),
                    new OA\Property(property: 'isbn', type: 'string', example: '978-0132350884', nullable: true),
                    new OA\Property(property: 'publishedDate', type: 'string', format: 'date', example: '2008-08-01', nullable: true),
                    new OA\Property(property: 'active', type: 'boolean', example: true, nullable: true)
                ],
                type: 'object'
            )
        ),
        tags: ['Books'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Book ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', pattern: '\d+', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Book updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Book'),
                        new OA\Property(property: 'message', type: 'string', example: 'Book updated successfully')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 404, description: 'Book not found'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    public function update(int $id, Request $request, UpdateBookUseCase $updateBook): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid JSON payload',
                ], Response::HTTP_BAD_REQUEST);
            }

            $book = $updateBook->execute($id, $data);

            return $this->json([
                'success' => true,
                'data' => $book->toArray(),
                'message' => 'Book updated successfully',
            ]);
        } catch (\DomainException $e) {
            $statusCode = $e->getMessage() === 'Book not found'
                ? Response::HTTP_NOT_FOUND
                : Response::HTTP_CONFLICT;

            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        } catch (ValidationFailedException $e) {
            $errors = [];
            foreach ($e->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }

            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error updating book: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_books_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/books/{id}',
        summary: 'Delete a book',
        tags: ['Books'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Book ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', pattern: '\d+', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Book deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Book deleted successfully')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Book not found')
        ]
    )]
    public function delete(int $id, DeleteBookUseCase $deleteBook): JsonResponse
    {
        try {
            $deleteBook->execute($id);

            return $this->json([
                'success' => true,
                'message' => 'Book deleted successfully',
            ]);
        } catch (\DomainException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error deleting book: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
