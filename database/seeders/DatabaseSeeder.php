<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events stay on (no WithoutModelEvents): BelongsToSchool and the activity log need them.
     */
    public function run(): void
    {
        $this->call(DemoSchoolsSeeder::class);
    }
}
