<script setup>
import { computed } from 'vue';
import { useRecordingStore } from '@/Stores/useRecordingStore';
import { useSubmissionPollingStore } from '@/Stores/useSubmissionPollingStore';

const props = defineProps({
    questionId: {
        type: Number,
        required: true,
    },
});

const recordingStore = useRecordingStore();
const submissionPollingStore = useSubmissionPollingStore();

const recordingStatusLabels = {
    idle: '待機中',
    recording: '録音中',
    recorded: '提出待ち',
    error: '録音エラー',
};

const submissionStatusLabels = {
    submitting: '提出中',
    pending: '解析待ち',
    processing: '解析中',
    completed: '解析完了',
    failed: '解析失敗',
    timeout: '確認タイムアウト',
    error: '提出エラー',
};

const currentStatusLabel = computed(() => {
    if (submissionPollingStore.state.status !== 'idle') {
        return submissionStatusLabels[submissionPollingStore.state.status] ?? submissionPollingStore.state.status;
    }

    return recordingStatusLabels[recordingStore.state.status] ?? recordingStore.state.status;
});

const stateHelp = computed(() => {
    if (submissionPollingStore.state.status !== 'idle') {
        return {
            submitting: '音声を提出しています。',
            pending: '提出を受け付けました。解析開始を待っています。',
            processing: '解析中です。3秒ごとに状態を確認しています。',
            completed: '解析が完了しました。結果画面は後続タスクで実装します。',
            failed: submissionPollingStore.state.errorMessage || '解析に失敗しました。',
            timeout: submissionPollingStore.state.errorMessage,
            error: submissionPollingStore.state.errorMessage,
        }[submissionPollingStore.state.status] ?? '';
    }

    return {
        idle: 'STARTを押すとマイク許可を確認し、録音を開始します。',
        recording: '録音中です。STOPを押すと録音を停止します。',
        recorded: '録音データを取得しました。提出すると解析状態の確認を開始します。',
        error: recordingStore.state.errorMessage || '録音または認識に失敗しました。',
    }[recordingStore.state.status] ?? '';
});

const isSubmittingOrPolling = computed(() => (
    submissionPollingStore.state.status === 'submitting'
        || submissionPollingStore.state.status === 'pending'
        || submissionPollingStore.state.status === 'processing'
        || submissionPollingStore.isPolling
));

const canSubmit = computed(() => (
    recordingStore.state.status === 'recorded'
        && Boolean(recordingStore.state.audioBlob)
        && ! isSubmittingOrPolling.value
));

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

const submitRecording = () => {
    submissionPollingStore.submitRecording({
        audioBlob: recordingStore.state.audioBlob,
        questionId: props.questionId,
    });
};

const resetRecording = () => {
    submissionPollingStore.reset();
    recordingStore.reset();
};
</script>

<template>
    <section class="rounded border border-slate-800 bg-slate-900 p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-300">録音操作</p>
                <h2 class="mt-2 text-xl font-semibold tracking-normal text-white">スピーチを録音する</h2>
                <p class="mt-2 text-sm leading-6 text-slate-300">
                    録音を提出すると、解析が完了するまで3秒ごとに状態を確認します。
                </p>
            </div>

            <div class="rounded border border-slate-700 bg-slate-950 px-4 py-3 text-sm">
                <p class="text-slate-400">現在状態</p>
                <p class="mt-1 font-semibold text-white">{{ currentStatusLabel }}</p>
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
                    :disabled="recordingStore.state.status === 'recording' || isSubmittingOrPolling"
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
                    class="rounded border border-slate-700 px-4 py-3 text-sm font-medium text-slate-200 transition hover:border-slate-500 disabled:cursor-not-allowed disabled:text-slate-500"
                    :disabled="isSubmittingOrPolling"
                    @click="resetRecording"
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
                録音データを取得しました。提出後は解析状態の確認に移ります。
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
                    class="rounded bg-emerald-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
                    :disabled="! canSubmit"
                    @click="submitRecording"
                >
                    提出する
                </button>
                <button
                    type="button"
                    class="rounded border border-emerald-700 px-4 py-2 text-sm font-medium text-emerald-100 hover:border-emerald-500 disabled:cursor-not-allowed disabled:text-emerald-900"
                    :disabled="isSubmittingOrPolling"
                    @click="resetRecording"
                >
                    キャンセル
                </button>
            </div>
        </div>

        <div
            v-if="['submitting', 'pending', 'processing'].includes(submissionPollingStore.state.status)"
            class="mt-5 rounded border border-sky-800 bg-sky-950 px-4 py-4"
        >
            <p class="text-sm font-semibold text-sky-100">解析中</p>
            <p class="mt-2 text-sm leading-6 text-sky-200">
                3秒間隔で最大60回まで解析状態を確認します。
            </p>
            <dl class="mt-4 grid gap-3 text-sm text-sky-100 sm:grid-cols-2">
                <div class="rounded border border-sky-800 bg-sky-900 px-3 py-2">
                    <dt class="text-sky-300">submission_id</dt>
                    <dd class="mt-1 break-all font-mono text-xs">{{ submissionPollingStore.state.submissionId || '-' }}</dd>
                </div>
                <div class="rounded border border-sky-800 bg-sky-900 px-3 py-2">
                    <dt class="text-sky-300">polling</dt>
                    <dd class="mt-1 font-semibold">
                        {{ submissionPollingStore.pollingAttempts }} / {{ submissionPollingStore.maxAttempts }}
                    </dd>
                </div>
            </dl>
        </div>

        <div
            v-if="submissionPollingStore.state.status === 'completed'"
            class="mt-5 rounded border border-emerald-800 bg-emerald-950 px-4 py-4"
        >
            <p class="text-sm font-semibold text-emerald-100">解析完了</p>
            <p class="mt-2 text-sm leading-6 text-emerald-200">
                結果表示画面は後続タスクで実装します。現時点では自動遷移せず、完了状態のみ表示します。
            </p>
            <dl
                v-if="submissionPollingStore.state.evaluation"
                class="mt-4 grid gap-3 text-sm text-emerald-100 sm:grid-cols-2"
            >
                <div class="rounded border border-emerald-800 bg-emerald-900 px-3 py-2">
                    <dt class="text-emerald-300">speed</dt>
                    <dd class="mt-1 font-semibold">{{ submissionPollingStore.state.evaluation.speed_assessment || '-' }}</dd>
                </div>
                <div class="rounded border border-emerald-800 bg-emerald-900 px-3 py-2">
                    <dt class="text-emerald-300">characters/min</dt>
                    <dd class="mt-1 font-semibold">{{ submissionPollingStore.state.evaluation.characters_per_minute ?? '-' }}</dd>
                </div>
            </dl>
        </div>

        <div
            v-if="['failed', 'timeout', 'error'].includes(submissionPollingStore.state.status)"
            class="mt-5 rounded border border-amber-800 bg-amber-950 px-4 py-4"
        >
            <p class="text-sm font-semibold text-amber-100">
                {{ submissionPollingStore.state.status === 'timeout' ? '確認タイムアウト' : '解析状態を確認できませんでした' }}
            </p>
            <p class="mt-2 text-sm leading-6 text-amber-200">
                {{ submissionPollingStore.state.errorMessage }}
            </p>
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
