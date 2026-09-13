import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    formatBillingMinorAmount,
    groupIntegerDigits,
} from './billing-money.ts';

test('formats minor units beyond the JavaScript safe integer range without numeric coercion', () => {
    assert.equal(
        formatBillingMinorAmount('9007199254740993', 'PHP'),
        'PHP 90,071,992,547,409.93',
    );
});

test('keeps unsupported currency values explicitly in minor units', () => {
    assert.equal(
        formatBillingMinorAmount('123456', 'JPY'),
        'JPY 123,456 minor units',
    );
});

test('groups signed integer strings without converting them to numbers', () => {
    assert.equal(
        groupIntegerDigits('-9007199254740993'),
        '-9,007,199,254,740,993',
    );
});
