<?php

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone_number' => '+90' . $this->faker->numerify('5#########'),
            'content' => $this->faker->text(config('message.char_limit', 160)),
            'status' => MessageStatus::PENDING,
            'message_id' => null,
            'sent_at' => null,
        ];
    }

    /**
     * Indicate that the message is sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MessageStatus::SENT,
            'message_id' => $this->faker->uuid(),
            'sent_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the message is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MessageStatus::PROCESSING,
        ]);
    }

    /**
     * Indicate that the message has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MessageStatus::FAILED,
        ]);
    }
}
