import { computed, reactive } from 'vue';
import { useAudioRecorder } from '@/Composables/useAudioRecorder';

const recorder = useAudioRecorder();

const state = reactive({
    status: 'idle',
    elapsedSeconds: 0,
    audioBlob: null,
    audioUrl: '',
    errorMessage: '',
    permissionDenied: false,
    unsupported: false,
});

let timerId = null;

const clearTimer = () => {
    if (timerId !== null) {
        window.clearInterval(timerId);
        timerId = null;
    }
};

const startTimer = () => {
    clearTimer();
    state.elapsedSeconds = 0;
    timerId = window.setInterval(() => {
        state.elapsedSeconds += 1;
    }, 1000);
};

const revokeAudioUrl = () => {
    if (state.audioUrl) {
        URL.revokeObjectURL(state.audioUrl);
        state.audioUrl = '';
    }
};

const syncErrorState = () => {
    state.status = 'error';
    state.errorMessage = recorder.errorMessage.value;
    state.permissionDenied = recorder.permissionDenied.value;
    state.unsupported = ! recorder.isSupported.value;
};

export function useRecordingStore() {
    const hasRecording = computed(() => Boolean(state.audioBlob));

    const start = async () => {
        if (state.status === 'recording') {
            return;
        }

        reset();

        if (! recorder.isSupported.value) {
            state.status = 'error';
            state.unsupported = true;
            state.errorMessage = 'このブラウザでは録音機能を利用できません。';

            return;
        }

        await recorder.start();

        if (recorder.errorMessage.value) {
            syncErrorState();

            return;
        }

        state.status = 'recording';
        startTimer();
    };

    const stop = async () => {
        if (state.status !== 'recording') {
            return;
        }

        const blob = await recorder.stop();
        clearTimer();

        if (recorder.errorMessage.value) {
            syncErrorState();

            return;
        }

        state.audioBlob = blob;
        state.audioUrl = blob ? URL.createObjectURL(blob) : '';
        state.status = blob ? 'recorded' : 'idle';
    };

    const reset = () => {
        clearTimer();
        revokeAudioUrl();
        recorder.reset();
        state.status = 'idle';
        state.elapsedSeconds = 0;
        state.audioBlob = null;
        state.errorMessage = '';
        state.permissionDenied = false;
        state.unsupported = false;
    };

    return {
        hasRecording,
        reset,
        start,
        state,
        stop,
    };
}
