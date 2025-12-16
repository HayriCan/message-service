<?php

namespace App\Repositories;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MessageRepository implements MessageRepositoryInterface
{
    public function __construct(
        protected Message $model
    ) {}

    public function getPendingMessages(int $limit = 100): Collection
    {
        return $this->model
            ->pending()
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * @param array<int> $ids
     */
    public function markAsProcessing(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        return $this->model
            ->whereIn('id', $ids)
            ->where('status', MessageStatus::PENDING)
            ->update([
                'status' => MessageStatus::PROCESSING,
                'updated_at' => now(),
            ]);
    }

    public function markAsSent(int $id, string $messageId): bool
    {
        $updated = $this->model
            ->where('id', $id)
            ->update([
                'status' => MessageStatus::SENT,
                'message_id' => $messageId,
                'sent_at' => now(),
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    public function markAsFailed(int $id): bool
    {
        $updated = $this->model
            ->where('id', $id)
            ->update([
                'status' => MessageStatus::FAILED,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    public function getSentMessages(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->sent()
            ->orderBy('sent_at', 'desc')
            ->paginate($perPage);
    }

    public function find(int $id): ?Message
    {
        return $this->model->find($id);
    }

    public function updateStatus(int $id, MessageStatus $status): bool
    {
        $updated = $this->model
            ->where('id', $id)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    /**
     * Reset messages stuck in processing state back to pending.
     */
    public function resetStaleProcessingMessages(int $minutesThreshold = 5): int
    {
        return $this->model
            ->where('status', MessageStatus::PROCESSING)
            ->where('updated_at', '<', now()->subMinutes($minutesThreshold))
            ->update([
                'status' => MessageStatus::PENDING,
                'updated_at' => now(),
            ]);
    }

    public function resetFailedMessages(): int
    {
        return $this->model
            ->where('status', MessageStatus::FAILED)
            ->update([
                'status' => MessageStatus::PENDING,
                'updated_at' => now(),
            ]);
    }
}
