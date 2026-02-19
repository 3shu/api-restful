<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Http\Controller;

use App\User\Application\UseCase\CreateUser\CreateUserCommand;
use App\User\Application\UseCase\CreateUser\CreateUserUseCase;
use App\User\Application\UseCase\UpdateUser\UpdateUserCommand;
use App\User\Application\UseCase\UpdateUser\UpdateUserUseCase;
use App\User\Application\UseCase\DeleteUser\DeleteUserUseCase;
use App\User\Application\UseCase\FindUser\FindUserUseCase;
use App\User\Application\UseCase\ListUsers\ListUsersUseCase;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * User REST API Controller
 * 
 * Provides CRUD endpoints for User management
 */
#[Route('/api/users', name: 'api_users_')]
#[OA\Tag(name: 'Users', description: 'User management endpoints (MySQL)')]
class UserController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users',
        summary: 'List all users',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                description: 'Maximum number of users to return',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 100, example: 10)
            ),
            new OA\Parameter(
                name: 'offset',
                in: 'query',
                description: 'Number of users to skip',
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
                            items: new OA\Items(ref: '#/components/schemas/User')
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
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function list(Request $request, ListUsersUseCase $useCase): JsonResponse
    {
        try {
            $limit = $request->query->getInt('limit', 100);
            $offset = $request->query->getInt('offset', 0);
            
            $result = $useCase->execute(
                criteria: [],
                orderBy: ['createdAt' => 'DESC'],
                limit: $limit,
                offset: $offset
            );

            return $this->json([
                'success' => true,
                'data' => $result['users'],
                'meta' => [
                    'total' => $result['total'],
                    'limit' => $limit,
                    'offset' => $offset,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Get a user by ID',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'User ID',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function show(int $id, FindUserUseCase $useCase): JsonResponse
    {
        try {
            $user = $useCase->execute($id);

            return $this->json([
                'success' => true,
                'data' => $user->toArray(),
            ]);
        } catch (\DomainException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        summary: 'Create a new user',
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'name'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '1234567890')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'message', type: 'string', example: 'User created successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 409, description: 'Email already exists'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    public function create(Request $request, CreateUserUseCase $useCase): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!is_array($data)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid JSON payload',
                ], Response::HTTP_BAD_REQUEST);
            }

            $command = new CreateUserCommand(
                email: $data['email'] ?? '',
                name: $data['name'] ?? '',
                phone: $data['phone'] ?? null
            );

            $user = $useCase->execute($command);

            return $this->json([
                'success' => true,
                'data' => $user->toArray(),
                'message' => 'User created successfully',
            ], Response::HTTP_CREATED);
        } catch (ValidationFailedException $e) {
            $errors = [];
            foreach ($e->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            
            return $this->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        path: '/api/users/{id}',
        summary: 'Update a user',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'User ID',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'newemail@example.com'),
                    new OA\Property(property: 'name', type: 'string', nullable: true, example: 'New Name'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '9876543210'),
                    new OA\Property(property: 'active', type: 'boolean', nullable: true, example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'message', type: 'string', example: 'User updated successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    #[OA\Patch(
        path: '/api/users/{id}',
        summary: 'Partially update a user',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'User ID',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'newemail@example.com'),
                    new OA\Property(property: 'name', type: 'string', nullable: true, example: 'New Name'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '9876543210'),
                    new OA\Property(property: 'active', type: 'boolean', nullable: true, example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'message', type: 'string', example: 'User updated successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    public function update(int $id, Request $request, UpdateUserUseCase $useCase): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!is_array($data)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid JSON payload',
                ], Response::HTTP_BAD_REQUEST);
            }

            $command = new UpdateUserCommand(
                id: $id,
                email: $data['email'] ?? null,
                name: $data['name'] ?? null,
                phone: $data['phone'] ?? null,
                active: isset($data['active']) ? (bool) $data['active'] : null
            );

            $user = $useCase->execute($command);

            return $this->json([
                'success' => true,
                'data' => $user->toArray(),
                'message' => 'User updated successfully',
            ]);
        } catch (ValidationFailedException $e) {
            $errors = [];
            foreach ($e->getViolations() as $violation) {
                $errors[$violation->getPropertyPath()] = $violation->getMessage();
            }
            
            return $this->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a user
     * DELETE /api/users/{id}
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id, DeleteUserUseCase $useCase): JsonResponse
    {
        try {
            $useCase->execute($id);

            return $this->json([
                'success' => true,
                'message' => 'User deleted successfully',
            ]);
        } catch (\DomainException $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
