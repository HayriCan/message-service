<?php

namespace App\Gateways\Exceptions;

/** Connection failed or timeout, will retry */
class WebhookConnectionException extends WebhookException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
