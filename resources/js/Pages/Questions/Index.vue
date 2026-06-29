<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const difficultyOptions = [
    { value: '', label: '全て' },
    { value: 'beginner', label: '初級' },
    { value: 'intermediate', label: '中級' },
    { value: 'advanced', label: '上級' },
];

const formatOptions = [
    { value: '', label: '全て' },
    { value: 'single_prompt', label: '単体問題' },
    { value: 'two_choice', label: '二者択一' },
];

const difficultyLabels = {
    beginner: '初級',
    intermediate: '中級',
    advanced: '上級',
};

const difficulty = ref('');
const questionFormat = ref('');
const questions = ref([]);
const loading = ref(false);
const errorMessage = ref('');

let requestId = 0;

const activeFilterText = computed(() => {
    const labels = [];

    if (difficulty.value) {
        labels.push(difficultyLabels[difficulty.value]);
    }

    if (questionFormat.value) {
        labels.push(formatOptions.find((option) => option.value === questionFormat.value)?.label);
    }

    return labels.filter(Boolean).join(' / ') || '全ての問題';
});

const fetchQuestions = async () => {
    const currentRequest = ++requestId;
    const params = new URLSearchParams();

    if (difficulty.value) {
        params.set('difficulty', difficulty.value);
    }

    if (questionFormat.value) {
        params.set('question_format', questionFormat.value);
    }

    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(`/api/questions${params.toString() ? `?${params.toString()}` : ''}`, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (! response.ok) {
            throw new Error('questions request failed');
        }

        const payload = await response.json();

        if (currentRequest === requestId) {
            questions.value = payload.data ?? [];
        }
    } catch {
        if (currentRequest === requestId) {
            questions.value = [];
            errorMessage.value = '問題一覧を取得できませんでした。時間をおいて再度お試しください。';
        }
    } finally {
        if (currentRequest === requestId) {
            loading.value = false;
        }
    }
};

const selectQuestion = (question) => {
    router.visit(`/dashboard?question_id=${question.id}`);
};

watch([difficulty, questionFormat], fetchQuestions);

onMounted(fetchQuestions);
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-slate-100">
        <section class="mx-auto w-full max-w-6xl px-6 py-8">
            <div class="flex flex-col gap-5 border-b border-slate-800 pb-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-medium text-emerald-300">Nihongo</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-normal">問題一覧</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                        スピーチ練習に使う問題を選択します。難易度と問題形式で絞り込めます。
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Link
                        href="/dashboard"
                        class="inline-flex items-center justify-center rounded border border-slate-700 px-4 py-2 text-sm font-medium text-slate-100 hover:border-slate-500"
                    >
                        学習ホームへ
                    </Link>
                    <Link
                        href="/settings"
                        class="inline-flex items-center justify-center rounded border border-slate-700 px-4 py-2 text-sm font-medium text-slate-100 hover:border-slate-500"
                    >
                        設定へ
                    </Link>
                </div>
            </div>

            <div class="grid gap-4 border-b border-slate-800 py-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                <label class="block">
                    <span class="text-sm font-medium text-slate-200">難易度</span>
                    <select
                        v-model="difficulty"
                        class="mt-2 w-full rounded border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white outline-none focus:border-emerald-400"
                    >
                        <option
                            v-for="option in difficultyOptions"
                            :key="option.value || 'all-difficulty'"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-200">問題形式</span>
                    <select
                        v-model="questionFormat"
                        class="mt-2 w-full rounded border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white outline-none focus:border-emerald-400"
                    >
                        <option
                            v-for="option in formatOptions"
                            :key="option.value || 'all-format'"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>

                <p class="rounded border border-slate-800 bg-slate-900 px-3 py-2 text-sm text-slate-300">
                    {{ activeFilterText }} / {{ questions.length }}件
                </p>
            </div>

            <div v-if="loading" class="py-10 text-sm text-slate-300">
                問題一覧を読み込んでいます。
            </div>

            <div v-else-if="errorMessage" class="mt-6 rounded border border-red-900 bg-red-950 px-4 py-3 text-sm text-red-100">
                {{ errorMessage }}
            </div>

            <div v-else-if="questions.length === 0" class="py-10">
                <p class="text-base font-medium text-slate-100">条件に一致する問題がありません</p>
                <p class="mt-2 text-sm text-slate-400">フィルタ条件を変更してください</p>
            </div>

            <div v-else class="grid gap-4 py-6 lg:grid-cols-2">
                <article
                    v-for="question in questions"
                    :key="question.id"
                    class="rounded border border-slate-800 bg-slate-900 p-5"
                >
                    <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
                        <span class="rounded bg-emerald-500 px-2 py-1 text-slate-950">{{ difficultyLabels[question.difficulty] ?? question.difficulty }}</span>
                        <span class="rounded border border-slate-700 px-2 py-1 text-slate-200">{{ question.question_format.label }}</span>
                        <span class="rounded border border-slate-700 px-2 py-1 text-slate-200">{{ question.recommended_duration_seconds }}秒</span>
                        <span class="rounded border border-slate-700 px-2 py-1 text-slate-200">
                            {{ question.has_model_answer ? '模範解答あり' : '模範解答なし' }}
                        </span>
                    </div>

                    <h2 class="mt-4 text-lg font-semibold tracking-normal text-white">{{ question.title }}</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-300">{{ question.prompt_text }}</p>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-slate-300">
                        <span class="rounded border border-slate-700 px-2 py-1">{{ question.category.name }}</span>
                        <span
                            v-for="tag in question.tags"
                            :key="tag.id"
                            class="rounded border border-slate-700 px-2 py-1"
                        >
                            {{ tag.name }}
                        </span>
                    </div>

                    <button
                        type="button"
                        class="mt-5 w-full rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400"
                        @click="selectQuestion(question)"
                    >
                        この問題を選択
                    </button>
                </article>
            </div>
        </section>
    </main>
</template>
