<?php

namespace App\Services;

use App\Gateways\Contracts\WebhookGatewayInterface;
use App\Gateways\Exceptions\WebhookException;
use App\Gateways\WebhookResponse;
use App\Jobs\SendMessageJob;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function __construct(
        protected MessageRepositoryInterface $repository,
        protected WebhookGatewayInterface $webhookGateway
    ) {}

    /**
     * Fetches pending messages, locks them, and dispatches to queue.
     * Rate limited: 2 messages per 5 seconds.
     *
     * @return array{dispatched: int, chunks: int}
     */
    public function processPendingMessages(int $limit = 100): array
    {
        $messages = $this->repository->getPendingMessages($limit);

        if ($messages->isEmpty()) {
            return ['dispatched' => 0, 'chunks' => 0];
        }

        // Lock to prevent duplicate processing
        $this->repository->markAsProcessing($messages->pluck('id')->toArray());

        $messagesPerBatch = config('message.rate_limit.messages_per_batch', 2);
        $batchInterval = config('message.rate_limit.batch_interval_seconds', 5);

        $chunks = $messages->chunk($messagesPerBatch);
        $chunkIndex = 0;

        foreach ($chunks as $chunk) {
            $delay = $chunkIndex * $batchInterval;

            foreach ($chunk as $message) {
                SendMessageJob::dispatch($message)
                    ->delay(now()->addSeconds($delay));
            }

            $chunkIndex++;
        }

        Log::info('Messages dispatched', [
            'total' => $messages->count(),
            'chunks' => $chunkIndex,
        ]);

        return [
            'dispatched' => $messages->count(),
            'chunks' => $chunkIndex,
        ];
    }

    /**
     * @throws WebhookException on failure, job will retry
     */
    public function sendMessage(Message $message): WebhookResponse
    {
        return $this->webhookGateway->send(
            to: $message->phone_number,
            content: $message->content
        );
    }

    public function validateContent(string $content): bool
    {
        $charLimit = config('message.char_limit', 160);
        return mb_strlen($content) <= $charLimit;
    }

    public function getCharacterLimit(): int
    {
        return config('message.char_limit', 160);
    }

    /**
     * Cache webhook message ID in Redis for quick lookups.
     */
    public function cacheMessageInfo(int $messageId, string $webhookMessageId): void
    {
        $prefix = config('message.cache.prefix', 'message');
        $ttlHours = config('message.cache.ttl_hours', 24);

        Cache::put(
            "{$prefix}:{$messageId}",
            [
                'message_id' => $webhookMessageId,
                'sent_at' => now()->toIso8601String(),
            ],
            now()->addHours($ttlHours)
        );
    }

    public function getCachedMessageInfo(int $messageId): ?array
    {
        $prefix = config('message.cache.prefix', 'message');
        return Cache::get("{$prefix}:{$messageId}");
    }

    public function markAsSent(int $id, string $messageId): bool
    {
        return $this->repository->markAsSent($id, $messageId);
    }

    public function markAsFailed(int $id): bool
    {
        return $this->repository->markAsFailed($id);
    }

    public function getSentMessages(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getSentMessages($perPage);
    }

    /**
     * Reset messages stuck in processing state back to pending.
     */
    public function resetStaleMessages(int $minutesThreshold = 5): int
    {
        return $this->repository->resetStaleProcessingMessages($minutesThreshold);
    }

    /**
     * Reset failed messages back to pending for retry.
     */
    public function retryFailedMessages(): int
    {
        return $this->repository->resetFailedMessages();
    }
}
