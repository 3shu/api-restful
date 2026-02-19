<?php

declare(strict_types=1);

namespace App\User\Application\UseCase\UpdateUser;

use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Update User Use Case
 * 
 * Handles the business logic for updating an existing user
 */
final readonly class UpdateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ValidatorInterface $validator
    ) {
    }

    public function execute(UpdateUserCommand $command): User
    {
        // Find user
        $user = $this->userRepository->findById($command->id);
        if ($user === null) {
            throw new \DomainException(sprintf('User with ID %d not found', $command->id));
        }

        // Update fields if provided
        if ($command->email !== null) {
            // Check if new email already exists
            $existingUser = $this->userRepository->findByEmail($command->email);
            if ($existingUser !== null && $existingUser->getId() !== $user->getId()) {
                throw new \DomainException(sprintf('Email "%s" is already in use', $command->email));
            }
            $user->setEmail($command->email);
        }

        if ($command->name !== null) {
            $user->setName($command->name);
        }

        if ($command->phone !== null) {
            $user->setPhone($command->phone);
        }

        if ($command->active !== null) {
            $command->active ? $user->activate() : $user->deactivate();
        }

        // Validate entity
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            throw new ValidationFailedException($user, $errors);
        }

        // Persist changes
        $this->userRepository->save($user);

        return $user;
    }
}
