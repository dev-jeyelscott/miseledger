import assert from 'node:assert/strict';
import { test } from 'node:test';
import { guideModules } from '../pages/user-guide/content.ts';
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

test('purchasing and recipe guidance topics remain discoverable by their customer-facing terms', () => {
    const expectedTopics = [
        ['supplier item', 'supplier-items-and-prices'],
        ['current price', 'supplier-items-and-prices'],
        ['approve purchase order', 'approve-or-cancel-a-purchase-order'],
        ['cancel purchase order', 'approve-or-cancel-a-purchase-order'],
        ['partial receipt', 'record-a-partial-receipt'],
        ['complete receipt', 'record-a-complete-receipt'],
        ['recipe version', 'recipe-versions'],
        ['recipe cost', 'recipe-cost'],
    ] as const;

    for (const [query, anchor] of expectedTopics) {
        assert.ok(
            searchGuideTopics(guideModules, query).some(
                (result) => result.anchor === anchor,
            ),
            `Expected guide search for "${query}" to find #${anchor}.`,
        );
    }
});

test('every report title is discoverable by its exact customer-facing name', () => {
    const expectedTopics = [
        ['Stock on hand', 'stock-on-hand'],
        ['Low stock', 'low-stock'],
        ['Stock movement ledger', 'stock-movement-ledger'],
        ['Inventory valuation', 'inventory-valuation'],
        ['Purchasing history', 'purchasing-history'],
    ] as const;

    for (const [query, anchor] of expectedTopics) {
        assert.ok(
            searchGuideTopics(guideModules, query).some(
                (result) =>
                    result.module.slug === 'reports' &&
                    result.anchor === anchor,
            ),
            `Expected guide search for "${query}" to find #${anchor}.`,
        );
    }
});

test('report filters and export guidance remain discoverable', () => {
    const expectedTopics = [
        ['storage location', 'stock-on-hand'],
        ['status', 'low-stock'],
        ['movement type', 'stock-movement-ledger'],
        ['grand total', 'inventory-valuation'],
        ['receipt state', 'purchasing-history'],
    ] as const;

    for (const [query, anchor] of expectedTopics) {
        assert.ok(
            searchGuideTopics(guideModules, query).some(
                (result) =>
                    result.module.slug === 'reports' &&
                    result.anchor === anchor,
            ),
            `Expected guide search for "${query}" to find #${anchor}.`,
        );
    }

    const exportSupportedAnchors = [
        'stock-on-hand',
        'stock-movement-ledger',
        'inventory-valuation',
        'purchasing-history',
    ] as const;
    const exportResults = searchGuideTopics(guideModules, 'export');

    for (const anchor of exportSupportedAnchors) {
        assert.ok(
            exportResults.some(
                (result) =>
                    result.module.slug === 'reports' &&
                    result.anchor === anchor,
            ),
            `Expected export guidance to be discoverable for #${anchor}.`,
        );
    }

    assert.ok(
        searchGuideTopics(guideModules, 'no export option').some(
            (result) =>
                result.module.slug === 'reports' &&
                result.anchor === 'low-stock',
        ),
        'Expected guide search for "no export option" to find #low-stock.',
    );
});

test('AI Assistant question examples and verification guidance remain discoverable', () => {
    const expectedTopics = [
        ['how much of', 'asking-useful-questions'],
        ['stock movements happened', 'asking-useful-questions'],
        ['purchase orders from', 'asking-useful-questions'],
        ['currently out of stock', 'asking-useful-questions'],
        ['verify an ai assistant answer', 'verify-an-answer'],
    ] as const;

    for (const [query, anchor] of expectedTopics) {
        assert.ok(
            searchGuideTopics(guideModules, query).some(
                (result) =>
                    result.module.slug === 'ai-assistant' &&
                    result.anchor === anchor,
            ),
            `Expected guide search for "${query}" to find #${anchor}.`,
        );
    }
});
