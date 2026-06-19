import axios from 'axios';
import { computed, reactive } from 'vue';
import { usePolling } from '@/Composables/usePolling';

const POLLING_INTERVAL_MS = 3000;
const MAX_POLLING_ATTEMPTS = 60;
const RECOGNITION_FAILURE_MARKERS = [
    'speech_unrecognized',
    'audio_conversion_failed',
];

const state = reactive({
    errorMessage: '',
    evaluation: null,
    resultUrl: null,
    status: 'idle',
    submissionId: null,
});

export function useSubmissionPollingStore() {
    const polling = usePolling({
        intervalMs: POLLING_INTERVAL_MS,
        maxAttempts: MAX_POLLING_ATTEMPTS,
    });

    const isTerminal = computed(() => ['completed', 'failed', 'timeout', 'error'].includes(state.status));
    const failureKind = computed(() => {
        if (state.status !== 'failed') {
            return null;
        }

        const message = String(state.errorMessage || '').toLowerCase();

        return RECOGNITION_FAILURE_MARKERS.some((marker) => message.includes(marker))
            ? 'recognition'
            : 'unknown';
    });
    const isRecognitionFailure = computed(() => failureKind.value === 'recognition');

    const applyStatusPayload = (payload) => {
        state.status = payload.status;
        state.errorMessage = payload.error_message || '';
        state.evaluation = payload.evaluation || null;
        state.resultUrl = payload.result_url || null;

        if (payload.status === 'completed' || payload.status === 'failed') {
            polling.stop();

            if (payload.status === 'completed' && payload.result_url) {
                window.location.assign(payload.result_url);
            }
        }
    };

    const markTimeout = () => {
        state.status = 'timeout';
        state.errorMessage = '解析に時間がかかっています。しばらくしてから再度確認してください。';
    };

    const pollStatus = async () => {
        if (! state.submissionId) {
            polling.stop();

            return;
        }

        try {
            const response = await axios.get(`/api/submissions/${state.submissionId}/status`);
            applyStatusPayload(response.data);
        } catch {
            state.status = 'error';
            state.errorMessage = '解析状態を確認できませんでした。しばらくしてから再度確認してください。';
            polling.stop();
        }
    };

    const startPolling = (submissionId) => {
        state.submissionId = submissionId;
        state.status = 'pending';
        state.errorMessage = '';
        state.evaluation = null;
        state.resultUrl = null;

        polling.start(pollStatus, markTimeout);
    };

    const submitRecording = async ({ questionId, audioBlob }) => {
        if (! questionId || ! audioBlob) {
            state.status = 'error';
            state.errorMessage = '提出に必要な録音データを確認できませんでした。';

            return;
        }

        polling.reset();
        state.status = 'submitting';
        state.errorMessage = '';
        state.evaluation = null;
        state.resultUrl = null;
        state.submissionId = null;

        const formData = new FormData();
        formData.append('question_id', String(questionId));
        formData.append('audio', audioBlob, 'recording.webm');

        try {
            const response = await axios.post('/api/submissions', formData);

            state.submissionId = response.data.submission_id;
            state.status = response.data.status || 'pending';
            startPolling(response.data.submission_id);
        } catch {
            state.status = 'error';
            state.errorMessage = '音声を提出できませんでした。しばらくしてから再度確認してください。';
        }
    };

    const reset = () => {
        polling.reset();
        state.errorMessage = '';
        state.evaluation = null;
        state.resultUrl = null;
        state.status = 'idle';
        state.submissionId = null;
    };

    return {
        get isPolling() {
            return polling.state.isPolling;
        },
        get isTerminal() {
            return isTerminal.value;
        },
        get failureKind() {
            return failureKind.value;
        },
        get isRecognitionFailure() {
            return isRecognitionFailure.value;
        },
        get pollingAttempts() {
            return polling.state.attempts;
        },
        maxAttempts: MAX_POLLING_ATTEMPTS,
        pollingIntervalMs: POLLING_INTERVAL_MS,
        pollStatus,
        reset,
        startPolling,
        state,
        submitRecording,
    };
}
