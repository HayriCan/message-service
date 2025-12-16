<?php

namespace Tests\Unit\Services;

use App\Enums\MessageStatus;
use App\Gateways\Contracts\WebhookGatewayInterface;
use App\Gateways\Exceptions\WebhookClientException;
use App\Gateways\Exceptions\WebhookServerException;
use App\Gateways\WebhookResponse;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class MessageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MessageService $service;
    protected MessageRepositoryInterface $repository;
    protected WebhookGatewayInterface|MockInterface $webhookGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(MessageRepositoryInterface::class);
        $this->webhookGateway = Mockery::mock(WebhookGatewayInterface::class);
        $this->service = new MessageService($this->repository, $this->webhookGateway);
    }

    public function test_validate_content_returns_true_for_valid_content(): void
    {
        config(['message.char_limit' => 160]);

        $validContent = str_repeat('a', 160);
        $this->assertTrue($this->service->validateContent($validContent));
    }

    public function test_validate_content_returns_false_for_invalid_content(): void
    {
        config(['message.char_limit' => 160]);

        $invalidContent = str_repeat('a', 161);
        $this->assertFalse($this->service->validateContent($invalidContent));
    }

    public function test_get_character_limit_returns_configured_value(): void
    {
        config(['message.char_limit' => 200]);

        $this->assertEquals(200, $this->service->getCharacterLimit());
    }

    public function test_cache_message_info_stores_data_in_cache(): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $value, $ttl) {
                return $key === 'message:1'
                    && $value['message_id'] === 'test-uuid'
                    && isset($value['sent_at']);
            });

        $this->service->cacheMessageInfo(1, 'test-uuid');
    }

    public function test_get_cached_message_info_returns_cached_data(): void
    {
        $cachedData = [
            'message_id' => 'test-uuid',
            'sent_at' => now()->toIso8601String(),
        ];

        Cache::shouldReceive('get')
            ->once()
            ->with('message:1')
            ->andReturn($cachedData);

        $result = $this->service->getCachedMessageInfo(1);

        $this->assertEquals($cachedData, $result);
    }

    public function test_process_pending_messages_returns_zero_when_no_messages(): void
    {
        $result = $this->service->processPendingMessages();

        $this->assertEquals(0, $result['dispatched']);
        $this->assertEquals(0, $result['chunks']);
    }

    public function test_process_pending_messages_dispatches_jobs(): void
    {
        // Create some pending messages
        Message::factory()->count(5)->create();

        $result = $this->service->processPendingMessages();

        $this->assertEquals(5, $result['dispatched']);
        $this->assertEquals(3, $result['chunks']); // 5 messages, 2 per chunk = 3 chunks
    }

    public function test_mark_as_sent_updates_message_status(): void
    {
        $message = Message::factory()->create([
            'status' => MessageStatus::PROCESSING,
        ]);

        $result = $this->service->markAsSent($message->id, 'webhook-message-id');

        $this->assertTrue($result);

        $message->refresh();
        $this->assertEquals(MessageStatus::SENT, $message->status);
        $this->assertEquals('webhook-message-id', $message->message_id);
        $this->assertNotNull($message->sent_at);
    }

    public function test_mark_as_failed_updates_message_status(): void
    {
        $message = Message::factory()->create([
            'status' => MessageStatus::PROCESSING,
        ]);

        $result = $this->service->markAsFailed($message->id);

        $this->assertTrue($result);

        $message->refresh();
        $this->assertEquals(MessageStatus::FAILED, $message->status);
    }

    public function test_get_sent_messages_returns_only_sent_messages(): void
    {
        // Create mixed messages
        Message::factory()->count(3)->create(); // pending
        Message::factory()->count(2)->sent()->create(); // sent

        $result = $this->service->getSentMessages();

        $this->assertEquals(2, $result->total());
    }

    public function test_send_message_returns_success_response(): void
    {
        $message = Message::factory()->create();
        $expectedIdempotencyKey = sprintf('msg_%d_%d', $message->id, $message->created_at->timestamp);

        $this->webhookGateway
            ->shouldReceive('send')
            ->once()
            ->with($message->phone_number, $message->content, $expectedIdempotencyKey)
            ->andReturn(WebhookResponse::success('test-message-id'));

        $result = $this->service->sendMessage($message);

        $this->assertTrue($result->success);
        $this->assertEquals('test-message-id', $result->messageId);
        $this->assertNull($result->error);
    }

    public function test_send_message_includes_idempotency_key(): void
    {
        $message = Message::factory()->create();
        $expectedIdempotencyKey = sprintf('msg_%d_%d', $message->id, $message->created_at->timestamp);

        $this->webhookGateway
            ->shouldReceive('send')
            ->once()
            ->withArgs(function ($to, $content, $idempotencyKey) use ($message, $expectedIdempotencyKey) {
                return $to === $message->phone_number
                    && $content === $message->content
                    && $idempotencyKey === $expectedIdempotencyKey;
            })
            ->andReturn(WebhookResponse::success('test-message-id'));

        $this->service->sendMessage($message);
    }

    public function test_send_message_throws_client_exception_on_4xx_response(): void
    {
        $message = Message::factory()->create();
        $expectedIdempotencyKey = sprintf('msg_%d_%d', $message->id, $message->created_at->timestamp);

        $this->webhookGateway
            ->shouldReceive('send')
            ->once()
            ->with($message->phone_number, $message->content, $expectedIdempotencyKey)
            ->andThrow(new WebhookClientException('Client error: HTTP 400', 400));

        $this->expectException(WebhookClientException::class);
        $this->expectExceptionMessage('Client error: HTTP 400');

        $this->service->sendMessage($message);
    }

    public function test_send_message_throws_server_exception_on_5xx_response(): void
    {
        $message = Message::factory()->create();
        $expectedIdempotencyKey = sprintf('msg_%d_%d', $message->id, $message->created_at->timestamp);

        $this->webhookGateway
            ->shouldReceive('send')
            ->once()
            ->with($message->phone_number, $message->content, $expectedIdempotencyKey)
            ->andThrow(new WebhookServerException('Server error: HTTP 500', 500));

        $this->expectException(WebhookServerException::class);
        $this->expectExceptionMessage('Server error: HTTP 500');

        $this->service->sendMessage($message);
    }

    public function test_reset_stale_messages_resets_old_processing_messages(): void
    {
        // Create stale processing message
        $staleMessage = Message::factory()->create([
            'status' => MessageStatus::PROCESSING,
            'updated_at' => now()->subMinutes(10),
        ]);

        // Create recent processing message
        $recentMessage = Message::factory()->create([
            'status' => MessageStatus::PROCESSING,
            'updated_at' => now()->subMinutes(2),
        ]);

        $resetCount = $this->service->resetStaleMessages(5);

        $this->assertEquals(1, $resetCount);

        $staleMessage->refresh();
        $recentMessage->refresh();

        $this->assertEquals(MessageStatus::PENDING, $staleMessage->status);
        $this->assertEquals(MessageStatus::PROCESSING, $recentMessage->status);
    }
}
