import assert from 'node:assert/strict';
import { test } from 'node:test';

import { getProblemReportStatusPresentation } from './status.ts';

test('submitted resolves to the Submitted label with the info variant', () => {
    assert.deepEqual(getProblemReportStatusPresentation('submitted'), {
        label: 'Submitted',
        variant: 'info',
    });
});

test('in-review resolves to the In Review label with the warning variant', () => {
    assert.deepEqual(getProblemReportStatusPresentation('in-review'), {
        label: 'In Review',
        variant: 'warning',
    });
});

test('in-progress resolves to the In Progress label with the info variant', () => {
    assert.deepEqual(getProblemReportStatusPresentation('in-progress'), {
        label: 'In Progress',
        variant: 'info',
    });
});

test('resolved resolves to the Resolved label with the success variant', () => {
    assert.deepEqual(getProblemReportStatusPresentation('resolved'), {
        label: 'Resolved',
        variant: 'success',
    });
});

test('closed resolves to the Closed label with the neutral variant', () => {
    assert.deepEqual(getProblemReportStatusPresentation('closed'), {
        label: 'Closed',
        variant: 'neutral',
    });
});

test('an unknown status falls back to its raw value with the neutral variant', () => {
    assert.deepEqual(getProblemReportStatusPresentation('archived'), {
        label: 'archived',
        variant: 'neutral',
    });
});
