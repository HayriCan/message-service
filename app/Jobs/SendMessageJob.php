<?php

namespace App\Jobs;

use App\Gateways\Exceptions\WebhookClientException;
use App\Gateways\Exceptions\WebhookException;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> Retry delays in seconds */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public Message $message
    ) {}

    public function handle(MessageService $service): void
    {
        if (!$service->validateContent($this->message->content)) {
            Log::warning('Message content exceeds character limit', [
                'message_id' => $this->message->id,
                'content_length' => mb_strlen($this->message->content),
                'limit' => $service->getCharacterLimit(),
            ]);

            $service->markAsFailed($this->message->id);
            return;
        }

        try {
            $response = $service->sendMessage($this->message);

            if ($response->success) {
                $service->markAsSent($this->message->id, $response->messageId);
                $service->cacheMessageInfo($this->message->id, $response->messageId);

                Log::info('Message sent successfully', [
                    'message_id' => $this->message->id,
                    'webhook_message_id' => $response->messageId,
                ]);
            } else {
                Log::error('Message send failed', [
                    'message_id' => $this->message->id,
                    'error' => $response->error,
                ]);

                $service->markAsFailed($this->message->id);
            }
        } catch (WebhookClientException $e) {
            // 4xx - client error, no retry
            Log::error('Message send failed (client error)', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
                'http_status' => $e->httpStatus,
            ]);

            $service->markAsFailed($this->message->id);
        } catch (WebhookException $e) {
            // 5xx or connection error, will retry
            Log::warning('Message send failed (will retry)', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Called when all retry attempts are exhausted.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('Message send permanently failed', [
            'message_id' => $this->message->id,
            'error' => $exception?->getMessage(),
        ]);

        app(MessageService::class)->markAsFailed($this->message->id);
    }
}
