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
        $this->call(DemoParentSeeder::class); // a ready parent-app login with fees, attendance and news

        // A term of tasks, marks, comments and sheets (about 15 s a school). The other seeder tests
        // skip it to stay quick; it has its own test (DemoAssessmentSeederTest).
        if (! app()->runningUnitTests()) {
            $this->call(DemoAssessmentSeeder::class);
        }
    }
}
