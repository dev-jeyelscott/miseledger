import type { OrganizationPermission } from '@/types';

export type GuideModuleSlug =
    | 'getting-started'
    | 'dashboard'
    | 'ai-assistant'
    | 'inventory'
    | 'stock-counts'
    | 'waste'
    | 'stock-transfers'
    | 'purchasing'
    | 'recipes'
    | 'reports'
    | 'organization'
    | 'billing'
    | 'settings';

export type GuideAccessContext = {
    activeOrganizationId: number | null;
    aiCanUse: boolean;
    hasFeature: (
        feature: 'purchasing' | 'recipes' | 'locations.multi',
    ) => boolean;
    hasPermission: (permission: OrganizationPermission) => boolean;
};

export type GuideActionKey =
    | 'dashboard'
    | 'ai-assistant'
    | 'inventory-items'
    | 'stock-counts'
    | 'waste'
    | 'stock-transfers'
    | 'purchase-orders'
    | 'suppliers'
    | 'receiving'
    | 'recipes'
    | 'organization-settings'
    | 'locations'
    | 'members'
    | 'billing'
    | 'profile-settings';

export type GuideField = {
    description: string;
    name: string;
};

export type GuideTutorial = {
    id: string;
    steps: string[];
    title: string;
};

export type GuideModule = {
    accessNote?: string;
    actions: GuideActionKey[];
    description: string;
    fields: GuideField[];
    keywords: string[];
    navigationLabels: string[];
    overview: string;
    slug: GuideModuleSlug;
    title: string;
    troubleshooting: string[];
    tutorials: GuideTutorial[];
};
