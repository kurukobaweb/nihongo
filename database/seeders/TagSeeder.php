<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Seed the minimum tags needed for MVP verification.
     */
    public function run(): void
    {
        $tags = [
            [
                'name' => '自己紹介',
                'slug' => 'self_introduction',
            ],
            [
                'name' => '意見',
                'slug' => 'opinion',
            ],
            [
                'name' => '説明',
                'slug' => 'explanation',
            ],
            [
                'name' => 'ビジネス',
                'slug' => 'business',
            ],
            [
                'name' => 'JLPT N3',
                'slug' => 'jlpt_n3',
            ],
        ];

        foreach ($tags as $tag) {
            Tag::query()->updateOrCreate(
                ['slug' => $tag['slug']],
                $tag,
            );
        }
    }
}
