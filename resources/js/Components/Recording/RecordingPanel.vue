<script setup>
import { computed } from 'vue';
import { useRecordingStore } from '@/Stores/useRecordingStore';

const recordingStore = useRecordingStore();

const stateLabels = {
    idle: '待機中',
    recording: '録音中',
    recorded: '提出確認',
    error: 'エラー確認',
};

const stateHelp = computed(() => ({
    idle: 'STARTを押すとマイク許可を確認し、録音を開始します。',
    recording: '録音中です。STOPを押すと録音を停止します。',
    recorded: '録音データを取得しました。提出処理は後続タスクで実装します。',
    error: recordingStore.state.errorMessage || '録音または認識に失敗した場合の案内枠です。',
}[recordingStore.state.status]));

const formattedElapsedTime = computed(() => {
    const minutes = Math.floor(recordingStore.state.elapsedSeconds / 60);
    const seconds = recordingStore.state.elapsedSeconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const formattedBlobSize = computed(() => {
    if (! recordingStore.state.audioBlob) {
        return '';
    }

    return `${Math.ceil(recordingStore.state.audioBlob.size / 1024)} KB`;
});
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
                <p class="mt-1 font-semibold text-white">{{ stateLabels[recordingStore.state.status] }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_12rem]">
            <div class="rounded border border-slate-800 bg-slate-950 p-4">
                <p class="text-sm font-medium text-slate-200">録音タイマー</p>
                <p class="mt-3 font-mono text-4xl font-semibold tracking-normal text-white">{{ formattedElapsedTime }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-400">{{ stateHelp }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-1">
                <button
                    type="button"
                    class="rounded bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
                    :disabled="recordingStore.state.status === 'recording'"
                    @click="recordingStore.start"
                >
                    START
                </button>
                <button
                    type="button"
                    class="rounded border border-slate-600 px-4 py-3 text-sm font-semibold text-slate-100 transition hover:border-slate-400 disabled:cursor-not-allowed disabled:border-slate-800 disabled:text-slate-500"
                    :disabled="recordingStore.state.status !== 'recording'"
                    @click="recordingStore.stop"
                >
                    STOP
                </button>
                <button
                    type="button"
                    class="rounded border border-slate-700 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-slate-500"
                    @click="recordingStore.reset"
                >
                    再録音
                </button>
            </div>
        </div>

        <div
            v-if="recordingStore.state.status === 'recorded'"
            class="mt-5 rounded border border-emerald-800 bg-emerald-950 px-4 py-4"
        >
            <p class="text-sm font-semibold text-emerald-100">提出確認</p>
            <p class="mt-2 text-sm leading-6 text-emerald-200">
                録音Blobを取得しました。実際の送信処理は後続タスクで実装します。
            </p>
            <dl class="mt-4 grid gap-3 text-sm text-emerald-100 sm:grid-cols-2">
                <div class="rounded border border-emerald-800 bg-emerald-900 px-3 py-2">
                    <dt class="text-emerald-300">録音時間</dt>
                    <dd class="mt-1 font-semibold">{{ formattedElapsedTime }}</dd>
                </div>
                <div class="rounded border border-emerald-800 bg-emerald-900 px-3 py-2">
                    <dt class="text-emerald-300">Blob取得</dt>
                    <dd class="mt-1 font-semibold">取得済み {{ formattedBlobSize }}</dd>
                </div>
            </dl>
            <audio
                v-if="recordingStore.state.audioUrl"
                :src="recordingStore.state.audioUrl"
                controls
                class="mt-4 w-full"
            />
            <div class="mt-4 flex flex-wrap gap-3">
                <button
                    type="button"
                    class="rounded bg-slate-700 px-4 py-2 text-sm font-semibold text-slate-300"
                    disabled
                >
                    提出する（後続タスク）
                </button>
                <button
                    type="button"
                    class="rounded border border-emerald-700 px-4 py-2 text-sm font-medium text-emerald-100 hover:border-emerald-500"
                    @click="recordingStore.reset"
                >
                    キャンセル
                </button>
                <button
                    type="button"
                    class="rounded border border-emerald-700 px-4 py-2 text-sm font-medium text-emerald-100 hover:border-emerald-500"
                    @click="recordingStore.reset"
                >
                    再録音する
                </button>
            </div>
        </div>

        <div
            v-if="recordingStore.state.status === 'error'"
            class="mt-5 rounded border border-amber-800 bg-amber-950 px-4 py-4"
        >
            <p class="text-sm font-semibold text-amber-100">録音を確認できませんでした</p>
            <p class="mt-2 text-sm leading-6 text-amber-200">
                {{ recordingStore.state.errorMessage }}
            </p>
            <p
                v-if="recordingStore.state.permissionDenied"
                class="mt-2 text-sm leading-6 text-amber-200"
            >
                ブラウザのマイク権限を許可してから再試行してください。
            </p>
            <p
                v-if="recordingStore.state.unsupported"
                class="mt-2 text-sm leading-6 text-amber-200"
            >
                MediaRecorder API に対応したブラウザでアクセスしてください。
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <button
                    type="button"
                    class="rounded bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-amber-300"
                    @click="recordingStore.start"
                >
                    再試行する
                </button>
            </div>
        </div>
    </section>
</template>
