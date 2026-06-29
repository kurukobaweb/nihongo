<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const questionOrderOptions = [
    { value: 'order', label: '順番', description: '問題一覧の並びに沿って練習します。' },
    { value: 'random', label: 'ランダム', description: '練習ごとに順序を変える想定の表示です。' },
];

const speechDurationOptions = [
    { value: 'recommended', label: '問題の推奨秒数', description: '各問題に設定された推奨時間を使う想定です。' },
    { value: '10', label: '10秒', description: '短い確認用の時間です。' },
    { value: '40', label: '40秒', description: '短めのスピーチ向けです。' },
    { value: '60', label: '60秒', description: '標準的な練習時間です。' },
    { value: '90', label: '90秒', description: '少し長めに話す練習向けです。' },
    { value: '120', label: '120秒', description: '長めのスピーチ練習向けです。' },
];

const timerDisplayOptions = [
    { value: 'count_up', label: 'カウントアップ', description: '経過時間を確認する想定です。' },
    { value: 'count_down', label: 'カウントダウン', description: '残り時間を確認する想定です。' },
    { value: 'hidden', label: '非表示', description: 'タイマーを見ずに練習する想定です。' },
];

const initialSettings = {
    questionOrder: 'order',
    speechDuration: 'recommended',
    timerDisplay: 'count_down',
    forceFinish: true,
    transcriptVisible: true,
};

const settings = reactive({ ...initialSettings });
const demoSnapshot = reactive({ ...initialSettings });
const demoNotice = ref('');
const demoErrorVisible = ref(false);

const isDirty = computed(() => Object.keys(initialSettings).some((key) => settings[key] !== demoSnapshot[key]));

const selectedSummary = computed(() => [
    questionOrderOptions.find((option) => option.value === settings.questionOrder)?.label,
    speechDurationOptions.find((option) => option.value === settings.speechDuration)?.label,
    timerDisplayOptions.find((option) => option.value === settings.timerDisplay)?.label,
    settings.forceFinish ? '強制終了 ON' : '強制終了 OFF',
    settings.transcriptVisible ? '文字起こし表示 ON' : '文字起こし表示 OFF',
].filter(Boolean).join(' / '));

const applyDemoSave = () => {
    Object.assign(demoSnapshot, settings);
    demoErrorVisible.value = false;
    demoNotice.value = 'この設定はまだ保存されません。画面内の確認用です。保存機能は後続タスクで実装予定です。';
};

const showDemoError = () => {
    demoNotice.value = '';
    demoErrorVisible.value = true;
};
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-slate-100">
        <section class="mx-auto w-full max-w-6xl px-6 py-8">
            <div class="flex flex-col gap-5 border-b border-slate-800 pb-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-medium text-emerald-300">Nihongo</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-normal">設定</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                        練習条件を画面上で調整できます。現在はUI確認用の一時状態のみで、保存先はOI-023で検討中です。
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
                        href="/questions"
                        class="inline-flex items-center justify-center rounded border border-slate-700 px-4 py-2 text-sm font-medium text-slate-100 hover:border-slate-500"
                    >
                        問題一覧へ
                    </Link>
                </div>
            </div>

            <div class="mt-6 rounded border border-amber-800 bg-amber-950 px-4 py-4 text-sm text-amber-100">
                <p class="font-semibold">永続化は未実装です</p>
                <p class="mt-2 leading-6 text-amber-200">
                    設定5項目の保存方式はOI-023で未確定です。この画面ではDB、API、ブラウザ保存を使わず、変更内容は画面内の確認用としてのみ扱います。
                </p>
            </div>

            <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section class="space-y-5">
                    <article class="rounded border border-slate-800 bg-slate-900 p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold tracking-normal text-white">出題方式</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-300">練習で問題を表示する順序を選びます。</p>
                            </div>
                            <span class="text-sm font-medium text-emerald-300">
                                {{ questionOrderOptions.find((option) => option.value === settings.questionOrder)?.label }}
                            </span>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <label
                                v-for="option in questionOrderOptions"
                                :key="option.value"
                                class="block rounded border p-4 transition"
                                :class="settings.questionOrder === option.value ? 'border-emerald-500 bg-emerald-950' : 'border-slate-800 bg-slate-950 hover:border-slate-600'"
                            >
                                <span class="flex items-start gap-3">
                                    <input
                                        v-model="settings.questionOrder"
                                        type="radio"
                                        name="question_order"
                                        :value="option.value"
                                        class="mt-1 accent-emerald-400"
                                    >
                                    <span>
                                        <span class="block text-sm font-semibold text-white">{{ option.label }}</span>
                                        <span class="mt-1 block text-sm leading-6 text-slate-300">{{ option.description }}</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </article>

                    <article class="rounded border border-slate-800 bg-slate-900 p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold tracking-normal text-white">スピーチ時間</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-300">練習時に使う想定の時間表示を選びます。</p>
                            </div>
                            <span class="text-sm font-medium text-emerald-300">
                                {{ speechDurationOptions.find((option) => option.value === settings.speechDuration)?.label }}
                            </span>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <label
                                v-for="option in speechDurationOptions"
                                :key="option.value"
                                class="block rounded border p-4 transition"
                                :class="settings.speechDuration === option.value ? 'border-emerald-500 bg-emerald-950' : 'border-slate-800 bg-slate-950 hover:border-slate-600'"
                            >
                                <span class="flex items-start gap-3">
                                    <input
                                        v-model="settings.speechDuration"
                                        type="radio"
                                        name="speech_duration"
                                        :value="option.value"
                                        class="mt-1 accent-emerald-400"
                                    >
                                    <span>
                                        <span class="block text-sm font-semibold text-white">{{ option.label }}</span>
                                        <span class="mt-1 block text-sm leading-6 text-slate-300">{{ option.description }}</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </article>

                    <article class="rounded border border-slate-800 bg-slate-900 p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold tracking-normal text-white">タイマー表示方式</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-300">録音中のタイマーの見せ方を選びます。</p>
                            </div>
                            <span class="text-sm font-medium text-emerald-300">
                                {{ timerDisplayOptions.find((option) => option.value === settings.timerDisplay)?.label }}
                            </span>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <label
                                v-for="option in timerDisplayOptions"
                                :key="option.value"
                                class="block rounded border p-4 transition"
                                :class="settings.timerDisplay === option.value ? 'border-emerald-500 bg-emerald-950' : 'border-slate-800 bg-slate-950 hover:border-slate-600'"
                            >
                                <span class="flex items-start gap-3">
                                    <input
                                        v-model="settings.timerDisplay"
                                        type="radio"
                                        name="timer_display"
                                        :value="option.value"
                                        class="mt-1 accent-emerald-400"
                                    >
                                    <span>
                                        <span class="block text-sm font-semibold text-white">{{ option.label }}</span>
                                        <span class="mt-1 block text-sm leading-6 text-slate-300">{{ option.description }}</span>
                                    </span>
                                </span>
                            </label>
                        </div>
                    </article>

                    <article class="rounded border border-slate-800 bg-slate-900 p-5">
                        <h2 class="text-lg font-semibold tracking-normal text-white">録音時の補助表示</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-300">強制終了と文字起こし表示のON/OFFを切り替えます。</p>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="flex min-h-32 items-center justify-between gap-4 rounded border border-slate-800 bg-slate-950 p-4">
                                <span>
                                    <span class="block text-sm font-semibold text-white">強制終了</span>
                                    <span class="mt-1 block text-sm leading-6 text-slate-300">時間に達したときに録音を終える想定です。</span>
                                </span>
                                <input
                                    v-model="settings.forceFinish"
                                    type="checkbox"
                                    class="h-6 w-6 shrink-0 accent-emerald-400"
                                >
                            </label>

                            <label class="flex min-h-32 items-center justify-between gap-4 rounded border border-slate-800 bg-slate-950 p-4">
                                <span>
                                    <span class="block text-sm font-semibold text-white">文字起こし表示</span>
                                    <span class="mt-1 block text-sm leading-6 text-slate-300">結果画面でtranscriptを表示する想定です。</span>
                                </span>
                                <input
                                    v-model="settings.transcriptVisible"
                                    type="checkbox"
                                    class="h-6 w-6 shrink-0 accent-emerald-400"
                                >
                            </label>
                        </div>
                    </article>
                </section>

                <aside class="lg:sticky lg:top-6 lg:self-start">
                    <div class="rounded border border-slate-800 bg-slate-900 p-5">
                        <p class="text-sm font-medium text-emerald-300">現在の選択</p>
                        <p class="mt-3 text-sm leading-6 text-slate-200">{{ selectedSummary }}</p>

                        <div
                            class="mt-5 rounded border px-4 py-3 text-sm"
                            :class="isDirty ? 'border-amber-700 bg-amber-950 text-amber-100' : 'border-slate-800 bg-slate-950 text-slate-300'"
                        >
                            <p class="font-semibold">{{ isDirty ? '未保存の変更があります' : '画面内の確認状態と一致しています' }}</p>
                            <p class="mt-1 leading-6">
                                {{ isDirty ? '保存ボタンで画面内デモ状態に反映できます。永続保存は行われません。' : 'この状態も永続保存済みではありません。' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="mt-5 w-full rounded bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400"
                            @click="applyDemoSave"
                        >
                            保存する（画面内デモ）
                        </button>

                        <button
                            type="button"
                            class="mt-3 w-full rounded border border-slate-700 px-4 py-3 text-sm font-medium text-slate-100 transition hover:border-slate-500"
                            @click="showDemoError"
                        >
                            保存失敗表示を確認
                        </button>

                        <div
                            v-if="demoNotice"
                            class="mt-4 rounded border border-emerald-800 bg-emerald-950 px-4 py-3 text-sm text-emerald-100"
                            role="status"
                        >
                            {{ demoNotice }}
                        </div>

                        <div
                            v-if="demoErrorVisible"
                            class="mt-4 rounded border border-red-900 bg-red-950 px-4 py-3 text-sm text-red-100"
                            role="alert"
                        >
                            保存失敗表示のUI確認です。API保存は実行していません。
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </main>
</template>
