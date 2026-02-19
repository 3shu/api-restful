<?php

declare(strict_types=1);

namespace App\User\Application\UseCase\UpdateUser;

/**
 * Update User Command (DTO)
 */
final readonly class UpdateUserCommand
{
    public function __construct(
        public int $id,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $phone = null,
        public ?bool $active = null
    ) {
    }
}
