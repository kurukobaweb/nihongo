<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Seed the minimum public questions needed for MVP verification.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            TagSeeder::class,
        ]);

        $questions = [
            [
                'category_slug' => 'daily_conversation',
                'tag_slugs' => ['self_introduction', 'jlpt_n3'],
                'title' => '自己紹介をしてください',
                'prompt_text' => 'あなたの名前、出身、好きなことについて、1分程度で話してください。',
                'difficulty' => 'beginner',
                'question_format' => 'mvp_verification',
                'recommended_duration_seconds' => 60,
                'has_model_answer' => true,
                'model_answer_text' => 'はじめまして。私は日本語を勉強しています。出身はベトナムです。休みの日は音楽を聞くことが好きです。',
                'is_published' => true,
                'display_order' => 10,
            ],
            [
                'category_slug' => 'work',
                'tag_slugs' => ['business', 'explanation'],
                'title' => '仕事で大切にしていることを説明してください',
                'prompt_text' => 'あなたが仕事で大切にしている考え方や行動について、理由と一緒に話してください。',
                'difficulty' => 'intermediate',
                'question_format' => 'mvp_verification',
                'recommended_duration_seconds' => 90,
                'has_model_answer' => false,
                'model_answer_text' => null,
                'is_published' => true,
                'display_order' => 10,
            ],
            [
                'category_slug' => 'study',
                'tag_slugs' => ['opinion', 'jlpt_n3'],
                'title' => '日本語学習の目標について話してください',
                'prompt_text' => 'あなたが日本語を勉強する目的と、これから達成したい目標について話してください。',
                'difficulty' => 'intermediate',
                'question_format' => 'mvp_verification',
                'recommended_duration_seconds' => 90,
                'has_model_answer' => false,
                'model_answer_text' => null,
                'is_published' => true,
                'display_order' => 10,
            ],
        ];

        foreach ($questions as $question) {
            $category = Category::query()
                ->where('slug', $question['category_slug'])
                ->firstOrFail();

            $tagIds = Tag::query()
                ->whereIn('slug', $question['tag_slugs'])
                ->pluck('id')
                ->all();

            $model = Question::query()->updateOrCreate(
                [
                    'category_id' => $category->id,
                    'title' => $question['title'],
                ],
                [
                    'prompt_text' => $question['prompt_text'],
                    'difficulty' => $question['difficulty'],
                    'question_format' => $question['question_format'],
                    'recommended_duration_seconds' => $question['recommended_duration_seconds'],
                    'has_model_answer' => $question['has_model_answer'],
                    'model_answer_text' => $question['model_answer_text'],
                    'is_published' => $question['is_published'],
                    'display_order' => $question['display_order'],
                ],
            );

            $model->tags()->syncWithoutDetaching($tagIds);
        }
    }
}
