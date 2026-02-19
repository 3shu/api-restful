<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a database connection cannot be found or established
 */
final class ConnectionNotFoundException extends RuntimeException
{
}
