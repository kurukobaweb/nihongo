<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    selectedQuestion: {
        type: Object,
        default: null,
    },
    selectedQuestionUnavailable: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const difficultyLabels = {
    beginner: '初級',
    intermediate: '中級',
    advanced: '上級',
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-slate-100">
        <section class="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-12">
            <div>
                <p class="text-sm font-medium text-emerald-300">Nihongo</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-normal">学習ホーム</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                    {{ user?.name }}さん、ログイン中です。選択した問題を確認できます。
                </p>
            </div>

            <div
                v-if="selectedQuestionUnavailable"
                class="mt-8 rounded border border-amber-800 bg-amber-950 px-4 py-3 text-sm text-amber-100"
            >
                <p class="font-medium">選択した問題は表示できません</p>
                <p class="mt-1 text-amber-200">公開状態またはURLを確認してください。問題一覧から選び直してください。</p>
            </div>

            <article
                v-if="selectedQuestion"
                class="mt-8 rounded border border-slate-800 bg-slate-900 p-5"
            >
                <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
                    <span class="rounded bg-emerald-500 px-2 py-1 text-slate-950">
                        {{ difficultyLabels[selectedQuestion.difficulty] ?? selectedQuestion.difficulty }}
                    </span>
                    <span class="rounded border border-slate-700 px-2 py-1 text-slate-200">
                        {{ selectedQuestion.question_format.label }}
                    </span>
                    <span class="rounded border border-slate-700 px-2 py-1 text-slate-200">
                        {{ selectedQuestion.recommended_duration_seconds }}秒
                    </span>
                    <span class="rounded border border-slate-700 px-2 py-1 text-slate-200">
                        {{ selectedQuestion.has_model_answer ? '模範解答あり' : '模範解答なし' }}
                    </span>
                </div>

                <h2 class="mt-4 text-xl font-semibold tracking-normal text-white">{{ selectedQuestion.title }}</h2>
                <p class="mt-3 text-sm leading-6 text-slate-300">{{ selectedQuestion.prompt_text }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-slate-300">
                    <span class="rounded border border-slate-700 px-2 py-1">{{ selectedQuestion.category.name }}</span>
                    <span
                        v-for="tag in selectedQuestion.tags"
                        :key="tag.id"
                        class="rounded border border-slate-700 px-2 py-1"
                    >
                        {{ tag.name }}
                    </span>
                </div>

                <p class="mt-5 rounded border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-300">
                    録音機能は後続タスクで実装します。
                </p>
            </article>

            <div
                v-else
                class="mt-8 rounded border border-slate-800 bg-slate-900 p-5"
            >
                <p class="text-base font-medium text-white">問題一覧から問題を選択してください</p>
                <p class="mt-2 text-sm leading-6 text-slate-300">
                    スピーチ練習に使う問題を選ぶと、このホーム画面に内容が表示されます。
                </p>
                <Link
                    href="/questions"
                    class="mt-4 inline-flex rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                >
                    問題一覧へ移動
                </Link>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <Link
                    href="/questions"
                    class="rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                >
                    問題一覧へ
                </Link>
                <Link
                    href="/"
                    class="rounded border border-slate-700 px-4 py-2 text-sm font-medium text-slate-100 hover:border-slate-500"
                >
                    トップへ
                </Link>
                <button
                    type="button"
                    class="rounded border border-slate-700 px-4 py-2 text-sm font-medium text-slate-100 hover:border-slate-500"
                    @click="logout"
                >
                    ログアウト
                </button>
            </div>
        </section>
    </main>
</template>
