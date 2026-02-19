<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database\Exceptions;

use RuntimeException;

/**
 * Exception thrown when an unsupported database driver is requested
 */
final class UnsupportedDatabaseException extends RuntimeException
{
}
