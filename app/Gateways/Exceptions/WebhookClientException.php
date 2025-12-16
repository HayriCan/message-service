<?php

namespace App\Gateways\Exceptions;

/** 4xx - client error, won't retry */
class WebhookClientException extends WebhookException
{
    public function isRetryable(): bool
    {
        return false;
    }
}
