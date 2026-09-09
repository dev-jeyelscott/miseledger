import assert from 'node:assert/strict';
import { register } from 'node:module';
import { test } from 'node:test';
import {
    canOpenPurchasingGuideAction,
    canOpenRecipesGuideAction,
    canOpenReportGuideAction,
} from './access.ts';
import type { GuideAccessContext, GuideActionKey } from './types.ts';

// actions.ts imports Wayfinder-generated modules via the `@/` alias, which
// only Vite/tsc resolve. Register a resolve hook so plain `node --test` can
// exercise the real resolver instead of a re-implementation of it.
register('./test-alias-loader.mjs', import.meta.url);
const { resolveGuideAction } = await import('./actions.ts');

function guideAccessContext({
    activeOrganizationId = 1,
    purchasing = true,
    recipes = true,
    permissions = ['purchasing.view', 'recipes.view'],
}: {
    activeOrganizationId?: number | null;
    purchasing?: boolean;
    recipes?: boolean;
    permissions?: string[];
} = {}): GuideAccessContext {
    return {
        activeOrganizationId,
        aiCanUse: false,
        hasFeature: (feature) => {
            return feature === 'purchasing'
                ? purchasing
                : feature === 'recipes'
                  ? recipes
                  : false;
        },
        hasPermission: (permission) => permissions.includes(permission),
    };
}

test('purchasing guide actions fail closed without an active organization, feature, or view access', () => {
    assert.equal(
        canOpenPurchasingGuideAction(
            guideAccessContext({ activeOrganizationId: null }),
        ),
        false,
    );
    assert.equal(
        canOpenPurchasingGuideAction(guideAccessContext({ purchasing: false })),
        false,
    );
    assert.equal(
        canOpenPurchasingGuideAction(guideAccessContext({ permissions: [] })),
        false,
    );
    assert.equal(canOpenPurchasingGuideAction(guideAccessContext()), true);
});

test('recipe guide actions fail closed without an active organization, feature, or view access', () => {
    assert.equal(
        canOpenRecipesGuideAction(
            guideAccessContext({ activeOrganizationId: null }),
        ),
        false,
    );
    assert.equal(
        canOpenRecipesGuideAction(guideAccessContext({ recipes: false })),
        false,
    );
    assert.equal(
        canOpenRecipesGuideAction(guideAccessContext({ permissions: [] })),
        false,
    );
    assert.equal(canOpenRecipesGuideAction(guideAccessContext()), true);
});

function reportGuideAccessContext({
    activeOrganizationId = 1,
    permissions = ['reports.view'],
}: {
    activeOrganizationId?: number | null;
    permissions?: string[];
} = {}): GuideAccessContext {
    return {
        activeOrganizationId,
        aiCanUse: false,
        hasFeature: () => false,
        hasPermission: (permission) => permissions.includes(permission),
    };
}

test('report guide actions require reports.view and fail closed without an active organization or membership', () => {
    assert.equal(canOpenReportGuideAction(reportGuideAccessContext()), true);
    assert.equal(
        canOpenReportGuideAction(reportGuideAccessContext({ permissions: [] })),
        false,
    );
    assert.equal(
        canOpenReportGuideAction(
            reportGuideAccessContext({ activeOrganizationId: null }),
        ),
        false,
    );
    assert.equal(
        canOpenReportGuideAction(
            reportGuideAccessContext({
                activeOrganizationId: null,
                permissions: [],
            }),
        ),
        false,
    );
});

const reportGuideActions: {
    key: GuideActionKey;
    label: string;
    urlContains: string;
}[] = [
    {
        key: 'report-stock-on-hand',
        label: 'Open Stock on hand',
        urlContains: 'stock-on-hand',
    },
    {
        key: 'report-low-stock',
        label: 'Open Low stock',
        urlContains: 'low-stock',
    },
    {
        key: 'report-stock-movements',
        label: 'Open Stock movement ledger',
        urlContains: 'stock-movement',
    },
    {
        key: 'report-valuation',
        label: 'Open Inventory valuation',
        urlContains: 'valuation',
    },
    {
        key: 'report-purchasing-history',
        label: 'Open Purchasing history',
        urlContains: 'purchasing-history',
    },
];

for (const { key, label, urlContains } of reportGuideActions) {
    test(`${key} guide action resolves its label and destination when reports.view is granted`, () => {
        const action = resolveGuideAction(key, reportGuideAccessContext());

        assert.ok(action, `expected ${key} to resolve an action`);
        assert.equal(action?.label, label);
        assert.ok(
            action?.href.includes(urlContains),
            `expected ${key} href "${action?.href}" to contain "${urlContains}"`,
        );
    });

    test(`${key} guide action fails closed without reports.view`, () => {
        assert.equal(
            resolveGuideAction(
                key,
                reportGuideAccessContext({ permissions: [] }),
            ),
            null,
        );
    });

    test(`${key} guide action fails closed without an active organization`, () => {
        assert.equal(
            resolveGuideAction(
                key,
                reportGuideAccessContext({ activeOrganizationId: null }),
            ),
            null,
        );
    });
}
