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

function organizationScopedAccessContext({
    activeOrganizationId = 1,
    hasFeature = () => true,
    permissions = [
        'organization.manage',
        'locations.manage',
        'users.manage',
        'billing.manage',
    ],
}: {
    activeOrganizationId?: number | null;
    hasFeature?: GuideAccessContext['hasFeature'];
    permissions?: string[];
} = {}): GuideAccessContext {
    return {
        activeOrganizationId,
        aiCanUse: false,
        hasFeature,
        hasPermission: (permission) => permissions.includes(permission),
    };
}

test('create-organization guide action is available without an active organization or organization role', () => {
    const action = resolveGuideAction(
        'create-organization',
        organizationScopedAccessContext({
            activeOrganizationId: null,
            permissions: [],
        }),
    );

    assert.ok(action, 'expected create-organization to resolve an action');
    assert.equal(action?.label, 'Create organization');
    assert.ok(
        action?.href.includes('organizations/create'),
        `expected create-organization href "${action?.href}" to contain "organizations/create"`,
    );
});

const organizationScopedActions: {
    key: GuideActionKey;
    label: string;
    permission: string;
    urlContains: string;
}[] = [
    {
        key: 'organization-settings',
        label: 'Open organization settings',
        permission: 'organization.manage',
        urlContains: '',
    },
    {
        key: 'locations',
        label: 'Open locations',
        permission: 'locations.manage',
        urlContains: 'locations',
    },
    {
        key: 'members',
        label: 'Open members',
        permission: 'users.manage',
        urlContains: 'members',
    },
    {
        key: 'billing',
        label: 'Open billing',
        permission: 'billing.manage',
        urlContains: 'billing',
    },
];

for (const {
    key,
    label,
    permission,
    urlContains,
} of organizationScopedActions) {
    test(`${key} guide action resolves its label and destination when ${permission} is granted`, () => {
        const action = resolveGuideAction(
            key,
            organizationScopedAccessContext(),
        );

        assert.ok(action, `expected ${key} to resolve an action`);
        assert.equal(action?.label, label);

        if (urlContains !== '') {
            assert.ok(
                action?.href.includes(urlContains),
                `expected ${key} href "${action?.href}" to contain "${urlContains}"`,
            );
        }
    });

    test(`${key} guide action fails closed without ${permission}`, () => {
        assert.equal(
            resolveGuideAction(
                key,
                organizationScopedAccessContext({ permissions: [] }),
            ),
            null,
        );
    });

    test(`${key} guide action fails closed without an active organization`, () => {
        assert.equal(
            resolveGuideAction(
                key,
                organizationScopedAccessContext({ activeOrganizationId: null }),
            ),
            null,
        );
    });
}

test('locations guide action fails closed without the locations.multi feature even when locations.manage is granted', () => {
    assert.equal(
        resolveGuideAction(
            'locations',
            organizationScopedAccessContext({ hasFeature: () => false }),
        ),
        null,
    );
});

function personalAccessContext({
    activeOrganizationId = null,
}: {
    activeOrganizationId?: number | null;
} = {}): GuideAccessContext {
    return {
        activeOrganizationId,
        aiCanUse: false,
        hasFeature: () => false,
        hasPermission: () => false,
    };
}

const personalSettingsActions: {
    key: GuideActionKey;
    label: string;
    urlContains: string;
}[] = [
    {
        key: 'profile-settings',
        label: 'Open profile settings',
        urlContains: 'settings/profile',
    },
    {
        key: 'security-settings',
        label: 'Open security settings',
        urlContains: 'settings/security',
    },
    {
        key: 'appearance-settings',
        label: 'Open appearance settings',
        urlContains: 'settings/appearance',
    },
];

for (const { key, label, urlContains } of personalSettingsActions) {
    test(`${key} guide action is always available, including with no active organization or role`, () => {
        const action = resolveGuideAction(key, personalAccessContext());

        assert.ok(action, `expected ${key} to resolve an action`);
        assert.equal(action?.label, label);
        assert.ok(
            action?.href.includes(urlContains),
            `expected ${key} href "${action?.href}" to contain "${urlContains}"`,
        );
    });
}
