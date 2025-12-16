<?php

namespace App\Gateways\Exceptions;

use Exception;

abstract class WebhookException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    abstract public function isRetryable(): bool;
}
