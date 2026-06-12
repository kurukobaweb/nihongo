<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the minimum categories needed for MVP verification.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => '日常会話',
                'slug' => 'daily_conversation',
                'description' => '日常生活で使う基本的な日本語スピーチ練習です。',
                'display_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => '仕事',
                'slug' => 'work',
                'description' => '職場やビジネス場面を想定した日本語スピーチ練習です。',
                'display_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => '学習',
                'slug' => 'study',
                'description' => '学習計画や日本語学習について話す練習です。',
                'display_order' => 30,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(
                ['slug' => $category['slug']],
                $category,
            );
        }
    }
}
