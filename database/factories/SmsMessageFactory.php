<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SmsMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsMessage>
 */
class SmsMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'to' => '+233244000001',
            'message' => 'Test message',
            'segments' => 1,
            'sender' => 'OrangeTree',
            'event' => 'absence',
        ];
    }
}
