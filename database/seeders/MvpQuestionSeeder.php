<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Question;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class MvpQuestionSeeder extends Seeder
{
    /**
     * Seed the MVP question set used to verify the question listing UI.
     */
    public function run(): void
    {
        $categories = [
            'self_introduction' => '自己紹介',
            'daily_life' => '日常生活',
            'life_study' => '生活・学習',
            'study_habits' => '学習習慣',
            'opinion_explanation' => '意見・説明',
        ];

        $tags = [
            'self_introduction' => '自己紹介',
            'daily_conversation' => '日常会話',
            'comparison' => '比較',
            'experience' => '経験',
            'study' => '学習',
            'opinion' => '意見',
            'explanation' => '説明',
        ];

        foreach ($categories as $slug => $name) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $name.'のMVP確認用スピーチ問題です。',
                    'display_order' => $this->categoryDisplayOrder($slug),
                    'is_active' => true,
                ],
            );
        }

        foreach ($tags as $slug => $name) {
            Tag::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );
        }

        foreach ($this->questions() as $question) {
            $category = Category::query()->where('slug', $question['category_slug'])->firstOrFail();
            $tagIds = Tag::query()->whereIn('slug', $question['tag_slugs'])->pluck('id')->all();

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
                    'has_model_answer' => false,
                    'model_answer_text' => null,
                    'is_published' => true,
                    'display_order' => $question['display_order'],
                ],
            );

            $model->tags()->sync($tagIds);
        }
    }

    private function categoryDisplayOrder(string $slug): int
    {
        return array_search($slug, [
            'self_introduction',
            'daily_life',
            'life_study',
            'study_habits',
            'opinion_explanation',
        ], true) * 10 + 10;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questions(): array
    {
        return [
            ['display_order' => 1, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => 'お名前は何ですか', 'prompt_text' => 'お名前は何ですか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 2, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => 'おいくつですか', 'prompt_text' => 'おいくつですか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 3, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => 'どこから来ましたか', 'prompt_text' => 'どこから来ましたか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 4, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '今、どこに住んでいますか', 'prompt_text' => '今、どこに住んでいますか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 5, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '何人家族ですか', 'prompt_text' => '何人家族ですか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 6, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '兄弟はいますか', 'prompt_text' => '兄弟はいますか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 7, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => 'お父さんの仕事は何ですか', 'prompt_text' => 'お父さんの仕事は何ですか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 8, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => 'お母さんは何をしていますか', 'prompt_text' => 'お母さんは何をしていますか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 9, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '誕生日はいつですか', 'prompt_text' => '誕生日はいつですか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 10, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '血液型は何型ですか', 'prompt_text' => '血液型は何型ですか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 11, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '今日は何曜日ですか', 'prompt_text' => '今日は何曜日ですか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 12, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '今、何時ですか', 'prompt_text' => '今、何時ですか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 13, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '朝ごはんは何を食べましたか', 'prompt_text' => '朝ごはんは何を食べましたか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 14, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '昼ごはんはどこで食べますか', 'prompt_text' => '昼ごはんはどこで食べますか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 15, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '夕ごはんは何を食べたいですか', 'prompt_text' => '夕ごはんは何を食べたいですか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 16, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '何時に起きますか', 'prompt_text' => '何時に起きますか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 17, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'self_introduction', 'tag_slugs' => ['self_introduction', 'daily_conversation'], 'title' => '何時に寝ますか', 'prompt_text' => '何時に寝ますか？', 'recommended_duration_seconds' => 60],
            ['display_order' => 18, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'daily_life', 'tag_slugs' => ['daily_conversation', 'comparison'], 'title' => 'すしが好きですか／ラーメンが好きですか', 'prompt_text' => 'すしが好きですか？／ラーメンが好きですか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 60],
            ['display_order' => 19, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'daily_life', 'tag_slugs' => ['daily_conversation', 'comparison'], 'title' => '甘いものが好きですか／しょっぱいものが好きですか', 'prompt_text' => '甘いものが好きですか？／しょっぱいものが好きですか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 60],
            ['display_order' => 20, 'difficulty' => 'beginner', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'daily_life', 'tag_slugs' => ['daily_conversation', 'comparison'], 'title' => '牛乳をよく飲みますか／ジュースをよく飲みますか', 'prompt_text' => '牛乳をよく飲みますか？／ジュースをよく飲みますか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 60],
            ['display_order' => 21, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => 'あなたの出身地について教えてください', 'prompt_text' => 'あなたの出身地について教えてください。', 'recommended_duration_seconds' => 90],
            ['display_order' => 22, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => '今、どこに住んでいますか どんなところですか', 'prompt_text' => '今、どこに住んでいますか？どんなところですか？', 'recommended_duration_seconds' => 90],
            ['display_order' => 23, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => 'あなたの一日のスケジュールを教えてください', 'prompt_text' => 'あなたの一日のスケジュールを教えてください。', 'recommended_duration_seconds' => 90],
            ['display_order' => 24, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => '住んでいる町のいいところは何ですか', 'prompt_text' => '住んでいる町のいいところは何ですか？', 'recommended_duration_seconds' => 90],
            ['display_order' => 25, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => '好きな場所について話してください', 'prompt_text' => '好きな場所について話してください。', 'recommended_duration_seconds' => 90],
            ['display_order' => 26, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => '日本語を勉強してどのくらいになりますか', 'prompt_text' => '日本語を勉強してどのくらいになりますか？', 'recommended_duration_seconds' => 90],
            ['display_order' => 27, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => 'なぜ日本語を勉強しようと思いましたか', 'prompt_text' => 'なぜ日本語を勉強しようと思いましたか？', 'recommended_duration_seconds' => 90],
            ['display_order' => 28, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => '勉強でむずかしいと思うことは何ですか', 'prompt_text' => '勉強でむずかしいと思うことは何ですか？', 'recommended_duration_seconds' => 90],
            ['display_order' => 29, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => 'あなたの国の学校と日本の学校のちがいは何ですか', 'prompt_text' => 'あなたの国の学校と日本の学校のちがいは何ですか？', 'recommended_duration_seconds' => 90],
            ['display_order' => 30, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => 'どのように日本語の勉強をしていますか', 'prompt_text' => 'どのように日本語の勉強をしていますか？', 'recommended_duration_seconds' => 90],
            ['display_order' => 31, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => 'あなたの趣味は何ですか それについてくわしく話してください', 'prompt_text' => 'あなたの趣味は何ですか？それについてくわしく話してください。', 'recommended_duration_seconds' => 90],
            ['display_order' => 32, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'life_study', 'tag_slugs' => ['daily_conversation', 'experience'], 'title' => '最近、ハマっていることは何ですか', 'prompt_text' => '最近、ハマっていることは何ですか？', 'recommended_duration_seconds' => 90],
            ['display_order' => 33, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'study_habits', 'tag_slugs' => ['study', 'comparison'], 'title' => '勉強は一人でするのが好きですか／友だちとするのが好きですか', 'prompt_text' => '勉強は一人でするのが好きですか？／友だちとするのが好きですか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 90],
            ['display_order' => 34, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'study_habits', 'tag_slugs' => ['study', 'comparison'], 'title' => '毎日少しずつ勉強しますか／一気にまとめて勉強しますか', 'prompt_text' => '毎日少しずつ勉強しますか？／一気にまとめて勉強しますか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 90],
            ['display_order' => 35, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'study_habits', 'tag_slugs' => ['study', 'comparison'], 'title' => '先生に質問しますか／自分で調べますか', 'prompt_text' => '先生に質問しますか？／自分で調べますか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 90],
            ['display_order' => 36, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'study_habits', 'tag_slugs' => ['study', 'comparison'], 'title' => '教科書を使って勉強しますか／アプリを使って勉強しますか', 'prompt_text' => '教科書を使って勉強しますか？／アプリを使って勉強しますか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 90],
            ['display_order' => 37, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'study_habits', 'tag_slugs' => ['study', 'comparison'], 'title' => '話す練習が好きですか／書く練習が好きですか', 'prompt_text' => '話す練習が好きですか？／書く練習が好きですか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 90],
            ['display_order' => 38, 'difficulty' => 'intermediate', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'study_habits', 'tag_slugs' => ['study', 'comparison'], 'title' => '朝早く起きる方ですか／夜遅くまで起きている方ですか', 'prompt_text' => '朝早く起きる方ですか？／夜遅くまで起きている方ですか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 90],
            ['display_order' => 39, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'explanation'], 'title' => 'あなたにとって幸せとは何ですか', 'prompt_text' => 'あなたにとって「幸せ」とは何ですか？', 'recommended_duration_seconds' => 120],
            ['display_order' => 40, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'explanation'], 'title' => '人生で一番大切にしていることは何ですか', 'prompt_text' => '人生で一番大切にしていることは何ですか？', 'recommended_duration_seconds' => 120],
            ['display_order' => 41, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'explanation'], 'title' => '努力と才能 どちらが成功に必要だと思いますか', 'prompt_text' => '努力と才能、どちらが成功に必要だと思いますか？', 'recommended_duration_seconds' => 120],
            ['display_order' => 42, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'explanation'], 'title' => '夢をあきらめたことはありますか 理由は何ですか', 'prompt_text' => '夢をあきらめたことはありますか？理由は何ですか？', 'recommended_duration_seconds' => 120],
            ['display_order' => 43, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'explanation'], 'title' => '生きがいとは何だと思いますか', 'prompt_text' => '「生きがい」とは何だと思いますか？', 'recommended_duration_seconds' => 120],
            ['display_order' => 44, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_SINGLE_PROMPT, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'explanation'], 'title' => '信頼関係を築くために大切なことは何だと思いますか', 'prompt_text' => '信頼関係を築くために大切なことは何だと思いますか？', 'recommended_duration_seconds' => 120],
            ['display_order' => 45, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'comparison'], 'title' => '夢を大切にしますか／現実を大切にしますか', 'prompt_text' => '夢を大切にしますか？／現実を大切にしますか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 120],
            ['display_order' => 46, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'comparison'], 'title' => '成功とはお金だと思いますか／幸せだと思いますか', 'prompt_text' => '成功とはお金だと思いますか？／幸せだと思いますか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 120],
            ['display_order' => 47, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'comparison'], 'title' => 'やりたい仕事を選びますか／安定した仕事を選びますか', 'prompt_text' => 'やりたい仕事を選びますか？／安定した仕事を選びますか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 120],
            ['display_order' => 48, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'comparison'], 'title' => '人は変われると思いますか／変わらないと思いますか', 'prompt_text' => '人は変われると思いますか？／変わらないと思いますか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 120],
            ['display_order' => 49, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'comparison'], 'title' => '大きな目標がある方がいいですか／小さな目標を積み重ねる方がいいですか', 'prompt_text' => '大きな目標がある方がいいですか？／小さな目標を積み重ねる方がいいですか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 120],
            ['display_order' => 50, 'difficulty' => 'advanced', 'question_format' => Question::QUESTION_FORMAT_TWO_CHOICE, 'category_slug' => 'opinion_explanation', 'tag_slugs' => ['opinion', 'comparison'], 'title' => '正直に話す方がいいですか／相手の気持ちを考えて黙る方がいいですか', 'prompt_text' => '正直に話す方がいいですか？／相手の気持ちを考えて黙る方がいいですか？どちらか選んで、理由を話してください。', 'recommended_duration_seconds' => 120],
        ];
    }
}
