<?php

namespace App\Gateways\Contracts;

use App\Gateways\WebhookResponse;

interface WebhookGatewayInterface
{
    /**
     * @param string $to Recipient phone number
     * @param string $content Message content
     * @param string|null $idempotencyKey Unique key to prevent duplicate sends
     *
     * @throws \App\Gateways\Exceptions\WebhookClientException 4xx errors
     * @throws \App\Gateways\Exceptions\WebhookServerException 5xx errors
     * @throws \App\Gateways\Exceptions\WebhookConnectionException Connection or timeout errors
     */
    public function send(string $to, string $content, ?string $idempotencyKey = null): WebhookResponse;
}
