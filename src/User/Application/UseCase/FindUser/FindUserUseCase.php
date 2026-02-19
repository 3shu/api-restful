<?php

declare(strict_types=1);

namespace App\User\Application\UseCase\FindUser;

use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepositoryInterface;

/**
 * Find User Use Case
 * 
 * Retrieves a single user by ID
 */
final readonly class FindUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(int $id): User
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            throw new \DomainException(sprintf('User with ID %d not found', $id));
        }

        return $user;
    }
}
