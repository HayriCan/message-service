<?php

namespace Tests\Feature\Api;

use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_sent_messages_returns_empty_when_no_messages(): void
    {
        $response = $this->getJson('/api/messages/sent');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ])
            ->assertJsonCount(0, 'data');
    }

    public function test_get_sent_messages_returns_only_sent_messages(): void
    {
        // Create pending messages (should not appear)
        Message::factory()->count(3)->create();

        // Create sent messages (should appear)
        Message::factory()->count(2)->sent()->create();

        $response = $this->getJson('/api/messages/sent');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_get_sent_messages_returns_correct_structure(): void
    {
        Message::factory()->sent()->create([
            'phone_number' => '+905551234567',
            'content' => 'Test message content',
        ]);

        $response = $this->getJson('/api/messages/sent');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'phone_number',
                        'content',
                        'message_id',
                        'sent_at',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);
    }

    public function test_get_sent_messages_respects_per_page_parameter(): void
    {
        Message::factory()->count(20)->sent()->create();

        $response = $this->getJson('/api/messages/sent?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    }

    public function test_get_sent_messages_caps_per_page_at_100(): void
    {
        Message::factory()->count(150)->sent()->create();

        $response = $this->getJson('/api/messages/sent?per_page=200');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_get_sent_messages_supports_pagination(): void
    {
        Message::factory()->count(30)->sent()->create();

        $response = $this->getJson('/api/messages/sent?page=2&per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(10, 'data');
    }

    public function test_get_sent_messages_returns_messages_ordered_by_sent_at_desc(): void
    {
        $oldMessage = Message::factory()->sent()->create([
            'sent_at' => now()->subDays(2),
        ]);

        $newMessage = Message::factory()->sent()->create([
            'sent_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/messages/sent');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Newest message should be first
        $this->assertEquals($newMessage->id, $data[0]['id']);
        $this->assertEquals($oldMessage->id, $data[1]['id']);
    }

    public function test_get_sent_messages_returns_iso8601_date_format(): void
    {
        Message::factory()->sent()->create();

        $response = $this->getJson('/api/messages/sent');

        $response->assertStatus(200);

        $sentAt = $response->json('data.0.sent_at');

        // ISO 8601 format check
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $sentAt
        );
    }
}
