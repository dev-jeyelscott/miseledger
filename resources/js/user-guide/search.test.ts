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

test('guide search indexes module, navigation, field, and troubleshooting content', () => {
    for (const query of [
        'manage suppliers',
        'record received goods',
        'purchasing',
        'purchase orders',
        'waste reason',
        'active organization',
    ]) {
        assert.ok(
            searchGuideTopics(modules, query).some(
                (result) => result.module.slug === 'purchasing',
            ),
            `Expected ${query} to find the purchasing guide module.`,
        );
    }
});

test('guide search normalizes full-width input and returns no results for an unmatched query', () => {
    assert.ok(
        searchGuideTopics(modules, 'ＰＵＲＣＨＡＳＩＮＧ').some(
            (result) => result.module.slug === 'purchasing',
        ),
    );
    assert.deepEqual(searchGuideTopics(modules, 'not-a-guide-topic'), []);
});

test('an empty guide search restores category browsing', () => {
    assert.deepEqual(searchGuideTopics(modules, '   '), []);
});
