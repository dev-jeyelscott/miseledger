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

test('organization, billing, and settings topics remain discoverable by their customer-facing terms', () => {
    const expectedTopics = [
        [
            'create organization',
            'create-and-switch-organizations',
            'organization',
        ],
        ['storage location', 'storage-locations', 'organization'],
        ['storage area', 'storage-locations', 'organization'],
        ['add member', 'members-and-access', 'organization'],
        ['role', 'members-and-access', 'organization'],
        ['ai access', 'members-and-access', 'organization'],
        ['currency', 'organization-settings', 'organization'],
        ['timezone', 'organization-settings', 'organization'],
        ['subscription status', 'current-plan-and-subscription', 'billing'],
        ['past due', 'subscription-status-meanings', 'billing'],
        ['unpaid', 'subscription-status-meanings', 'billing'],
        ['read-only', 'read-only-and-recovery', 'billing'],
        ['usage limit', 'plan-features-and-usage-limits', 'billing'],
        ['cancel renewal', 'billing-management-actions', 'billing'],
        ['delete account', 'profile-settings', 'settings'],
        ['password policy', 'security-password', 'settings'],
        ['passkey', 'security-passkeys', 'settings'],
        ['two-factor', 'security-two-factor', 'settings'],
        ['recovery codes', 'security-two-factor', 'settings'],
        ['dark', 'appearance-settings', 'settings'],
        ['system', 'appearance-settings', 'settings'],
    ] as const;

    for (const [query, anchor, slug] of expectedTopics) {
        assert.ok(
            searchGuideTopics(guideModules, query).some(
                (result) =>
                    result.module.slug === slug && result.anchor === anchor,
            ),
            `Expected guide search for "${query}" to find #${anchor} in ${slug}.`,
        );
    }
});

test('cancelled subscription copy explains continued access until the end date, not immediate read-only', () => {
    const billingModule = guideModules.find(
        (module) => module.slug === 'billing',
    );

    assert.ok(billingModule, 'expected a billing module');

    const statusPage = billingModule.pages?.find(
        (page) => page.id === 'subscription-status-meanings',
    );

    assert.ok(statusPage, 'expected a subscription-status-meanings page');

    const cancelledNote = statusPage.notes?.find(
        (note) => note.title === 'Cancelled',
    );

    assert.ok(cancelledNote, 'expected a Cancelled note');
    assert.ok(
        cancelledNote.description.includes('Normal changes stay available'),
        'Expected the Cancelled note to explain that normal access continues until the end date.',
    );
    assert.ok(
        !cancelledNote.description.startsWith(
            'The subscription was cancelled and is no longer renewing. The organization is read-only',
        ),
        'Cancelled note must not claim read-only access starts immediately.',
    );
});

test('billing copy never uses internal provider or lifecycle terminology', () => {
    const billingModule = guideModules.find(
        (module) => module.slug === 'billing',
    );

    assert.ok(billingModule, 'expected a billing module');

    const serialized = JSON.stringify(billingModule);
    const forbiddenTerms = [
        'stable internal plan',
        'commercial lifecycle status',
        'validated provider settlement path',
        'PlanCode',
        'server-authoritative',
    ];

    for (const term of forbiddenTerms) {
        assert.ok(
            !serialized.includes(term),
            `Expected billing guide content to avoid internal terminology: "${term}".`,
        );
    }
});
