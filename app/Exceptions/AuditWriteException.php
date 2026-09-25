<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AuditWriteException extends RuntimeException
{
    public function __construct(string $message = 'The audit record could not be written.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
