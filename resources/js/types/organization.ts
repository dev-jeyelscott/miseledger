export type OrganizationPermission =
    | 'inventory.view'
    | 'inventory.adjust'
    | 'purchasing.view'
    | 'purchasing.manage'
    | 'receiving.finalize'
    | 'counts.create'
    | 'counts.finalize'
    | 'waste.record'
    | 'transfers.create'
    | 'transfers.ship'
    | 'transfers.receive'
    | 'recipes.view'
    | 'recipes.manage'
    | 'reports.view'
    | 'costs.view'
    | 'locations.manage'
    | 'users.manage'
    | 'organization.manage'
    | 'billing.manage';

export type OrganizationRole =
    'owner' | 'manager' | 'inventory_staff' | 'kitchen_staff' | 'auditor';

export type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

export type LocationSummary = {
    id: number;
    name: string;
    code: string;
    active: boolean;
};

export type StorageLocationSummary = {
    id: number;
    name: string;
    code: string;
    active: boolean;
};

export type OrganizationMembership = {
    organization: OrganizationSummary;
    role: OrganizationRole;
    permissions: OrganizationPermission[];
};

export type OrganizationSubscriptionContext = {
    plan: string | null;
    status: string | null;
    accessMode: 'writable' | 'read_only';
    onTrial: boolean;
    trialEndsAt: string | null;
    endsAt: string | null;
    billingWarning: boolean;
};

export type FeatureCode =
    | 'purchasing'
    | 'recipes'
    | 'reports.export'
    | 'locations.multi';

export type OrganizationEntitlementContext = {
    features: string[];
    limits: Record<string, number | null>;
    grants: Record<FeatureCode, boolean>;
};

export type OrganizationAvailablePlan = {
    code: string;
    name: string;
    monthly: boolean;
    yearly: boolean;
};

export type OrganizationContext = {
    active: OrganizationSummary | null;
    memberships: OrganizationMembership[];
    subscription: OrganizationSubscriptionContext | null;
    entitlements: OrganizationEntitlementContext | null;
};
