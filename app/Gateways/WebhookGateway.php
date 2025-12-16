<?php

namespace App\Gateways;

use App\Gateways\Contracts\WebhookGatewayInterface;
use App\Gateways\Exceptions\WebhookClientException;
use App\Gateways\Exceptions\WebhookConnectionException;
use App\Gateways\Exceptions\WebhookServerException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WebhookGateway implements WebhookGatewayInterface
{
    public function __construct(
        protected string $url,
        protected string $authKey,
        protected int $timeout = 30
    ) {}

    /**
     * @throws WebhookClientException 4xx errors, won't retry
     * @throws WebhookServerException 5xx errors, will retry
     * @throws WebhookConnectionException Connection or timeout errors, will retry
     */
    public function send(string $to, string $content): WebhookResponse
    {
        try {
            /** @var Response $response */
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-ins-auth-key' => $this->authKey,
                ])
                ->post($this->url, [
                    'to' => $to,
                    'content' => $content,
                ]);

            return $this->handleResponse($response);

        } catch (ConnectionException $e) {
            throw new WebhookConnectionException(
                message: 'Connection failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    protected function handleResponse(Response $response): WebhookResponse
    {
        $status = $response->status();

        if ($status === 202) {
            $messageId = $response->json('messageId');
            return $messageId
                ? WebhookResponse::success($messageId)
                : WebhookResponse::failure('Missing messageId in response');
        }

        if ($status >= 400 && $status < 500) {
            throw new WebhookClientException("Client error: HTTP {$status}", $status);
        }

        if ($status >= 500) {
            throw new WebhookServerException("Server error: HTTP {$status}", $status);
        }

        return WebhookResponse::failure("Unexpected HTTP status: {$status}");
    }
}
