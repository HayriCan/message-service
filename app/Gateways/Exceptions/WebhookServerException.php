<?php

namespace App\Gateways\Exceptions;

/** 5xx - server unavailable, will retry */
class WebhookServerException extends WebhookException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
