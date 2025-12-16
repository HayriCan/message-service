<?php

namespace App\Console\Commands;

use App\Services\MessageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendMessagesCommand extends Command
{
    protected $signature = 'messages:send
                            {--limit=100 : Maximum number of messages to process}
                            {--reset-stale : Reset stale processing messages before sending}
                            {--retry-failed : Reset failed messages to pending and retry}';

    protected $description = 'Process and send pending messages via webhook';

    private const LOCK_KEY = 'messages:send:lock';
    private const LOCK_TIMEOUT = 300;

    public function handle(MessageService $service): int
    {
        // only one instance at a time
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TIMEOUT);

        if (!$lock->get()) {
            $this->warn('Another instance is already running.');
            return self::FAILURE;
        }

        try {
            $this->info('Starting message processing...');

            if ($this->option('reset-stale')) {
                $reset = $service->resetStaleMessages();
                if ($reset > 0) {
                    $this->info("Reset {$reset} stale message(s) back to pending.");
                }
            }

            if ($this->option('retry-failed')) {
                $retried = $service->retryFailedMessages();
                if ($retried > 0) {
                    $this->info("Reset {$retried} failed message(s) for retry.");
                }
            }

            $limit = (int) $this->option('limit');
            $result = $service->processPendingMessages($limit);

            if ($result['dispatched'] === 0) {
                $this->info('No pending messages.');
                return self::SUCCESS;
            }

            $this->info("Dispatched {$result['dispatched']} message(s) in {$result['chunks']} chunk(s).");
            $this->newLine();
            $this->info('Run `php artisan queue:work` to process the queue.');

            return self::SUCCESS;

        } finally {
            $lock->release();
        }
    }
}
