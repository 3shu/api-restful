<?php

declare(strict_types=1);

namespace App\User\Application\UseCase\ListUsers;

use App\User\Domain\Repository\UserRepositoryInterface;

/**
 * List Users Use Case
 * 
 * Retrieves all users with optional filtering
 */
final readonly class ListUsersUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy
     * @return array{users: array, total: int}
     */
    public function execute(array $criteria = [], ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $users = $this->userRepository->findBy($criteria, $orderBy, $limit, $offset);
        $total = $this->userRepository->countUsers();

        return [
            'users' => array_map(fn($user) => $user->toArray(), $users),
            'total' => $total,
        ];
    }
}
