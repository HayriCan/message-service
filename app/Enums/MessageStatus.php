<?php

namespace App\Enums;

enum MessageStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::SENT => 'Sent',
            self::FAILED => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::SENT, self::FAILED]);
    }
}
