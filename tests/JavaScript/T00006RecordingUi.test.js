import assert from 'node:assert/strict';
import test from 'node:test';

import {
    calculateRecordingUiTime,
    formatRecordingTime,
} from '../../resources/js/verification/t00006RecordingUi.js';

test('shows zero elapsed and the full remaining time at recording start', () => {
    assert.deepEqual(calculateRecordingUiTime(0, 10), {
        elapsedDisplaySeconds: 0,
        remainingDisplaySeconds: 10,
        elapsedLabel: '00:00',
        remainingLabel: '00:10',
        profileLabel: '00:10',
    });
});

test('shows elapsed and remaining time during a 10 second profile', () => {
    assert.deepEqual(calculateRecordingUiTime(3200, 10), {
        elapsedDisplaySeconds: 3,
        remainingDisplaySeconds: 7,
        elapsedLabel: '00:03',
        remainingLabel: '00:07',
        profileLabel: '00:10',
    });
});

test('uses floor for elapsed and ceil for remaining immediately before the end', () => {
    assert.deepEqual(calculateRecordingUiTime(9999, 10), {
        elapsedDisplaySeconds: 9,
        remainingDisplaySeconds: 1,
        elapsedLabel: '00:09',
        remainingLabel: '00:01',
        profileLabel: '00:10',
    });
});

test('shows the profile duration and zero remaining at the exact end', () => {
    assert.deepEqual(calculateRecordingUiTime(10000, 10), {
        elapsedDisplaySeconds: 10,
        remainingDisplaySeconds: 0,
        elapsedLabel: '00:10',
        remainingLabel: '00:00',
        profileLabel: '00:10',
    });
});

test('caps elapsed at the profile and never shows negative remaining time', () => {
    assert.deepEqual(calculateRecordingUiTime(12500, 10), {
        elapsedDisplaySeconds: 10,
        remainingDisplaySeconds: 0,
        elapsedLabel: '00:10',
        remainingLabel: '00:00',
        profileLabel: '00:10',
    });
});

test('formats every supported profile with two digit minutes and seconds', () => {
    const expectedLabels = new Map([
        [10, '00:10'],
        [40, '00:40'],
        [60, '01:00'],
        [90, '01:30'],
        [120, '02:00'],
    ]);

    for (const [profileSeconds, expectedLabel] of expectedLabels) {
        const result = calculateRecordingUiTime(0, profileSeconds);

        assert.equal(result.profileLabel, expectedLabel);
        assert.equal(result.remainingLabel, expectedLabel);
        assert.match(result.elapsedLabel, /^\d{2}:\d{2}$/);
        assert.match(result.remainingLabel, /^\d{2}:\d{2}$/);
        assert.match(result.profileLabel, /^\d{2}:\d{2}$/);
    }
});

test('formats 120 seconds as 02:00', () => {
    assert.equal(formatRecordingTime(120), '02:00');
});
