import { computed, ref } from 'vue';

const preferredMimeTypes = [
    'audio/webm;codecs=opus',
    'audio/webm',
];

const getMediaDevices = () => {
    if (typeof navigator === 'undefined') {
        return null;
    }

    return navigator.mediaDevices ?? null;
};

const getMediaRecorder = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.MediaRecorder ?? null;
};

const resolveMimeType = (MediaRecorderApi) => {
    if (! MediaRecorderApi?.isTypeSupported) {
        return '';
    }

    return preferredMimeTypes.find((type) => MediaRecorderApi.isTypeSupported(type)) ?? '';
};

export function useAudioRecorder() {
    const mediaRecorder = ref(null);
    const mediaStream = ref(null);
    const audioBlob = ref(null);
    const errorMessage = ref('');
    const permissionDenied = ref(false);
    const mimeType = ref('');
    const isRecording = ref(false);
    let recordedChunks = [];

    const isSupported = computed(() => {
        const MediaRecorderApi = getMediaRecorder();
        const mediaDevices = getMediaDevices();

        return Boolean(MediaRecorderApi && mediaDevices?.getUserMedia);
    });

    const stopTracks = () => {
        mediaStream.value?.getTracks().forEach((track) => track.stop());
        mediaStream.value = null;
    };

    const reset = () => {
        if (isRecording.value && mediaRecorder.value?.state === 'recording') {
            mediaRecorder.value.stop();
        }

        stopTracks();
        mediaRecorder.value = null;
        audioBlob.value = null;
        recordedChunks = [];
        errorMessage.value = '';
        permissionDenied.value = false;
        mimeType.value = '';
        isRecording.value = false;
    };

    const setError = (message, denied = false) => {
        errorMessage.value = message;
        permissionDenied.value = denied;
        isRecording.value = false;
        stopTracks();
    };

    const start = async () => {
        if (isRecording.value) {
            return;
        }

        const MediaRecorderApi = getMediaRecorder();
        const mediaDevices = getMediaDevices();

        if (! MediaRecorderApi || ! mediaDevices?.getUserMedia) {
            setError('このブラウザでは録音機能を利用できません。');

            return;
        }

        errorMessage.value = '';
        permissionDenied.value = false;
        audioBlob.value = null;
        recordedChunks = [];

        try {
            const stream = await mediaDevices.getUserMedia({ audio: true });
            const selectedMimeType = resolveMimeType(MediaRecorderApi);
            const recorder = selectedMimeType
                ? new MediaRecorderApi(stream, { mimeType: selectedMimeType })
                : new MediaRecorderApi(stream);

            mediaStream.value = stream;
            mediaRecorder.value = recorder;
            mimeType.value = selectedMimeType || recorder.mimeType || '';

            recorder.addEventListener('dataavailable', (event) => {
                if (event.data?.size > 0) {
                    recordedChunks.push(event.data);
                }
            });

            recorder.addEventListener('error', () => {
                setError('録音中にエラーが発生しました。');
            });

            recorder.start();
            isRecording.value = true;
        } catch (error) {
            const denied = error?.name === 'NotAllowedError' || error?.name === 'SecurityError';
            setError(
                denied
                    ? 'マイクの使用が許可されませんでした。ブラウザの権限設定を確認してください。'
                    : '録音を開始できませんでした。時間をおいて再度お試しください。',
                denied,
            );
        }
    };

    const stop = () => new Promise((resolve) => {
        const recorder = mediaRecorder.value;

        if (! isRecording.value || ! recorder || recorder.state !== 'recording') {
            resolve(audioBlob.value);

            return;
        }

        recorder.addEventListener('stop', () => {
            const blob = new Blob(recordedChunks, { type: mimeType.value || recorder.mimeType || 'audio/webm' });

            audioBlob.value = blob;
            recordedChunks = [];
            isRecording.value = false;
            stopTracks();
            resolve(blob);
        }, { once: true });

        recorder.stop();
    });

    return {
        audioBlob,
        errorMessage,
        isRecording,
        isSupported,
        mimeType,
        permissionDenied,
        reset,
        start,
        stop,
    };
}
