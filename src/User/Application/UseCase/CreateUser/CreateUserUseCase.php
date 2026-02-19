<?php

declare(strict_types=1);

namespace App\User\Application\UseCase\CreateUser;

use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Create User Use Case
 * 
 * Handles the business logic for creating a new user
 */
final readonly class CreateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ValidatorInterface $validator
    ) {
    }

    public function execute(CreateUserCommand $command): User
    {
        // Check if email already exists
        $existingUser = $this->userRepository->findByEmail($command->email);
        if ($existingUser !== null) {
            throw new \DomainException(sprintf('User with email "%s" already exists', $command->email));
        }

        // Create user entity
        $user = new User(
            email: $command->email,
            name: $command->name,
            phone: $command->phone
        );

        // Validate entity
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            throw new ValidationFailedException($user, $errors);
        }

        // Persist user
        $this->userRepository->save($user);

        return $user;
    }
}
