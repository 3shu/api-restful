<?php

declare(strict_types=1);

namespace App\User\Application\UseCase\CreateUser;

/**
 * Create User Command (DTO)
 */
final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
        public string $name,
        public ?string $phone = null
    ) {
    }
}
