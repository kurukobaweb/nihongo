<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

const props = defineProps({
    profiles: {
        type: Array,
        required: true,
    },
});

const form = reactive({
    environmentId: 'env-a',
    profileSeconds: 10,
    runNumber: 1,
    attemptNumber: 1,
});
const status = ref('idle');
const mimeType = ref('');
const errorMessage = ref('');
const serverResult = ref(null);

let recorder = null;
let stream = null;
let chunks = [];
let stopTimerId = null;
let recordingOrigin = null;
let activeMetadata = null;
let trialSubmitted = false;
let visibilityHandler = null;

const statusLabels = {
    idle: 'Ready',
    requesting_microphone: 'Requesting microphone permission',
    recording: 'Recording',
    uploading: 'Saving and analyzing',
    saved_valid: 'Saved as valid trial',
    saved_invalid: 'Saved as invalid trial',
    error: 'Error',
};

const statusLabel = computed(() => statusLabels[status.value] ?? status.value);
const isBusy = computed(() => ['requesting_microphone', 'recording', 'uploading'].includes(status.value));
const inputIsValid = computed(() => (
    /^[a-z0-9-]+$/.test(form.environmentId)
    && form.environmentId.length <= 32
    && props.profiles.includes(Number(form.profileSeconds))
    && Number(form.runNumber) >= 1
    && Number(form.runNumber) <= 5
    && Number(form.attemptNumber) >= 1
    && Number(form.attemptNumber) <= 99
));

const relativeNow = () => (
    recordingOrigin === null ? null : performance.now() - recordingOrigin
);

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
    removeVisibilityListener();
    stopTracks();
};

const freshMetadata = () => ({
    environment_id: form.environmentId,
    profile_seconds: Number(form.profileSeconds),
    run_number: Number(form.runNumber),
    attempt_number: Number(form.attemptNumber),
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
    } catch (error) {
        activeMetadata.client_invalid_reason = 'media_recorder_error';
        activeMetadata.recorder_error = error?.name ?? 'MediaRecorderStopError';
        activeMetadata.ended_visibility_state = document.visibilityState;
        void submitTrial(null);
    }
};

const startTrial = async () => {
    if (isBusy.value || ! inputIsValid.value) {
        errorMessage.value = inputIsValid.value ? '' : 'Check the trial identifier fields.';

        return;
    }

    cleanupCapture();
    status.value = 'requesting_microphone';
    errorMessage.value = '';
    serverResult.value = null;
    mimeType.value = '';
    chunks = [];
    recordingOrigin = null;
    trialSubmitted = false;
    activeMetadata = freshMetadata();

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

            <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.8fr)]">
                <section class="rounded border border-slate-800 bg-slate-900 p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="text-sm">
                            <span class="block font-medium text-slate-200">environment_id</span>
                            <input
                                v-model.trim="form.environmentId"
                                :disabled="isBusy"
                                maxlength="32"
                                pattern="[a-z0-9-]+"
                                class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2"
                            >
                        </label>

                        <label class="text-sm">
                            <span class="block font-medium text-slate-200">profile_seconds</span>
                            <select
                                v-model.number="form.profileSeconds"
                                :disabled="isBusy"
                                class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2"
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
                                :disabled="isBusy"
                                type="number"
                                min="1"
                                max="5"
                                class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2"
                            >
                        </label>

                        <label class="text-sm">
                            <span class="block font-medium text-slate-200">attempt_number</span>
                            <input
                                v-model.number="form.attemptNumber"
                                :disabled="isBusy"
                                type="number"
                                min="1"
                                max="99"
                                class="mt-2 w-full rounded border border-slate-700 bg-slate-950 px-3 py-2"
                            >
                        </label>
                    </div>

                    <button
                        type="button"
                        :disabled="isBusy || ! inputIsValid"
                        class="mt-6 rounded bg-emerald-500 px-5 py-3 text-sm font-semibold text-slate-950 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
                        @click="startTrial"
                    >
                        Start one measurement trial
                    </button>

                    <p class="mt-4 text-xs leading-5 text-slate-400">
                        This helper does not submit to Azure, create submissions, or write to the database.
                    </p>
                </section>

                <section class="rounded border border-slate-800 bg-slate-900 p-5">
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
                <pre class="mt-4 overflow-x-auto rounded bg-slate-950 p-4 text-xs leading-6 text-slate-200">{{ JSON.stringify(serverResult, null, 2) }}</pre>
            </section>
        </section>
    </main>
</template>
