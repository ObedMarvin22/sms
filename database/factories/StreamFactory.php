<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Stream;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stream>
 */
class StreamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(),
            // Test data may be built while another school is current, so look up without the scope.
            'school_id' => fn (array $attributes) => SchoolClass::withoutGlobalScopes()->findOrFail((int) $attributes['school_class_id'])->school_id,
            'name' => 'A',
        ];
    }
}
