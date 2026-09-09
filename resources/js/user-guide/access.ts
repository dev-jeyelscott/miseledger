import type { GuideAccessContext } from './types';

export function canOpenPurchasingGuideAction(
    context: GuideAccessContext,
): boolean {
    return (
        context.activeOrganizationId !== null &&
        context.hasFeature('purchasing') &&
        context.hasPermission('purchasing.view')
    );
}

export function canOpenRecipesGuideAction(
    context: GuideAccessContext,
): boolean {
    return (
        context.activeOrganizationId !== null &&
        context.hasFeature('recipes') &&
        context.hasPermission('recipes.view')
    );
}
