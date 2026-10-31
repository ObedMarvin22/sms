<?php

namespace Database\Factories;

use App\Models\ParentOtp;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<ParentOtp>
 */
class ParentOtpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'phone' => '+23324'.fake()->unique()->numerify('#######'),
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(ParentOtp::TTL_MINUTES),
        ];
    }
}
