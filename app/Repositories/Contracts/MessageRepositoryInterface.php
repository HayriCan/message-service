<?php

namespace App\Repositories\Contracts;

use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MessageRepositoryInterface
{
    public function getPendingMessages(int $limit = 100): Collection;

    /** @param array<int> $ids */
    public function markAsProcessing(array $ids): int;

    public function markAsSent(int $id, string $messageId): bool;

    public function markAsFailed(int $id): bool;

    public function getSentMessages(int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Message;

    public function updateStatus(int $id, MessageStatus $status): bool;

    public function resetStaleProcessingMessages(int $minutesThreshold = 5): int;

    /**
     * Reset failed messages back to pending for retry.
     */
    public function resetFailedMessages(): int;
}
