<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import {
    buildTrialId,
    buildTrialQuery,
    calculateRecordingUiTime,
    formatRecordingTime,
    lockTrialIdentity,
    normalizeTrialIdentity,
} from '../../verification/t00006RecordingUi.js';

const props = defineProps({
    profiles: {
        type: Array,
        required: true,
    },
    initialTrial: {
        type: Object,
        required: true,
    },
    initialTrialId: {
        type: String,
        required: true,
    },
});

const normalizedInitialTrial = normalizeTrialIdentity(props.initialTrial);

if (buildTrialId(normalizedInitialTrial) !== props.initialTrialId) {
    throw new Error('The server trial identity is inconsistent.');
}

const form = reactive({
    environmentId: normalizedInitialTrial.environment_id,
    profileSeconds: normalizedInitialTrial.profile_seconds,
    runNumber: normalizedInitialTrial.run_number,
    attemptNumber: normalizedInitialTrial.attempt_number,
});
const status = ref('idle');
const mimeType = ref('');
const errorMessage = ref('');
const serverResult = ref(null);
const lockedTrial = ref(null);
const preflightFailure = ref(null);
const recordingOpportunityConsumed = ref(false);
const elapsedDisplaySeconds = ref(0);
const remainingDisplaySeconds = ref(normalizedInitialTrial.profile_seconds);

let recorder = null;
let stream = null;
let chunks = [];
let stopTimerId = null;
let recordingUiTimerId = null;
let recordingOrigin = null;
let activeMetadata = null;
let trialSubmitted = false;
let visibilityHandler = null;

const statusLabels = {
    idle: 'Ready — trial not locked',
    checking_trial: 'Checking trial ID',
    locked: 'Ready — trial locked',
    start_preflight: 'Rechecking trial ID',
    preflight_error: 'Trial condition check failed',
    requesting_microphone: 'Requesting microphone permission',
    recording: 'Recording',
    finishing: 'Recording finished / Saving and analyzing',
    uploading: 'Recording finished / Saving and analyzing',
    saved_valid: 'Measurement completed — valid',
    saved_invalid: 'Measurement completed — invalid',
    error: 'Measurement failed',
};

const statusLabel = computed(() => statusLabels[status.value] ?? status.value);
const isBusy = computed(() => [
    'checking_trial',
    'start_preflight',
    'requesting_microphone',
    'recording',
    'finishing',
    'uploading',
].includes(status.value));
const normalizedForm = computed(() => {
    try {
        return normalizeTrialIdentity(form);
    } catch {
        return null;
    }
});
const previewTrialId = computed(() => (
    normalizedForm.value === null ? null : buildTrialId(normalizedForm.value)
));
const displayedTrialId = computed(() => lockedTrial.value?.trial_id ?? previewTrialId.value);
const activeProfileSeconds = () => (
    activeMetadata?.profile_seconds
    ?? lockedTrial.value?.profile_seconds
    ?? normalizedForm.value?.profile_seconds
    ?? 0
);
const plannedRecordingTime = computed(() => formatRecordingTime(activeProfileSeconds()));
const elapsedRecordingTime = computed(() => formatRecordingTime(elapsedDisplaySeconds.value));
const remainingRecordingTime = computed(() => formatRecordingTime(remainingDisplaySeconds.value));
const measuredDuration = computed(() => {
    const duration = Number(serverResult.value?.webm_duration_seconds);

    return Number.isFinite(duration) ? `${duration.toFixed(2)}秒` : '取得できませんでした';
});
const invalidReason = computed(() => serverResult.value?.invalid_reason ?? 'unknown');
const inputIsValid = computed(() => normalizedForm.value !== null);
const inputsDisabled = computed(() => (
    isBusy.value || lockedTrial.value !== null || recordingOpportunityConsumed.value
));
const canStart = computed(() => (
    lockedTrial.value !== null
    && status.value === 'locked'
    && ! isBusy.value
    && ! recordingOpportunityConsumed.value
));
const serverTrialMatchesLocked = computed(() => {
    if (! lockedTrial.value || ! serverResult.value?.trial_id) {
        return null;
    }

    return lockedTrial.value.trial_id === serverResult.value.trial_id;
});

const relativeNow = () => (
    recordingOrigin === null ? null : performance.now() - recordingOrigin
);

const updateRecordingUiTime = (elapsedMs = relativeNow()) => {
    const display = calculateRecordingUiTime(elapsedMs ?? 0, activeProfileSeconds());

    elapsedDisplaySeconds.value = display.elapsedDisplaySeconds;
    remainingDisplaySeconds.value = display.remainingDisplaySeconds;
};

const clearRecordingUiTimer = () => {
    if (recordingUiTimerId !== null) {
        window.clearInterval(recordingUiTimerId);
        recordingUiTimerId = null;
    }
};

const startRecordingUiTimer = () => {
    clearRecordingUiTimer();
    updateRecordingUiTime();
    recordingUiTimerId = window.setInterval(updateRecordingUiTime, 200);
};

const markRecordingFinished = () => {
    clearRecordingUiTimer();
    updateRecordingUiTime(activeProfileSeconds() * 1000);
    status.value = 'finishing';
};

const resolveMimeType = (MediaRecorderApi) => {
    const preferredMimeTypes = [
        'audio/webm;codecs=opus',
        'audio/webm',
    ];

    if (! MediaRecorderApi?.isTypeSupported) {
        return '';
    }

    return preferredMimeTypes.find((type) => MediaRecorderApi.isTypeSupported(type)) ?? '';
};

const stopTracks = () => {
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
};

const clearStopTimer = () => {
    if (stopTimerId !== null) {
        window.clearTimeout(stopTimerId);
        stopTimerId = null;
    }
};

const removeVisibilityListener = () => {
    if (visibilityHandler !== null) {
        document.removeEventListener('visibilitychange', visibilityHandler);
        visibilityHandler = null;
    }
};

const cleanupCapture = () => {
    clearStopTimer();
    clearRecordingUiTimer();
    removeVisibilityListener();
    stopTracks();
};

const freshMetadata = (trial) => ({
    environment_id: trial.environment_id,
    profile_seconds: trial.profile_seconds,
    run_number: trial.run_number,
    attempt_number: trial.attempt_number,
    browser_user_agent: navigator.userAgent ?? null,
    browser_platform: navigator.userAgentData?.platform ?? navigator.platform ?? null,
    browser_language: navigator.language ?? null,
    requested_mime_type: null,
    actual_mime_type: null,
    recording_started_ms: null,
    stop_requested_ms: null,
    recorder_stop_called_ms: null,
    last_dataavailable_ms: null,
    stop_event_ms: null,
    blob_completed_ms: null,
    blob_size_bytes: null,
    started_visibility_state: document.visibilityState,
    ended_visibility_state: null,
    visibility_change_count: 0,
    recorder_error: null,
    client_invalid_reason: null,
});

const preflightPayload = (trial) => ({
    environment_id: trial.environment_id,
    profile_seconds: trial.profile_seconds,
    run_number: trial.run_number,
    attempt_number: trial.attempt_number,
});

const requestPreflight = async (trial) => {
    const response = await axios.post(
        '/verification/t000-06/media-recorder/trials/preflight',
        preflightPayload(trial),
        { headers: { Accept: 'application/json' } },
    );

    if (response.data?.available !== true || response.data?.trial_id !== trial.trial_id) {
        throw new Error('The preflight response did not match the locked trial ID.');
    }

    return response.data;
};

const replaceTrialQuery = (trial) => {
    const query = buildTrialQuery(trial);
    const nextUrl = `${window.location.pathname}?${query}${window.location.hash}`;

    window.history.replaceState(window.history.state, '', nextUrl);
};

const handlePreflightFailure = (error, trial) => {
    const response = error.response?.data ?? null;

    preflightFailure.value = {
        trial_id: response?.trial_id ?? trial?.trial_id ?? previewTrialId.value,
        invalid_reason: response?.invalid_reason ?? 'storage_failed',
    };
    lockedTrial.value = null;
    serverResult.value = response;
    errorMessage.value = response?.message ?? 'The trial ID could not be checked.';
    status.value = 'preflight_error';
};

const lockTrial = async () => {
    if (isBusy.value || lockedTrial.value !== null || recordingOpportunityConsumed.value) {
        return;
    }

    if (! inputIsValid.value) {
        errorMessage.value = 'Check the trial identifier fields.';

        return;
    }

    const candidate = lockTrialIdentity(form);

    status.value = 'checking_trial';
    errorMessage.value = '';
    serverResult.value = null;
    preflightFailure.value = null;

    try {
        await requestPreflight(candidate);
        lockedTrial.value = candidate;
        replaceTrialQuery(candidate);
        elapsedDisplaySeconds.value = 0;
        remainingDisplaySeconds.value = candidate.profile_seconds;
        status.value = 'locked';
    } catch (error) {
        handlePreflightFailure(error, candidate);
    }
};

const editTrialConditions = () => {
    if (isBusy.value || recordingOpportunityConsumed.value) {
        return;
    }

    lockedTrial.value = null;
    preflightFailure.value = null;
    serverResult.value = null;
    errorMessage.value = '';
    status.value = 'idle';
};

const submitTrial = async (blob = null) => {
    if (trialSubmitted || activeMetadata === null) {
        return;
    }

    trialSubmitted = true;
    cleanupCapture();
    status.value = 'uploading';
    activeMetadata.ended_visibility_state ??= document.visibilityState;
    activeMetadata.blob_size_bytes = blob?.size ?? null;

    const payload = new FormData();
    payload.append('metadata', JSON.stringify(activeMetadata));

    if (blob !== null && blob.size > 0) {
        payload.append('audio_file', blob, 'measurement.webm');
    }

    try {
        const response = await axios.post('/verification/t000-06/media-recorder/trials', payload, {
            headers: {
                Accept: 'application/json',
            },
        });

        serverResult.value = response.data;
        status.value = response.data.valid ? 'saved_valid' : 'saved_invalid';
    } catch (error) {
        status.value = 'error';
        serverResult.value = error.response?.data ?? null;
        errorMessage.value = error.response?.data?.message ?? 'The trial could not be saved.';
    } finally {
        recorder = null;
        chunks = [];
        recordingOrigin = null;
        activeMetadata = null;
    }
};

const submitCaptureFailure = async (invalidReason, errorName) => {
    activeMetadata.client_invalid_reason = invalidReason;
    activeMetadata.recorder_error = errorName;
    activeMetadata.ended_visibility_state = document.visibilityState;
    await submitTrial(null);
};

const requestAutomaticStop = () => {
    if (recorder === null || activeMetadata === null || recorder.state !== 'recording') {
        return;
    }

    activeMetadata.stop_requested_ms = relativeNow();
    activeMetadata.recorder_stop_called_ms = relativeNow();

    try {
        recorder.stop();
        markRecordingFinished();
    } catch (error) {
        activeMetadata.client_invalid_reason = 'media_recorder_error';
        activeMetadata.recorder_error = error?.name ?? 'MediaRecorderStopError';
        activeMetadata.ended_visibility_state = document.visibilityState;
        void submitTrial(null);
    }
};

const startTrial = async () => {
    if (! canStart.value) {
        errorMessage.value = lockedTrial.value
            ? ''
            : 'Confirm and lock the trial conditions before starting.';

        return;
    }

    const trial = lockedTrial.value;

    status.value = 'start_preflight';
    errorMessage.value = '';
    serverResult.value = null;
    preflightFailure.value = null;

    try {
        await requestPreflight(trial);
    } catch (error) {
        handlePreflightFailure(error, trial);

        return;
    }

    // The one recording opportunity is consumed only after Start preflight succeeds.
    // From this point, permission and recorder failures must not re-enable Start.
    recordingOpportunityConsumed.value = true;
    cleanupCapture();
    status.value = 'requesting_microphone';
    mimeType.value = '';
    chunks = [];
    recordingOrigin = null;
    updateRecordingUiTime(0);
    trialSubmitted = false;
    activeMetadata = freshMetadata(trial);

    const MediaRecorderApi = window.MediaRecorder;

    if (! MediaRecorderApi || ! navigator.mediaDevices?.getUserMedia) {
        await submitCaptureFailure('media_recorder_unsupported', 'MediaRecorderUnsupported');

        return;
    }

    try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const selectedMimeType = resolveMimeType(MediaRecorderApi);
        recorder = selectedMimeType
            ? new MediaRecorderApi(stream, { mimeType: selectedMimeType })
            : new MediaRecorderApi(stream);

        activeMetadata.requested_mime_type = selectedMimeType || null;
        activeMetadata.actual_mime_type = recorder.mimeType || null;
        mimeType.value = recorder.mimeType || selectedMimeType || 'browser default';

        visibilityHandler = () => {
            if (recordingOrigin !== null && ! trialSubmitted) {
                activeMetadata.visibility_change_count += 1;
            }
        };
        document.addEventListener('visibilitychange', visibilityHandler);

        recorder.addEventListener('start', () => {
            recordingOrigin = performance.now();
            activeMetadata.recording_started_ms = 0;
            activeMetadata.started_visibility_state = document.visibilityState;
            activeMetadata.visibility_change_count = 0;
            status.value = 'recording';

            stopTimerId = window.setTimeout(
                requestAutomaticStop,
                activeMetadata.profile_seconds * 1000,
            );
            startRecordingUiTimer();
        }, { once: true });

        recorder.addEventListener('dataavailable', (event) => {
            if (trialSubmitted || activeMetadata === null) {
                return;
            }

            activeMetadata.last_dataavailable_ms = relativeNow();

            if (event.data?.size > 0) {
                chunks.push(event.data);
            }
        });

        recorder.addEventListener('error', (event) => {
            if (trialSubmitted || activeMetadata === null) {
                return;
            }

            clearStopTimer();
            clearRecordingUiTimer();
            status.value = 'finishing';
            activeMetadata.client_invalid_reason = 'media_recorder_error';
            activeMetadata.recorder_error = event.error?.name ?? 'MediaRecorderError';

            if (recorder?.state === 'recording') {
                activeMetadata.stop_requested_ms = relativeNow();
                activeMetadata.recorder_stop_called_ms = relativeNow();

                try {
                    recorder.stop();
                } catch (error) {
                    activeMetadata.recorder_error = error?.name ?? activeMetadata.recorder_error;
                    activeMetadata.ended_visibility_state = document.visibilityState;
                    void submitTrial(null);
                }
            } else {
                activeMetadata.ended_visibility_state = document.visibilityState;
                void submitTrial(null);
            }
        });

        recorder.addEventListener('stop', async () => {
            if (trialSubmitted || activeMetadata === null) {
                return;
            }

            clearStopTimer();
            clearRecordingUiTimer();

            if (status.value === 'recording') {
                status.value = 'finishing';
            }

            activeMetadata.stop_event_ms = relativeNow();
            activeMetadata.actual_mime_type = recorder.mimeType || activeMetadata.actual_mime_type;

            const blob = new Blob(chunks, {
                type: activeMetadata.actual_mime_type
                    || activeMetadata.requested_mime_type
                    || 'audio/webm',
            });
            activeMetadata.blob_completed_ms = relativeNow();
            activeMetadata.ended_visibility_state = document.visibilityState;
            await submitTrial(blob);
        }, { once: true });

        // Keep production parity: listeners are registered first and no timeslice is supplied.
        recorder.start();
    } catch (error) {
        const permissionDenied = error?.name === 'NotAllowedError' || error?.name === 'SecurityError';
        await submitCaptureFailure(
            permissionDenied ? 'microphone_permission_denied' : 'media_recorder_error',
            error?.name ?? 'MediaRecorderStartError',
        );
    }
};

onBeforeUnmount(() => {
    trialSubmitted = true;

    if (recorder?.state === 'recording') {
        try {
            recorder.stop();
        } catch {
            // Page teardown still releases the microphone tracks below.
        }
    }

    cleanupCapture();
});
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-6 py-10 text-slate-100">
        <section class="mx-auto w-full max-w-5xl">
            <p class="text-sm font-medium text-emerald-300">Local/testing verification</p>
            <h1 class="mt-2 text-3xl font-semibold">T000-06 MediaRecorder measurement</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">
                Run one trial at a time. Recording stops automatically when the selected profile duration is reached.
            </p>

            <section class="mt-6 rounded border border-amber-700 bg-amber-950/50 p-5">
                <h2 class="text-lg font-semibold text-amber-100">録音前の確認</h2>
                <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-amber-50">
                    <li>Startを押す前に、指定時間分の発話内容を準備してください。</li>
                    <li>マイク許可済みの場合、Startを押すと直ちに録音が始まります。</li>
                    <li>録音中は画面やタブを切り替えないでください。</li>
                    <li>「録音終了」が表示されるまで発話してください。</li>
                    <li>Startは1回だけ押してください。</li>
                    <li>無効結果でも独断で再録音しないでください。</li>
                </ol>
            </section>

            <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.8fr)]">
                <section class="rounded border border-slate-800 bg-slate-900 p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm">
                            <span class="block font-medium text-slate-200">environment_id</span>
                            <input
                                v-model.trim="form.environmentId"
                                :disabled="inputsDisabled"
                                maxlength="32"
                                pattern="[a-z0-9-]+"
                                class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 disabled:text-slate-500"
                            >
                        </label>

                        <label class="text-sm">
                            <span class="block font-medium text-slate-200">profile_seconds</span>
                            <select
                                v-model.number="form.profileSeconds"
                                :disabled="inputsDisabled"
                                class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 disabled:text-slate-500"
                            >
                                <option v-for="profile in profiles" :key="profile" :value="profile">
                                    {{ profile }} seconds
                                </option>
                            </select>
                        </label>

                        <label class="text-sm">
                            <span class="block font-medium text-slate-200">run_number</span>
                            <input
                                v-model.number="form.runNumber"
                                :disabled="inputsDisabled"
                                type="number"
                                min="1"
                                max="5"
                                class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 disabled:text-slate-500"
                            >
                        </label>

                        <label class="text-sm">
                            <span class="block font-medium text-slate-200">attempt_number</span>
                            <input
                                v-model.number="form.attemptNumber"
                                :disabled="inputsDisabled"
                                type="number"
                                min="1"
                                max="99"
                                class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2 disabled:text-slate-500"
                            >
                        </label>
                    </div>

                    <div class="mt-6 rounded border border-cyan-800 bg-cyan-950/40 p-4">
                        <p class="text-sm font-semibold text-cyan-100">
                            {{ lockedTrial ? '今回固定したtrial ID' : '入力中のtrial ID' }}
                        </p>
                        <p class="mt-2 break-all font-mono text-lg font-semibold text-white">
                            {{ displayedTrialId || '入力値を確認してください' }}
                        </p>

                        <dl v-if="lockedTrial" class="mt-4 grid gap-2 text-xs text-cyan-50 sm:grid-cols-2">
                            <div>environment_id: <span class="font-mono">{{ lockedTrial.environment_id }}</span></div>
                            <div>profile_seconds: <span class="font-mono">{{ lockedTrial.profile_seconds }}</span></div>
                            <div>run_number: <span class="font-mono">{{ lockedTrial.run_number }}</span></div>
                            <div>attempt_number: <span class="font-mono">{{ lockedTrial.attempt_number }}</span></div>
                        </dl>
                    </div>

                    <div
                        v-if="! recordingOpportunityConsumed"
                        class="mt-6 rounded border border-emerald-800 bg-emerald-950/50 p-4 text-sm leading-6 text-emerald-50"
                    >
                        <p class="font-semibold">予定録音時間: {{ plannedRecordingTime }}</p>
                        <p class="mt-2">Startを押す前に、発話の準備を完了してください。</p>
                        <p>マイク許可済みの場合、Startを押すと直ちに録音が始まります。</p>
                        <p v-if="! lockedTrial" class="mt-2 font-semibold">
                            Start前にtrial条件を確認・固定してください。
                        </p>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button
                            v-if="! lockedTrial && ! recordingOpportunityConsumed"
                            type="button"
                            :disabled="isBusy || ! inputIsValid"
                            class="rounded bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
                            @click="lockTrial"
                        >
                            trial条件を確認・固定
                        </button>

                        <button
                            v-if="lockedTrial && ! recordingOpportunityConsumed"
                            type="button"
                            :disabled="status !== 'locked'"
                            class="rounded border border-slate-600 px-4 py-3 text-sm font-semibold text-slate-100 disabled:cursor-not-allowed disabled:text-slate-500"
                            @click="editTrialConditions"
                        >
                            条件を修正する
                        </button>

                        <button
                            v-if="lockedTrial"
                            type="button"
                            :disabled="! canStart"
                            class="rounded bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
                            @click="startTrial"
                        >
                            Start {{ lockedTrial.trial_id }}
                        </button>
                    </div>

                    <p
                        v-if="recordingOpportunityConsumed"
                        class="mt-5 rounded border border-slate-700 bg-slate-950 p-4 text-sm leading-6 text-slate-200"
                    >
                        この画面では録音を再実行できません。次の操作は確認・承認後に新しい条件で画面を開いてください。
                    </p>

                    <p class="mt-4 text-xs leading-5 text-slate-400">
                        This helper does not submit to Azure, create submissions, or write to the database.
                    </p>
                </section>

                <section
                    class="rounded border border-slate-800 bg-slate-900 p-5"
                    aria-live="polite"
                >
                    <dl class="space-y-4 text-sm">
                        <div>
                            <dt class="text-slate-400">Current status</dt>
                            <dd class="mt-1 font-semibold text-white">{{ statusLabel }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">MIME type</dt>
                            <dd class="mt-1 break-all font-mono text-xs text-white">{{ mimeType || '-' }}</dd>
                        </div>
                    </dl>

                    <div
                        v-if="status === 'checking_trial'"
                        class="mt-5 rounded border border-cyan-700 bg-cyan-950 p-4 text-sm text-cyan-50"
                        role="status"
                    >
                        <p class="font-semibold">trial IDを確認しています</p>
                    </div>

                    <div
                        v-if="status === 'start_preflight'"
                        class="mt-5 rounded border border-cyan-700 bg-cyan-950 p-4 text-sm text-cyan-50"
                        role="status"
                    >
                        <p class="font-semibold">trial IDを再確認しています</p>
                        <p class="mt-2">マイク録音はまだ開始していません。</p>
                    </div>

                    <div
                        v-if="status === 'preflight_error'"
                        class="mt-5 rounded border border-red-900 bg-red-950 p-4 text-sm text-red-100"
                        role="alert"
                    >
                        <p class="font-semibold">録音は開始されませんでした</p>
                        <template v-if="preflightFailure?.invalid_reason === 'duplicate_trial_id'">
                            <p class="mt-2">このtrial IDは既に存在します。</p>
                            <p class="mt-1 break-all font-mono">{{ preflightFailure.trial_id }}</p>
                        </template>
                        <p v-else class="mt-2">trial IDの安全確認に失敗しました。</p>
                        <p class="mt-3">条件を確認し、再度trial条件の固定から行ってください。</p>
                    </div>

                    <div
                        v-if="status === 'requesting_microphone'"
                        class="mt-5 rounded border border-amber-700 bg-amber-950 p-4 text-sm text-amber-50"
                        role="status"
                    >
                        <p class="font-semibold">マイク権限を確認しています</p>
                        <p class="mt-2">許可すると直ちに録音が始まります。</p>
                    </div>

                    <div
                        v-if="status === 'recording'"
                        class="mt-5 rounded border border-red-700 bg-red-950 p-4 text-red-50"
                        role="status"
                    >
                        <p class="text-lg font-semibold">
                            <span class="animate-pulse text-red-400" aria-hidden="true">●</span>
                            録音中
                        </p>
                        <p class="mt-3 font-mono text-sm">
                            経過時間: {{ elapsedRecordingTime }} / {{ plannedRecordingTime }}
                        </p>
                        <p class="mt-1 font-mono text-sm">残り時間: {{ remainingRecordingTime }}</p>
                    </div>

                    <div
                        v-if="status === 'finishing' || status === 'uploading'"
                        class="mt-5 rounded border border-sky-700 bg-sky-950 p-4 text-sm text-sky-50"
                        role="status"
                    >
                        <p class="text-lg font-semibold">録音終了</p>
                        <p class="mt-2">音声を保存・解析しています。</p>
                    </div>

                    <div
                        v-if="status === 'saved_valid'"
                        class="mt-5 rounded border border-emerald-700 bg-emerald-950 p-4 text-sm text-emerald-50"
                        role="status"
                    >
                        <p class="text-lg font-semibold">計測完了</p>
                        <p class="mt-2">結果: 有効</p>
                        <p class="mt-1">実音声時間: {{ measuredDuration }}</p>
                    </div>

                    <div
                        v-if="status === 'saved_invalid'"
                        class="mt-5 rounded border border-amber-700 bg-amber-950 p-4 text-sm text-amber-50"
                        role="status"
                    >
                        <p class="text-lg font-semibold">計測完了</p>
                        <p class="mt-2">結果: 無効</p>
                        <p class="mt-1 break-all">理由: {{ invalidReason }}</p>
                    </div>

                    <div
                        v-if="status === 'error'"
                        class="mt-5 rounded border border-red-900 bg-red-950 p-4 text-sm text-red-100"
                        role="status"
                    >
                        <p class="text-lg font-semibold">計測失敗</p>
                        <p class="mt-2">録音結果を保存できませんでした。</p>
                    </div>

                    <div
                        v-if="errorMessage"
                        class="mt-5 rounded border border-red-900 bg-red-950 p-3 text-sm text-red-100"
                        role="alert"
                    >
                        {{ errorMessage }}
                    </div>
                </section>
            </div>

            <section v-if="serverResult" class="mt-6 rounded border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-lg font-semibold">Server save / analysis result</h2>
                <p v-if="serverTrialMatchesLocked !== null" class="mt-3 text-sm">
                    Fixed trial ID comparison:
                    <span :class="serverTrialMatchesLocked ? 'text-emerald-300' : 'text-red-300'">
                        {{ serverTrialMatchesLocked ? 'match' : 'mismatch' }}
                    </span>
                </p>
                <pre class="mt-4 overflow-x-auto rounded bg-slate-950 p-4 text-xs leading-6 text-slate-200">{{ JSON.stringify(serverResult, null, 2) }}</pre>
            </section>
        </section>
    </main>
</template>
