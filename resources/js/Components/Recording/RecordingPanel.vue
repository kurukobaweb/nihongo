<script setup>
import { computed, ref } from 'vue';

const recordingState = ref('idle');

const stateLabels = {
    idle: '待機中',
    recording: '録音中',
    confirm: '提出確認',
    error: 'エラー確認',
};

const stateHelp = computed(() => ({
    idle: 'STARTを押すと録音中の表示に切り替わります。',
    recording: 'STOPを押すと提出確認の表示に進みます。',
    confirm: '提出前に内容を確認する想定の領域です。',
    error: '録音または認識に失敗した場合の案内枠です。',
}[recordingState.value]));

const startRecording = () => {
    recordingState.value = 'recording';
};

const stopRecording = () => {
    recordingState.value = 'confirm';
};

const resetRecording = () => {
    recordingState.value = 'idle';
};

const showError = () => {
    recordingState.value = 'error';
};
</script>

<template>
    <section class="rounded border border-slate-800 bg-slate-900 p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-300">録音操作</p>
                <h2 class="mt-2 text-xl font-semibold tracking-normal text-white">スピーチを録音する</h2>
                <p class="mt-2 text-sm leading-6 text-slate-300">
                    ここでは録音フローの表示枠だけを確認できます。
                </p>
            </div>

            <div class="rounded border border-slate-700 bg-slate-950 px-4 py-3 text-sm">
                <p class="text-slate-400">現在状態</p>
                <p class="mt-1 font-semibold text-white">{{ stateLabels[recordingState] }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_12rem]">
            <div class="rounded border border-slate-800 bg-slate-950 p-4">
                <p class="text-sm font-medium text-slate-200">録音タイマー</p>
                <p class="mt-3 font-mono text-4xl font-semibold tracking-normal text-white">00:00</p>
                <p class="mt-2 text-sm leading-6 text-slate-400">{{ stateHelp }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-1">
                <button
                    type="button"
                    class="rounded bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
                    :disabled="recordingState === 'recording'"
                    @click="startRecording"
                >
                    START
                </button>
                <button
                    type="button"
                    class="rounded border border-slate-600 px-4 py-3 text-sm font-semibold text-slate-100 transition hover:border-slate-400 disabled:cursor-not-allowed disabled:border-slate-800 disabled:text-slate-500"
                    :disabled="recordingState !== 'recording'"
                    @click="stopRecording"
                >
                    STOP
                </button>
                <button
                    type="button"
                    class="rounded border border-slate-700 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-slate-500"
                    @click="resetRecording"
                >
                    再録音
                </button>
                <button
                    type="button"
                    class="rounded border border-amber-700 px-4 py-3 text-sm font-medium text-amber-100 transition hover:border-amber-500"
                    @click="showError"
                >
                    エラー表示
                </button>
            </div>
        </div>

        <div
            v-if="recordingState === 'confirm'"
            class="mt-5 rounded border border-emerald-800 bg-emerald-950 px-4 py-4"
        >
            <p class="text-sm font-semibold text-emerald-100">提出確認</p>
            <p class="mt-2 text-sm leading-6 text-emerald-200">
                録音内容を提出する前の確認枠です。実際の送信処理は後続タスクで実装します。
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <button
                    type="button"
                    class="rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
                >
                    提出する
                </button>
                <button
                    type="button"
                    class="rounded border border-emerald-700 px-4 py-2 text-sm font-medium text-emerald-100 hover:border-emerald-500"
                    @click="resetRecording"
                >
                    キャンセル
                </button>
                <button
                    type="button"
                    class="rounded border border-emerald-700 px-4 py-2 text-sm font-medium text-emerald-100 hover:border-emerald-500"
                    @click="resetRecording"
                >
                    再録音する
                </button>
            </div>
        </div>

        <div
            v-if="recordingState === 'error'"
            class="mt-5 rounded border border-amber-800 bg-amber-950 px-4 py-4"
        >
            <p class="text-sm font-semibold text-amber-100">録音を確認できませんでした</p>
            <p class="mt-2 text-sm leading-6 text-amber-200">
                状況を確認して、もう一度録音または提出をお試しください。
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <button
                    type="button"
                    class="rounded bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-300"
                    @click="resetRecording"
                >
                    再録音する
                </button>
                <button
                    type="button"
                    class="rounded border border-amber-700 px-4 py-2 text-sm font-medium text-amber-100 hover:border-amber-500"
                    @click="recordingState = 'confirm'"
                >
                    提出確認へ戻る
                </button>
            </div>
        </div>
    </section>
</template>
