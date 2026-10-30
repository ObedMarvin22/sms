<?php

namespace Database\Factories;

use App\Enums\CreditEntryType;
use App\Models\MessageCreditEntry;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageCreditEntry>
 */
class MessageCreditEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'type' => CreditEntryType::TopUp,
            'units' => 100,
            'note' => 'Test top-up',
        ];
    }
}
