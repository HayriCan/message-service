<?php

namespace Tests\Unit\Gateways;

use App\Gateways\Exceptions\WebhookClientException;
use App\Gateways\Exceptions\WebhookConnectionException;
use App\Gateways\Exceptions\WebhookServerException;
use App\Gateways\WebhookGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookGatewayTest extends TestCase
{
    protected WebhookGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new WebhookGateway(
            url: 'https://webhook.site/test',
            authKey: 'test-auth-key',
            timeout: 30
        );
    }

    public function test_send_returns_success_response_on_202(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response([
                'message' => 'Accepted',
                'messageId' => 'test-message-id',
            ], 202),
        ]);

        $response = $this->gateway->send('+905551234567', 'Test message');

        $this->assertTrue($response->success);
        $this->assertEquals('test-message-id', $response->messageId);
        $this->assertNull($response->error);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://webhook.site/test'
                && $request->hasHeader('x-ins-auth-key', 'test-auth-key')
                && $request['to'] === '+905551234567'
                && $request['content'] === 'Test message';
        });
    }

    public function test_send_returns_failure_when_message_id_missing(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response([
                'message' => 'Accepted',
            ], 202),
        ]);

        $response = $this->gateway->send('+905551234567', 'Test message');

        $this->assertFalse($response->success);
        $this->assertNull($response->messageId);
        $this->assertEquals('Missing messageId in response', $response->error);
    }

    public function test_send_throws_client_exception_on_4xx(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $this->expectException(WebhookClientException::class);
        $this->expectExceptionMessage('Client error: HTTP 400');

        $this->gateway->send('+905551234567', 'Test message');
    }

    public function test_send_throws_server_exception_on_5xx(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $this->expectException(WebhookServerException::class);
        $this->expectExceptionMessage('Server error: HTTP 500');

        $this->gateway->send('+905551234567', 'Test message');
    }

    public function test_send_throws_connection_exception_on_timeout(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        });

        $this->expectException(WebhookConnectionException::class);
        $this->expectExceptionMessage('Connection failed: Connection timed out');

        $this->gateway->send('+905551234567', 'Test message');
    }

    public function test_client_exception_is_not_retryable(): void
    {
        $exception = new WebhookClientException('Client error', 400);

        $this->assertFalse($exception->isRetryable());
        $this->assertEquals(400, $exception->httpStatus);
    }

    public function test_server_exception_is_retryable(): void
    {
        $exception = new WebhookServerException('Server error', 500);

        $this->assertTrue($exception->isRetryable());
        $this->assertEquals(500, $exception->httpStatus);
    }

    public function test_connection_exception_is_retryable(): void
    {
        $exception = new WebhookConnectionException('Connection failed');

        $this->assertTrue($exception->isRetryable());
        $this->assertNull($exception->httpStatus);
    }
}
