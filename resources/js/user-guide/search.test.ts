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
        pages: [
            {
                id: 'receiving-overview',
                title: 'Receive goods',
                summary: 'Review goods before finalizing a receipt.',
                controls: [
                    {
                        label: 'Finalize receipt',
                        description: 'Complete the receipt for received goods.',
                    },
                ],
                fields: [
                    {
                        name: 'Waste reason',
                        description: 'The reason inventory was lost.',
                    },
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not finalize a receipt?',
                        answer: 'Check the receipt details and your access.',
                    },
                    {
                        question: 'Which organization is active?',
                        answer: 'Check your active organization.',
                    },
                ],
                tutorials: [
                    {
                        id: 'receive-a-purchase-order',
                        title: 'Receive a purchase order',
                        steps: ['Select the received goods.'],
                    },
                ],
            },
        ],
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

test('guide search indexes page titles, controls, and troubleshooting at stable page anchors', () => {
    for (const query of [
        'receive goods',
        'finalize receipt',
        'why can i not finalize',
    ]) {
        assert.ok(
            searchGuideTopics(modules, query).some(
                (result) =>
                    result.topic === 'Receive goods' &&
                    result.anchor === 'receiving-overview',
            ),
            `Expected ${query} to find the receiving overview anchor.`,
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

test('guide search reaches stock workflow actions, variance topics, and stable tutorial anchors', () => {
    const workflowModules: GuideModule[] = [
        {
            actions: [],
            description: 'Complete stock workflow guidance.',
            fields: [],
            keywords: [],
            navigationLabels: [],
            overview: 'Lifecycle actions and reports.',
            pages: [
                {
                    id: 'stock-count-variance-report',
                    title: 'Stock count variance report',
                    summary: 'Review finalized count variance.',
                    tutorials: [
                        {
                            id: 'finalize-a-count',
                            title: 'Finalize a count',
                            steps: ['Finalize the submitted count.'],
                        },
                    ],
                },
            ],
            slug: 'stock-counts',
            title: 'Stock counts',
            troubleshooting: [],
            tutorials: [],
        },
        {
            actions: [],
            description: 'Record and report operational waste.',
            fields: [],
            keywords: [],
            navigationLabels: [],
            overview: 'Waste lifecycle guidance.',
            pages: [
                {
                    id: 'waste-reports-and-export',
                    title: 'Waste report and export',
                    summary: 'Waste export report evidence.',
                    tutorials: [
                        {
                            id: 'record-waste',
                            title: 'Record waste',
                            steps: ['Record the operational loss.'],
                        },
                    ],
                },
            ],
            slug: 'waste',
            title: 'Waste',
            troubleshooting: [],
            tutorials: [],
        },
        {
            actions: [],
            description: 'Move stock through shipment and receipt.',
            fields: [],
            keywords: [],
            navigationLabels: [],
            overview: 'Transfer lifecycle guidance.',
            pages: [
                {
                    id: 'stock-transfer-variance-report',
                    title: 'Stock transfer variance report',
                    summary: 'Review transfer variance.',
                    tutorials: [
                        {
                            id: 'ship-a-transfer',
                            title: 'Ship a transfer',
                            steps: ['Ship stock from the source.'],
                        },
                        {
                            id: 'receive-a-transfer',
                            title: 'Receive a transfer',
                            steps: ['Receive stock at the destination.'],
                        },
                    ],
                },
            ],
            slug: 'stock-transfers',
            title: 'Stock transfers',
            troubleshooting: [],
            tutorials: [],
        },
    ];
    const expectedAnchors = [
        ['finalize a count', 'stock-counts', 'finalize-a-count'],
        ['variance report', 'stock-counts', 'stock-count-variance-report'],
        ['record waste', 'waste', 'record-waste'],
        ['waste export', 'waste', 'waste-reports-and-export'],
        ['ship a transfer', 'stock-transfers', 'ship-a-transfer'],
        ['receive a transfer', 'stock-transfers', 'receive-a-transfer'],
        [
            'transfer variance',
            'stock-transfers',
            'stock-transfer-variance-report',
        ],
    ] as const;

    for (const [query, slug, anchor] of expectedAnchors) {
        assert.ok(
            searchGuideTopics(workflowModules, query).some(
                (result) =>
                    result.module.slug === slug && result.anchor === anchor,
            ),
            `Expected ${query} to find #${anchor}.`,
        );
    }
});
