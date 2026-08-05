const allowedProfiles = new Set([10, 40, 60, 90, 120]);

const identityValue = (identity, snakeCaseKey, camelCaseKey) => (
    identity?.[snakeCaseKey] ?? identity?.[camelCaseKey]
);

const finiteInteger = (value, fieldName) => {
    const number = Number(value);

    if (! Number.isFinite(number) || ! Number.isInteger(number)) {
        throw new TypeError(`${fieldName} must be a finite integer.`);
    }

    return number;
};

export const normalizeTrialIdentity = (identity) => {
    const environmentId = String(identityValue(identity, 'environment_id', 'environmentId') ?? '');
    const profileSeconds = finiteInteger(
        identityValue(identity, 'profile_seconds', 'profileSeconds'),
        'profile_seconds',
    );
    const runNumber = finiteInteger(identityValue(identity, 'run_number', 'runNumber'), 'run_number');
    const attemptNumber = finiteInteger(
        identityValue(identity, 'attempt_number', 'attemptNumber'),
        'attempt_number',
    );

    if (! /^[a-z0-9-]+$/.test(environmentId) || environmentId.length > 32) {
        throw new TypeError('environment_id is invalid.');
    }

    if (! allowedProfiles.has(profileSeconds)) {
        throw new RangeError('profile_seconds is invalid.');
    }

    if (runNumber < 1 || runNumber > 5) {
        throw new RangeError('run_number is invalid.');
    }

    if (attemptNumber < 1 || attemptNumber > 99) {
        throw new RangeError('attempt_number is invalid.');
    }

    return Object.freeze({
        environment_id: environmentId,
        profile_seconds: profileSeconds,
        run_number: runNumber,
        attempt_number: attemptNumber,
    });
};

export const buildTrialId = (identity) => {
    const normalized = normalizeTrialIdentity(identity);

    return [
        normalized.environment_id,
        `p${String(normalized.profile_seconds).padStart(3, '0')}`,
        `r${String(normalized.run_number).padStart(2, '0')}`,
        `a${String(normalized.attempt_number).padStart(2, '0')}`,
    ].join('-');
};

export const buildTrialQuery = (identity) => {
    const normalized = normalizeTrialIdentity(identity);
    const query = new URLSearchParams();

    query.append('environment_id', normalized.environment_id);
    query.append('profile_seconds', String(normalized.profile_seconds));
    query.append('run_number', String(normalized.run_number));
    query.append('attempt_number', String(normalized.attempt_number));

    return query.toString();
};

export const lockTrialIdentity = (identity) => {
    const normalized = normalizeTrialIdentity(identity);

    return Object.freeze({
        ...normalized,
        trial_id: buildTrialId(normalized),
    });
};

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
