<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's MVP learning master data.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            TagSeeder::class,
            QuestionSeeder::class,
            MvpQuestionSeeder::class,
        ]);
    }
}
