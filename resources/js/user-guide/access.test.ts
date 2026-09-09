import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    canOpenPurchasingGuideAction,
    canOpenRecipesGuideAction,
} from './access.ts';
import type { GuideAccessContext } from './types.ts';

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
