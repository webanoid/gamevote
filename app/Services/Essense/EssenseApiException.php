<?php

namespace App\Services\Essense;

use RuntimeException;
use Throwable;

/**
 * Thrown when the Essense API cannot be reached or does not return a usable response.
 */
class EssenseApiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $errorCode = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
