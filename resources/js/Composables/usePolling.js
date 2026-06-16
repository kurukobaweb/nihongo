import { computed, onUnmounted, reactive } from 'vue';

export function usePolling({ intervalMs = 3000, maxAttempts = 60 } = {}) {
    const state = reactive({
        attempts: 0,
        isPolling: false,
        timedOut: false,
    });

    let timerId = null;
    let running = false;

    const clearTimer = () => {
        if (timerId !== null) {
            window.clearInterval(timerId);
            timerId = null;
        }
    };

    const stop = () => {
        clearTimer();
        state.isPolling = false;
        running = false;
    };

    const reset = () => {
        stop();
        state.attempts = 0;
        state.timedOut = false;
    };

    const run = async (callback, onTimeout) => {
        if (! state.isPolling || running) {
            return;
        }

        running = true;
        state.attempts += 1;

        try {
            await callback(state.attempts);
        } finally {
            running = false;
        }

        if (! state.isPolling) {
            return;
        }

        if (state.attempts >= maxAttempts) {
            state.timedOut = true;
            stop();
            onTimeout?.();
        }
    };

    const start = (callback, onTimeout = null) => {
        reset();
        state.isPolling = true;
        timerId = window.setInterval(() => {
            run(callback, onTimeout);
        }, intervalMs);
    };

    onUnmounted(() => {
        stop();
    });

    return {
        attempts: computed(() => state.attempts),
        isPolling: computed(() => state.isPolling),
        reset,
        start,
        state,
        stop,
        timedOut: computed(() => state.timedOut),
    };
}
