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
        feature:
            'purchasing' | 'recipes' | 'locations.multi' | 'reports.export',
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
    | 'profile-settings'
    | 'security-settings'
    | 'appearance-settings'
    | 'report-stock-on-hand'
    | 'report-low-stock'
    | 'report-stock-movements'
    | 'report-valuation'
    | 'report-purchasing-history';

export type GuideField = {
    description: string;
    name: string;
};

export type GuideTutorial = {
    id: string;
    steps: string[];
    title: string;
};

export type GuideControl = {
    description: string;
    label: string;
};

export type GuideNote = {
    description: string;
    title: string;
};

export type GuideTroubleshootingQuestion = {
    answer: string;
    question: string;
};

export type GuideModulePage = {
    controls?: GuideControl[];
    fields?: GuideField[];
    id: string;
    notes?: GuideNote[];
    summary: string;
    title: string;
    troubleshooting?: GuideTroubleshootingQuestion[];
    tutorials?: GuideTutorial[];
    whatHappensNext?: string[];
    whenToUse?: string;
};

export type GuideModule = {
    accessNote?: string;
    actions: GuideActionKey[];
    description: string;
    fields: GuideField[];
    keywords: string[];
    navigationLabels: string[];
    overview: string;
    pages?: GuideModulePage[];
    slug: GuideModuleSlug;
    title: string;
    troubleshooting: string[];
    tutorials: GuideTutorial[];
};
