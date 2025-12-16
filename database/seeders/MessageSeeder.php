<?php

namespace Database\Seeders;

use App\Models\Message;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Seed sample messages for testing.
     */
    public function run(): void
    {
        // Create 20 pending messages for testing
        Message::factory()->count(20)->create();

        // Create 5 already sent messages
        Message::factory()->count(5)->sent()->create();
    }
}
