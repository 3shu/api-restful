<?php

declare(strict_types=1);

namespace App\User\Application\UseCase\DeleteUser;

use App\User\Domain\Repository\UserRepositoryInterface;

/**
 * Delete User Use Case
 * 
 * Handles the business logic for deleting a user
 */
final readonly class DeleteUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(int $id): void
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            throw new \DomainException(sprintf('User with ID %d not found', $id));
        }

        $this->userRepository->delete($user);
    }
}
