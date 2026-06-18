<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    submission: {
        type: Object,
        required: true,
    },
    question: {
        type: Object,
        required: true,
    },
    evaluation: {
        type: Object,
        required: true,
    },
});

const speedLabels = {
    slow: 'ゆっくり',
    appropriate: '適切',
    fast: '速い',
};

const formatNumber = (value, suffix = '') => {
    if (value === null || value === undefined || value === '') {
        return '未記録';
    }

    return `${value}${suffix}`;
};

const overallScore = computed(() => formatNumber(props.evaluation.overall_score));
const speechRate = computed(() => formatNumber(props.evaluation.characters_per_minute, ' 文字/分'));
const speedAssessment = computed(() => (
    props.evaluation.speed_assessment
        ? speedLabels[props.evaluation.speed_assessment] ?? props.evaluation.speed_assessment
        : '未記録'
));
const durationSeconds = computed(() => formatNumber(props.evaluation.duration_seconds, ' 秒'));
const transcript = computed(() => props.evaluation.transcript || 'transcript は保存されていません。');
const comment = computed(() => props.evaluation.comment || 'コメントはまだありません。');
const questionTitle = computed(() => props.question?.title || '問題');
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-slate-100">
        <section class="mx-auto w-full max-w-5xl px-6 py-8">
            <div class="border-b border-slate-800 pb-6">
                <p class="text-sm font-medium text-emerald-300">Nihongo</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-normal">結果</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                    保存済みの評価結果を表示しています。
                </p>
            </div>

            <section class="grid gap-5 py-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <article class="rounded border border-slate-800 bg-slate-900 p-5">
                    <p class="text-sm font-medium text-emerald-300">問題</p>
                    <h2 class="mt-3 text-2xl font-semibold tracking-normal text-white">{{ questionTitle }}</h2>
                    <p class="mt-4 text-base leading-8 text-slate-200">{{ question.prompt_text || '問題文は保存されていません。' }}</p>

                    <div class="mt-5 flex flex-wrap items-center gap-2 text-xs font-medium">
                        <span
                            v-if="question.question_format?.label"
                            class="rounded border border-slate-700 px-2 py-1 text-slate-200"
                        >
                            {{ question.question_format.label }}
                        </span>
                        <span
                            v-if="question.recommended_duration_seconds"
                            class="rounded border border-slate-700 px-2 py-1 text-slate-200"
                        >
                            {{ question.recommended_duration_seconds }}秒
                        </span>
                        <span
                            v-if="question.category?.name"
                            class="rounded border border-slate-700 px-2 py-1 text-slate-200"
                        >
                            {{ question.category.name }}
                        </span>
                    </div>
                </article>

                <aside class="rounded border border-slate-800 bg-slate-900 p-5">
                    <p class="text-sm font-medium text-emerald-300">submission</p>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-slate-400">status</dt>
                            <dd class="mt-1 font-semibold text-white">{{ submission.status }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">submission_id</dt>
                            <dd class="mt-1 break-all font-mono text-xs text-white">{{ submission.id }}</dd>
                        </div>
                    </dl>
                </aside>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <article class="rounded border border-slate-800 bg-slate-900 p-5">
                    <p class="text-sm font-medium text-slate-400">総合スコア</p>
                    <p class="mt-3 text-3xl font-semibold tracking-normal text-white">{{ overallScore }}</p>
                </article>
                <article class="rounded border border-slate-800 bg-slate-900 p-5">
                    <p class="text-sm font-medium text-slate-400">速度</p>
                    <p class="mt-3 text-2xl font-semibold tracking-normal text-white">{{ speechRate }}</p>
                    <p class="mt-2 text-sm text-slate-300">{{ speedAssessment }}</p>
                </article>
                <article class="rounded border border-slate-800 bg-slate-900 p-5">
                    <p class="text-sm font-medium text-slate-400">認識時間</p>
                    <p class="mt-3 text-2xl font-semibold tracking-normal text-white">{{ durationSeconds }}</p>
                </article>
            </section>

            <section class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.8fr)]">
                <article class="rounded border border-slate-800 bg-slate-900 p-5">
                    <p class="text-sm font-medium text-emerald-300">transcript</p>
                    <p class="mt-3 whitespace-pre-wrap text-base leading-8 text-slate-100">{{ transcript }}</p>
                </article>

                <article class="rounded border border-slate-800 bg-slate-900 p-5">
                    <p class="text-sm font-medium text-emerald-300">コメント</p>
                    <p class="mt-3 text-sm leading-7 text-slate-200">{{ comment }}</p>
                </article>
            </section>

            <div class="mt-6 flex flex-wrap gap-3">
                <Link
                    href="/questions"
                    class="rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                >
                    次の問題を選ぶ
                </Link>
                <Link
                    href="/dashboard"
                    class="rounded border border-slate-700 px-4 py-2 text-sm font-medium text-slate-100 hover:border-slate-500"
                >
                    学習ホームへ
                </Link>
            </div>
        </section>
    </main>
</template>
