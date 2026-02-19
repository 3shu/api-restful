<?php

declare(strict_types=1);

namespace App\Product\Infrastructure\Http\Controller;

use App\Product\Application\UseCase\CreateProductUseCase;
use App\Product\Application\UseCase\UpdateProductUseCase;
use App\Product\Application\UseCase\DeleteProductUseCase;
use App\Product\Application\UseCase\FindProductUseCase;
use App\Product\Application\UseCase\ListProductsUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use OpenApi\Attributes as OA;

#[Route('/api/products')]
#[OA\Tag(name: 'Products', description: 'Product management endpoints (DynamoDB)')]
class ProductController extends AbstractController
{
    #[Route('', name: 'api_products_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/products',
        summary: 'List all products',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(
                name: 'limit',
                description: 'Maximum number of products to return',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 100, example: 10)
            ),
            new OA\Parameter(
                name: 'category',
                description: 'Filter by category',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'Electronics')
            ),
            new OA\Parameter(
                name: 'lastKey',
                description: 'Last evaluated key for pagination',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', nullable: true)
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
                            items: new OA\Items(ref: '#/components/schemas/Product')
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 10),
                                new OA\Property(property: 'limit', type: 'integer', example: 10),
                                new OA\Property(property: 'category', type: 'string', nullable: true, example: 'Electronics')
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
    public function list(Request $request, ListProductsUseCase $listProducts): JsonResponse
    {
        try {
            $limit = (int) $request->query->get('limit', 100);
            $category = $request->query->get('category');
            $lastKey = $request->query->get('lastKey');

            $result = $listProducts->execute($limit, $lastKey, $category);

            return $this->json([
                'success' => true,
                'data' => array_map(fn($product) => $product->toArray(), $result['products']),
                'meta' => [
                    'total' => $result['total'],
                    'limit' => $result['limit'],
                    'category' => $category,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error fetching products: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('', name: 'api_products_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/products',
        summary: 'Create a new product',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'price', 'stock', 'category'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Laptop Dell XPS 15'),
                    new OA\Property(property: 'description', type: 'string', example: 'High-performance laptop for professionals', nullable: true),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 1299.99),
                    new OA\Property(property: 'stock', type: 'integer', example: 50),
                    new OA\Property(property: 'category', type: 'string', example: 'Electronics')
                ],
                type: 'object'
            )
        ),
        tags: ['Products'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Product created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Product'),
                        new OA\Property(property: 'message', type: 'string', example: 'Product created successfully')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    public function create(Request $request, CreateProductUseCase $createProduct): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid JSON payload',
                ], Response::HTTP_BAD_REQUEST);
            }

            $product = $createProduct->execute($data);

            return $this->json([
                'success' => true,
                'data' => $product->toArray(),
                'message' => 'Product created successfully',
            ], Response::HTTP_CREATED);
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
                'message' => 'Error creating product: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_products_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/products/{id}',
        summary: 'Get a product by ID',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Product ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'prod_65c1234567890abcdef')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Product')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function show(string $id, FindProductUseCase $findProduct): JsonResponse
    {
        try {
            $product = $findProduct->execute($id);

            return $this->json([
                'success' => true,
                'data' => $product->toArray(),
            ]);
        } catch (\DomainException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error fetching product: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_products_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/products/{id}',
        summary: 'Update a product',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Laptop Dell XPS 15 (Updated)', nullable: true),
                    new OA\Property(property: 'description', type: 'string', example: 'Updated description', nullable: true),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 1199.99, nullable: true),
                    new OA\Property(property: 'stock', type: 'integer', example: 75, nullable: true),
                    new OA\Property(property: 'category', type: 'string', example: 'Computers', nullable: true),
                    new OA\Property(property: 'active', type: 'boolean', example: true, nullable: true)
                ],
                type: 'object'
            )
        ),
        tags: ['Products'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Product ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'prod_65c1234567890abcdef')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Product'),
                        new OA\Property(property: 'message', type: 'string', example: 'Product updated successfully')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    #[OA\Patch(
        path: '/api/products/{id}',
        summary: 'Partially update a product',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Laptop Dell XPS 15 (Updated)', nullable: true),
                    new OA\Property(property: 'description', type: 'string', example: 'Updated description', nullable: true),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 1199.99, nullable: true),
                    new OA\Property(property: 'stock', type: 'integer', example: 75, nullable: true),
                    new OA\Property(property: 'category', type: 'string', example: 'Computers', nullable: true),
                    new OA\Property(property: 'active', type: 'boolean', example: true, nullable: true)
                ],
                type: 'object'
            )
        ),
        tags: ['Products'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Product ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'prod_65c1234567890abcdef')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Product'),
                        new OA\Property(property: 'message', type: 'string', example: 'Product updated successfully')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Invalid JSON payload'),
            new OA\Response(response: 404, description: 'Product not found'),
            new OA\Response(response: 422, description: 'Validation failed')
        ]
    )]
    public function update(string $id, Request $request, UpdateProductUseCase $updateProduct): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid JSON payload',
                ], Response::HTTP_BAD_REQUEST);
            }

            $product = $updateProduct->execute($id, $data);

            return $this->json([
                'success' => true,
                'data' => $product->toArray(),
                'message' => 'Product updated successfully',
            ]);
        } catch (\DomainException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
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
                'message' => 'Error updating product: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'api_products_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/products/{id}',
        summary: 'Delete a product',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Product ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'prod_65c1234567890abcdef')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Product deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Product deleted successfully')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Product not found')
        ]
    )]
    public function delete(string $id, DeleteProductUseCase $deleteProduct): JsonResponse
    {
        try {
            $deleteProduct->execute($id);

            return $this->json([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);
        } catch (\DomainException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error deleting product: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
