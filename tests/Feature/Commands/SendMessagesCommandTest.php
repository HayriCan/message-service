<?php

namespace Tests\Feature\Commands;

use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendMessagesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_shows_message_when_no_pending_messages(): void
    {
        $this->artisan('messages:send')
            ->expectsOutput('Starting message processing...')
            ->expectsOutput('No pending messages.')
            ->assertExitCode(0);
    }

    public function test_command_dispatches_pending_messages(): void
    {
        Queue::fake();

        Message::factory()->count(5)->create();

        $this->artisan('messages:send')
            ->expectsOutput('Starting message processing...')
            ->expectsOutputToContain('Dispatched 5 message(s)')
            ->assertExitCode(0);
    }

    public function test_command_marks_messages_as_processing(): void
    {
        Queue::fake();

        $messages = Message::factory()->count(3)->create();

        $this->artisan('messages:send')
            ->assertExitCode(0);

        foreach ($messages as $message) {
            $message->refresh();
            $this->assertEquals(MessageStatus::PROCESSING, $message->status);
        }
    }

    public function test_command_respects_limit_option(): void
    {
        Queue::fake();

        Message::factory()->count(10)->create();

        $this->artisan('messages:send', ['--limit' => 5])
            ->expectsOutputToContain('Dispatched 5 message(s)')
            ->assertExitCode(0);

        // Check that 5 are processing and 5 are still pending
        $this->assertEquals(5, Message::where('status', MessageStatus::PROCESSING)->count());
        $this->assertEquals(5, Message::where('status', MessageStatus::PENDING)->count());
    }

    public function test_command_prevents_overlapping_execution(): void
    {
        $lock = Cache::lock('messages:send:lock', 300);
        $lock->get();

        try {
            $this->artisan('messages:send')
                ->expectsOutput('Another instance is already running.')
                ->assertExitCode(1);
        } finally {
            $lock->release();
        }
    }

    public function test_command_resets_stale_messages_when_option_provided(): void
    {
        Queue::fake();

        $staleMessage = Message::factory()->create([
            'status' => MessageStatus::PROCESSING,
            'updated_at' => now()->subMinutes(10),
        ]);

        $this->artisan('messages:send', ['--reset-stale' => true])
            ->expectsOutputToContain('Reset 1 stale message(s)')
            ->assertExitCode(0);

        $staleMessage->refresh();
        $this->assertEquals(MessageStatus::PROCESSING, $staleMessage->status); // Re-processed
    }

    public function test_command_only_processes_pending_messages(): void
    {
        Queue::fake();

        // Create messages with different statuses
        Message::factory()->create(['status' => MessageStatus::PENDING]);
        Message::factory()->create(['status' => MessageStatus::SENT]);
        Message::factory()->create(['status' => MessageStatus::FAILED]);
        Message::factory()->create(['status' => MessageStatus::PROCESSING]);

        $this->artisan('messages:send')
            ->expectsOutputToContain('Dispatched 1 message(s)')
            ->assertExitCode(0);
    }

    public function test_command_calculates_correct_chunks(): void
    {
        Queue::fake();

        // 7 messages with 2 per batch = 4 chunks
        Message::factory()->count(7)->create();

        $this->artisan('messages:send')
            ->expectsOutputToContain('4 chunk(s)')
            ->assertExitCode(0);
    }

    public function test_command_retries_failed_messages_when_option_provided(): void
    {
        Queue::fake();

        $failedMessages = Message::factory()->count(3)->create([
            'status' => MessageStatus::FAILED,
        ]);

        $this->artisan('messages:send', ['--retry-failed' => true])
            ->expectsOutputToContain('Reset 3 failed message(s) for retry')
            ->expectsOutputToContain('Dispatched 3 message(s)')
            ->assertExitCode(0);

        foreach ($failedMessages as $message) {
            $message->refresh();
            $this->assertEquals(MessageStatus::PROCESSING, $message->status);
        }
    }

    public function test_command_does_not_retry_failed_without_option(): void
    {
        Queue::fake();

        Message::factory()->count(2)->create(['status' => MessageStatus::FAILED]);

        $this->artisan('messages:send')
            ->expectsOutput('No pending messages.')
            ->assertExitCode(0);

        $this->assertEquals(2, Message::where('status', MessageStatus::FAILED)->count());
    }
}
