import assert from 'node:assert/strict';
import { test } from 'node:test';

import { createScanDebouncer } from './scan-debounce.ts';

test('rejects a value on its first frame and accepts it once it repeats', () => {
    const debouncer = createScanDebouncer();

    assert.equal(debouncer.accept('0123456789012'), false);
    assert.equal(debouncer.accept('0123456789012'), true);
});

test('resets the repeat streak when a different value is decoded mid-stream', () => {
    const debouncer = createScanDebouncer();

    assert.equal(debouncer.accept('AAA'), false);
    assert.equal(debouncer.accept('BBB'), false);
    assert.equal(debouncer.accept('BBB'), true);
});

test('clear() resets the buffer so the same physical item can be scanned again', () => {
    const debouncer = createScanDebouncer();

    assert.equal(debouncer.accept('0123456789012'), false);
    assert.equal(debouncer.accept('0123456789012'), true);

    debouncer.clear();

    assert.equal(debouncer.accept('0123456789012'), false);
    assert.equal(debouncer.accept('0123456789012'), true);
});
