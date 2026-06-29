<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
});

const page = usePage();

const questionFormatOptions = [
    { value: 'single_prompt', label: '単体問題', description: '1つの出題文に対してスピーチを練習します。' },
    { value: 'two_choice', label: '二者択一', description: '2つの選択肢から立場を選んで話す練習に使います。' },
];

const speechDurationOptions = [
    { value: 30, label: '30秒', description: '短い回答やウォームアップ向けです。' },
    { value: 60, label: '60秒', description: '標準的な練習時間です。' },
    { value: 90, label: '90秒', description: '少し長めに説明する練習向けです。' },
    { value: 120, label: '120秒', description: '構成を意識して話す練習向けです。' },
    { value: 180, label: '180秒', description: '長めのスピーチ練習向けです。' },
];

const timerDisplayOptions = [
    { value: 'count_down', label: 'カウントダウン', description: '残り時間を見ながら話します。' },
    { value: 'count_up', label: 'カウントアップ', description: '経過時間を見ながら話します。' },
    { value: 'hidden', label: '非表示', description: 'タイマーを見ずに練習します。' },
];

const form = useForm({
    question_format_preference: props.settings.question_format_preference,
    speech_duration_seconds: props.settings.speech_duration_seconds,
    timer_display_mode: props.settings.timer_display_mode,
    force_stop_enabled: props.settings.force_stop_enabled,
    transcript_display_enabled: props.settings.transcript_display_enabled,
});

const savedSettings = ref({ ...props.settings });
const successMessage = ref('');

watch(
    () => props.settings,
    (settings) => {
        savedSettings.value = { ...settings };
    },
    { deep: true },
);

const isDirty = computed(() => Object.keys(savedSettings.value).some((key) => form[key] !== savedSettings.value[key]));

const selectedSummary = computed(() => [
    questionFormatOptions.find((option) => option.value === form.question_format_preference)?.label,
    speechDurationOptions.find((option) => option.value === Number(form.speech_duration_seconds))?.label,
    timerDisplayOptions.find((option) => option.value === form.timer_display_mode)?.label,
    form.force_stop_enabled ? '強制終了 ON' : '強制終了 OFF',
    form.transcript_display_enabled ? '文字起こし表示 ON' : '文字起こし表示 OFF',
].filter(Boolean).join(' / '));

const hasErrors = computed(() => Object.keys(form.errors).length > 0);

const submit = () => {
    successMessage.value = '';

    form.put('/settings', {
        preserveScroll: true,
        onSuccess: () => {
            savedSettings.value = {
                question_format_preference: form.question_format_preference,
                speech_duration_seconds: form.speech_duration_seconds,
                timer_display_mode: form.timer_display_mode,
                force_stop_enabled: form.force_stop_enabled,
                transcript_display_enabled: form.transcript_display_enabled,
            };
            successMessage.value = page.props.flash?.status === 'settings-saved'
                ? '設定を保存しました。次回表示時もこの内容が読み込まれます。'
                : '設定を保存しました。';
            form.clearErrors();
        },
    });
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
                        練習条件をログインユーザーごとに保存できます。保存した内容は設定画面を開き直したときにも再読込されます。
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

            <div class="mt-6 rounded border border-emerald-800 bg-emerald-950 px-4 py-4 text-sm text-emerald-100">
                <p class="font-semibold">設定保存が有効です</p>
                <p class="mt-2 leading-6 text-emerald-200">
                    設定5項目はログインユーザーごとの専用保存先へ保存されます。
                </p>
            </div>

            <form class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]" @submit.prevent="submit">
                <section class="space-y-5">
                    <article class="rounded border border-slate-800 bg-slate-900 p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold tracking-normal text-white">出題方式</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-300">練習で使う問題形式の希望を選びます。</p>
                            </div>
                            <span class="text-sm font-medium text-emerald-300">
                                {{ questionFormatOptions.find((option) => option.value === form.question_format_preference)?.label }}
                            </span>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <label
                                v-for="option in questionFormatOptions"
                                :key="option.value"
                                class="block rounded border p-4 transition"
                                :class="form.question_format_preference === option.value ? 'border-emerald-500 bg-emerald-950' : 'border-slate-800 bg-slate-950 hover:border-slate-600'"
                            >
                                <span class="flex items-start gap-3">
                                    <input
                                        v-model="form.question_format_preference"
                                        type="radio"
                                        name="question_format_preference"
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
                        <p v-if="form.errors.question_format_preference" class="mt-3 text-sm text-red-300">
                            {{ form.errors.question_format_preference }}
                        </p>
                    </article>

                    <article class="rounded border border-slate-800 bg-slate-900 p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold tracking-normal text-white">スピーチ時間</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-300">録音時に使う予定時間を選びます。</p>
                            </div>
                            <span class="text-sm font-medium text-emerald-300">
                                {{ speechDurationOptions.find((option) => option.value === Number(form.speech_duration_seconds))?.label }}
                            </span>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <label
                                v-for="option in speechDurationOptions"
                                :key="option.value"
                                class="block rounded border p-4 transition"
                                :class="Number(form.speech_duration_seconds) === option.value ? 'border-emerald-500 bg-emerald-950' : 'border-slate-800 bg-slate-950 hover:border-slate-600'"
                            >
                                <span class="flex items-start gap-3">
                                    <input
                                        v-model.number="form.speech_duration_seconds"
                                        type="radio"
                                        name="speech_duration_seconds"
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
                        <p v-if="form.errors.speech_duration_seconds" class="mt-3 text-sm text-red-300">
                            {{ form.errors.speech_duration_seconds }}
                        </p>
                    </article>

                    <article class="rounded border border-slate-800 bg-slate-900 p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold tracking-normal text-white">タイマー表示方式</h2>
                                <p class="mt-2 text-sm leading-6 text-slate-300">録音中のタイマーの見せ方を選びます。</p>
                            </div>
                            <span class="text-sm font-medium text-emerald-300">
                                {{ timerDisplayOptions.find((option) => option.value === form.timer_display_mode)?.label }}
                            </span>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <label
                                v-for="option in timerDisplayOptions"
                                :key="option.value"
                                class="block rounded border p-4 transition"
                                :class="form.timer_display_mode === option.value ? 'border-emerald-500 bg-emerald-950' : 'border-slate-800 bg-slate-950 hover:border-slate-600'"
                            >
                                <span class="flex items-start gap-3">
                                    <input
                                        v-model="form.timer_display_mode"
                                        type="radio"
                                        name="timer_display_mode"
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
                        <p v-if="form.errors.timer_display_mode" class="mt-3 text-sm text-red-300">
                            {{ form.errors.timer_display_mode }}
                        </p>
                    </article>

                    <article class="rounded border border-slate-800 bg-slate-900 p-5">
                        <h2 class="text-lg font-semibold tracking-normal text-white">録音時の補助表示</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-300">強制終了と文字起こし表示のON/OFFを切り替えます。</p>

                        <div class="mt-5 grid gap-4 md:grid-cols-2">
                            <label class="flex min-h-32 items-center justify-between gap-4 rounded border border-slate-800 bg-slate-950 p-4">
                                <span>
                                    <span class="block text-sm font-semibold text-white">強制終了</span>
                                    <span class="mt-1 block text-sm leading-6 text-slate-300">時間に達したときに録音を終了する想定です。</span>
                                </span>
                                <input
                                    v-model="form.force_stop_enabled"
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
                                    v-model="form.transcript_display_enabled"
                                    type="checkbox"
                                    class="h-6 w-6 shrink-0 accent-emerald-400"
                                >
                            </label>
                        </div>
                        <p v-if="form.errors.force_stop_enabled" class="mt-3 text-sm text-red-300">
                            {{ form.errors.force_stop_enabled }}
                        </p>
                        <p v-if="form.errors.transcript_display_enabled" class="mt-3 text-sm text-red-300">
                            {{ form.errors.transcript_display_enabled }}
                        </p>
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
                            <p class="font-semibold">{{ isDirty ? '未保存の変更があります' : '保存済みの設定と一致しています' }}</p>
                            <p class="mt-1 leading-6">
                                {{ isDirty ? '保存ボタンで変更を反映できます。' : '次回表示時もこの内容が読み込まれます。' }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="mt-5 w-full rounded bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? '保存中...' : '設定を保存' }}
                        </button>

                        <div
                            v-if="successMessage"
                            class="mt-4 rounded border border-emerald-800 bg-emerald-950 px-4 py-3 text-sm text-emerald-100"
                            role="status"
                        >
                            {{ successMessage }}
                        </div>

                        <div
                            v-if="hasErrors"
                            class="mt-4 rounded border border-red-900 bg-red-950 px-4 py-3 text-sm text-red-100"
                            role="alert"
                        >
                            入力内容を確認してください。保存はまだ完了していません。
                        </div>
                    </div>
                </aside>
            </form>
        </section>
    </main>
</template>
