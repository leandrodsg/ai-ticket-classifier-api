<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create test classification jobs and tickets
        \App\Models\ClassificationJob::factory()
            ->count(3)
            ->create();

        // Create specific jobs with different statuses
        \App\Models\ClassificationJob::factory()
            ->pending()
            ->create();

        \App\Models\ClassificationJob::factory()
            ->completed()
            ->create();

        \App\Models\ClassificationJob::factory()
            ->failed()
            ->create();
    }
}
