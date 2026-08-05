const normalizeProfileSeconds = (profileSeconds) => {
    const numericProfileSeconds = Number(profileSeconds);

    if (! Number.isFinite(numericProfileSeconds) || numericProfileSeconds < 0) {
        return 0;
    }

    return Math.floor(numericProfileSeconds);
};

export const formatRecordingTime = (seconds) => {
    const normalizedSeconds = Math.max(Math.floor(Number(seconds) || 0), 0);
    const minutes = Math.floor(normalizedSeconds / 60);
    const remainingSeconds = normalizedSeconds % 60;

    return `${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
};

export const calculateRecordingUiTime = (elapsedMs, profileSeconds) => {
    const normalizedProfileSeconds = normalizeProfileSeconds(profileSeconds);
    const numericElapsedMs = Number(elapsedMs);
    const normalizedElapsedMs = Number.isFinite(numericElapsedMs)
        ? Math.max(numericElapsedMs, 0)
        : 0;
    const elapsedDisplaySeconds = Math.min(
        Math.floor(normalizedElapsedMs / 1000),
        normalizedProfileSeconds,
    );
    const remainingDisplaySeconds = Math.max(
        Math.ceil((normalizedProfileSeconds * 1000 - normalizedElapsedMs) / 1000),
        0,
    );

    return {
        elapsedDisplaySeconds,
        remainingDisplaySeconds,
        elapsedLabel: formatRecordingTime(elapsedDisplaySeconds),
        remainingLabel: formatRecordingTime(remainingDisplaySeconds),
        profileLabel: formatRecordingTime(normalizedProfileSeconds),
    };
};
