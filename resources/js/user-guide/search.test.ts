import assert from 'node:assert/strict';
import { test } from 'node:test';
import { searchGuideTopics } from './search.ts';
import type { GuideModule } from './types.ts';

const modules: GuideModule[] = [
    {
        actions: [],
        description: 'Manage suppliers and goods receipts.',
        fields: [
            {
                name: 'Waste reason',
                description: 'The reason inventory was lost.',
            },
        ],
        keywords: ['purchasing'],
        navigationLabels: ['Purchase orders'],
        overview: 'Record received goods accurately.',
        slug: 'purchasing',
        title: 'Purchasing',
        troubleshooting: ['Check your active organization.'],
        tutorials: [
            {
                id: 'receive-a-purchase-order',
                title: 'Receive a purchase order',
                steps: ['Select the received goods.'],
            },
        ],
    },
];

/** Verify tutorial matching remains case-insensitive and preserves the tutorial anchor. */
test('guide search is case insensitive and matches tutorials', () => {
    const results = searchGuideTopics(modules, 'ReCeIvE A PuRcHaSe');

    assert.ok(
        results.some(
            (result) =>
                result.module.slug === 'purchasing' &&
                result.anchor === 'receive-a-purchase-order',
        ),
    );
});

/** Verify field matching and explicit unmatched-search behavior remain intact. */
test('guide search matches fields and returns no results for an unmatched query', () => {
    assert.ok(
        searchGuideTopics(modules, 'Waste reason').some(
            (result) => result.module.slug === 'purchasing',
        ),
    );
    assert.deepEqual(searchGuideTopics(modules, 'not-a-guide-topic'), []);
});

/** Verify module metadata and troubleshooting remain part of the supported search surface. */
test('guide search matches module metadata and troubleshooting', () => {
    const moduleResults = searchGuideTopics(modules, 'Purchase orders');

    assert.ok(
        moduleResults.some(
            (result) =>
                result.module.slug === 'purchasing' &&
                result.topic === 'Purchasing' &&
                result.anchor === null,
        ),
    );

    const troubleshootingResults = searchGuideTopics(
        modules,
        'active organization',
    );

    assert.ok(
        troubleshootingResults.some(
            (result) =>
                result.module.slug === 'purchasing' &&
                result.topic === 'Check your active organization.' &&
                result.anchor === null,
        ),
    );
});

/** Verify whitespace-only queries continue to represent the normal discovery state. */
test('an empty guide search restores category browsing', () => {
    assert.deepEqual(searchGuideTopics(modules, '   '), []);
});
