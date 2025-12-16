<?php

namespace App\Models;

use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number',
        'content',
        'status',
        'message_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::PENDING);
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::SENT);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::FAILED);
    }

    /**
     * Returns true if message is in final state (sent or failed).
     */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function canBeSent(): bool
    {
        return $this->status === MessageStatus::PENDING;
    }
}
