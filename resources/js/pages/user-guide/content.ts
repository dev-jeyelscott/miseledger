import AiAssistantController from '@/actions/App/Http/Controllers/Ai/AiAssistantController';
import OrganizationBillingController from '@/actions/App/Http/Controllers/Billing/OrganizationBillingController';
import InventoryItemController from '@/actions/App/Http/Controllers/Inventory/InventoryItemController';
import StockCountController from '@/actions/App/Http/Controllers/Inventory/StockCountController';
import StockTransferController from '@/actions/App/Http/Controllers/Inventory/StockTransferController';
import WasteController from '@/actions/App/Http/Controllers/Inventory/WasteController';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import OrganizationLocationController from '@/actions/App/Http/Controllers/OrganizationLocationController';
import OrganizationMemberController from '@/actions/App/Http/Controllers/OrganizationMemberController';
import GoodsReceiptController from '@/actions/App/Http/Controllers/Purchasing/GoodsReceiptController';
import PurchaseOrderController from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import RecipeController from '@/actions/App/Http/Controllers/Recipes/RecipeController';
import SupplierController from '@/actions/App/Http/Controllers/Suppliers/SupplierController';
import { dashboard } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';
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

type GuideAction = {
    href: (context: GuideAccessContext) => string | null;
    isAvailable: (context: GuideAccessContext) => boolean;
    label: string;
};

type GuideField = {
    description: string;
    name: string;
};

type GuideTutorial = {
    id: string;
    steps: string[];
    title: string;
};

export type GuideModule = {
    actions: GuideAction[];
    description: string;
    fields: GuideField[];
    overview: string;
    slug: GuideModuleSlug;
    title: string;
    troubleshooting: string[];
    tutorials: GuideTutorial[];
};

/** Maps every sidebar destination to the guide module that explains it. */
export const documentedNavigationLabels: Record<string, GuideModuleSlug> = {
    'AI Assistant': 'ai-assistant',
    Billing: 'billing',
    Brands: 'inventory',
    Categories: 'inventory',
    Dashboard: 'dashboard',
    'Inventory valuation': 'reports',
    Items: 'inventory',
    'Low stock': 'reports',
    Locations: 'organization',
    Members: 'organization',
    'Opening balances': 'inventory',
    'Product families': 'inventory',
    'Purchase orders': 'purchasing',
    'Purchasing history': 'reports',
    Recipes: 'recipes',
    Receiving: 'purchasing',
    Settings: 'organization',
    'Stock adjustments': 'inventory',
    'Stock counts': 'stock-counts',
    'Stock movement ledger': 'reports',
    'Stock on hand': 'reports',
    'Stock transfers': 'stock-transfers',
    Suppliers: 'purchasing',
    'Units of measure': 'inventory',
    Waste: 'waste',
};

const hasAnyPermission = (
    context: GuideAccessContext,
    permissions: OrganizationPermission[],
): boolean =>
    permissions.some((permission) => context.hasPermission(permission));

export const guideModules: GuideModule[] = [
    {
        slug: 'getting-started',
        title: 'Getting started',
        description:
            'Set up your organization and establish a reliable inventory workflow.',
        overview:
            'MiseLedger keeps stock history and current balances in sync. Start with your organization, locations, units, and item catalog before recording day-to-day activity.',
        tutorials: [
            {
                id: 'prepare-your-workspace',
                title: 'Prepare your workspace',
                steps: [
                    'Create or switch to the organization you will manage.',
                    'Confirm the timezone and currency in Organization settings.',
                    'Add locations, then build units of measure and inventory items.',
                    'Record opening balances before processing purchases, transfers, counts, or waste.',
                ],
            },
        ],
        fields: [
            {
                name: 'Organization',
                description:
                    'The business context that owns members, stock, and billing.',
            },
            {
                name: 'Location',
                description:
                    'A physical site where inventory is stored or used.',
            },
            {
                name: 'Base unit',
                description:
                    'The unit used for authoritative stock quantities and costing.',
            },
        ],
        troubleshooting: [
            'If a feature is not visible, confirm you selected the right organization and have the required permission.',
            'Do not use opening balances to correct later mistakes. Use the appropriate stock adjustment, count, transfer, or waste workflow instead.',
        ],
        actions: [
            {
                label: 'Open dashboard',
                isAvailable: () => true,
                href: () => dashboard().url,
            },
        ],
    },
    {
        slug: 'dashboard',
        title: 'Dashboard',
        description:
            'Review the operational picture for your active organization.',
        overview:
            'The dashboard is the starting point for organization-aware work. Its summaries are based on the active organization, so use the organization switcher before interpreting the data.',
        tutorials: [
            {
                id: 'review-your-dashboard',
                title: 'Review your dashboard',
                steps: [
                    'Use the organization switcher to select the organization you want to review.',
                    'Review the available operational summaries and follow linked workflows for details.',
                    'Return here after stock activity to orient yourself before the next task.',
                ],
            },
        ],
        fields: [
            {
                name: 'Active organization',
                description:
                    'The organization whose data is currently displayed.',
            },
            {
                name: 'Operational summary',
                description:
                    'A current view of the data available to your role.',
            },
        ],
        troubleshooting: [
            'If information looks unexpected, first verify the active organization.',
            'Dashboard visibility follows your organization membership and permissions.',
        ],
        actions: [
            {
                label: 'Open dashboard',
                isAvailable: () => true,
                href: () => dashboard().url,
            },
        ],
    },
    {
        slug: 'ai-assistant',
        title: 'AI Assistant',
        description:
            'Ask context-aware questions about the active organization.',
        overview:
            'The AI Assistant uses the current organization context. It is available only when your organization plan and individual member access permit it.',
        tutorials: [
            {
                id: 'ask-a-question',
                title: 'Ask a useful question',
                steps: [
                    'Confirm that the active organization is the one you intend to ask about.',
                    'Open AI Assistant and describe the question with relevant dates, items, or locations.',
                    'Review the response and use the linked operational pages to verify or act on the result.',
                ],
            },
        ],
        fields: [
            {
                name: 'Active organization',
                description:
                    'The organization context used for the conversation.',
            },
            {
                name: 'Question',
                description:
                    'The operational question or task you want help with.',
            },
        ],
        troubleshooting: [
            'If AI Assistant is unavailable, ask an organization administrator to check plan entitlement and member access.',
            'Switch organizations before starting a new question when the subject belongs to a different business.',
        ],
        actions: [
            {
                label: 'Open AI Assistant',
                isAvailable: (context) => context.aiCanUse,
                href: () => AiAssistantController.index().url,
            },
        ],
    },
    {
        slug: 'inventory',
        title: 'Inventory',
        description:
            'Maintain the item catalog and establish accurate starting quantities.',
        overview:
            'Inventory items, categories, brands, product families, and units of measure are the master data behind stock activity. Changes to quantities must use a ledger-safe workflow.',
        tutorials: [
            {
                id: 'create-an-inventory-item',
                title: 'Create an inventory item',
                steps: [
                    'Add the category, brand, product family, and units needed by the item.',
                    'Create the item with its identifying details and base unit of measure.',
                    'Use opening balances only when establishing the initial on-hand quantity.',
                    'Use stock adjustments for later corrections rather than editing balances directly.',
                ],
            },
        ],
        fields: [
            {
                name: 'Name and SKU',
                description:
                    'The human-readable and operational identifiers for an item.',
            },
            {
                name: 'Item type',
                description:
                    'Classifies the item for inventory and recipe workflows.',
            },
            {
                name: 'Base unit of measure',
                description:
                    'The authoritative unit for stock and valuation calculations.',
            },
        ],
        troubleshooting: [
            'If an item cannot change product family, reconcile its saved option values as part of the same update.',
            'If a quantity needs correction, use a stock adjustment or count workflow instead of changing stock directly.',
        ],
        actions: [
            {
                label: 'Open inventory items',
                isAvailable: (context) =>
                    context.hasPermission('inventory.view'),
                href: () => InventoryItemController.index().url,
            },
        ],
    },
    {
        slug: 'stock-counts',
        title: 'Stock counts',
        description:
            'Count on-hand stock and finalize approved variances safely.',
        overview:
            'Stock counts compare physical inventory with the derived balance. Finalizing a count records the required stock movement, so review quantities carefully before completing it.',
        tutorials: [
            {
                id: 'complete-a-stock-count',
                title: 'Complete a stock count',
                steps: [
                    'Create a count for the relevant location.',
                    'Record the physical quantities in the count.',
                    'Review the variance and resolve obvious entry mistakes.',
                    'Finalize only when the count is ready to create its authoritative adjustment.',
                ],
            },
        ],
        fields: [
            {
                name: 'Location',
                description: 'The site whose stock is being counted.',
            },
            {
                name: 'Counted quantity',
                description:
                    'The physical quantity observed in the item base unit.',
            },
            {
                name: 'Variance',
                description:
                    'The difference between the count and the projected balance.',
            },
        ],
        troubleshooting: [
            'If finalization is unavailable, you may need count-finalization permission.',
            'Recheck the selected location and base-unit quantity before finalizing a variance.',
        ],
        actions: [
            {
                label: 'Open stock counts',
                isAvailable: (context) =>
                    hasAnyPermission(context, [
                        'counts.create',
                        'counts.finalize',
                        'reports.view',
                    ]),
                href: () => StockCountController.index().url,
            },
        ],
    },
    {
        slug: 'waste',
        title: 'Waste',
        description:
            'Record inventory lost through spoilage, damage, or other approved reasons.',
        overview:
            'Waste is a typed stock movement. Recording it preserves the reason, quantity, location, actor, and audit history while updating the derived balance.',
        tutorials: [
            {
                id: 'record-waste',
                title: 'Record waste',
                steps: [
                    'Choose the organization location and the affected inventory item.',
                    'Enter the quantity in the item base unit and select the accurate reason.',
                    'Review the occurrence time and submit the record.',
                    'Use reports to review trends instead of rewriting historical waste entries.',
                ],
            },
        ],
        fields: [
            { name: 'Item', description: 'The stock item that was lost.' },
            {
                name: 'Quantity',
                description: 'The amount lost, recorded in the item base unit.',
            },
            {
                name: 'Waste reason',
                description: 'The auditable reason for the loss.',
            },
        ],
        troubleshooting: [
            'If recording would make stock negative, verify the quantity, location, and earlier stock activity.',
            'Use a truthful waste reason so reports remain useful for operational decisions.',
        ],
        actions: [
            {
                label: 'Open waste',
                isAvailable: (context) =>
                    hasAnyPermission(context, ['waste.record', 'reports.view']),
                href: () => WasteController.index().url,
            },
        ],
    },
    {
        slug: 'stock-transfers',
        title: 'Stock transfers',
        description:
            'Move stock between locations with clear shipment and receipt stages.',
        overview:
            'Transfers maintain location-level accuracy. The sending and receiving steps are distinct, so stock is not treated as available at the destination until it is received.',
        tutorials: [
            {
                id: 'transfer-stock-between-locations',
                title: 'Transfer stock between locations',
                steps: [
                    'Create a transfer with the source, destination, and base-unit quantities.',
                    'Ship the transfer when the stock leaves the source location.',
                    'Receive the transfer at the destination after the physical handoff is complete.',
                    'Review transfer history for exceptions or outstanding stock.',
                ],
            },
        ],
        fields: [
            {
                name: 'From and to locations',
                description:
                    'The source and destination locations for the movement.',
            },
            {
                name: 'Transfer quantity',
                description: 'The amount moved in the item base unit.',
            },
            {
                name: 'Status',
                description:
                    'The workflow stage, such as draft, shipped, or received.',
            },
        ],
        troubleshooting: [
            'If you cannot ship or receive, confirm you have the corresponding transfer permission.',
            'Do not create a second transfer to correct an in-progress one until you review its status and physical stock.',
        ],
        actions: [
            {
                label: 'Open stock transfers',
                isAvailable: (context) =>
                    hasAnyPermission(context, [
                        'transfers.create',
                        'transfers.ship',
                        'transfers.receive',
                        'reports.view',
                    ]),
                href: () => StockTransferController.index().url,
            },
        ],
    },
    {
        slug: 'purchasing',
        title: 'Purchasing',
        description: 'Manage suppliers, purchase orders, and goods receipts.',
        overview:
            'Purchasing records the intention to buy and the actual receipt of stock separately. Inventory increases through goods receiving, using the established inbound cost and stock-ledger controls.',
        tutorials: [
            {
                id: 'receive-a-purchase-order',
                title: 'Receive a purchase order',
                steps: [
                    'Create or select the supplier and prepare a purchase order.',
                    'Review ordered quantities and expected costs before approval.',
                    'Create a goods receipt only for stock physically received.',
                    'Finalize the receipt after validating quantities and costs.',
                ],
            },
        ],
        fields: [
            {
                name: 'Supplier',
                description: 'The business providing the goods.',
            },
            {
                name: 'Purchase order',
                description:
                    'The document that records intended purchases and quantities.',
            },
            {
                name: 'Goods receipt',
                description:
                    'The record that confirms received stock and inbound cost.',
            },
        ],
        troubleshooting: [
            'If purchasing is unavailable, your plan may not include the feature or you may lack purchasing permission.',
            'Do not finalize a receipt for goods that are still pending delivery.',
        ],
        actions: [
            {
                label: 'Open purchase orders',
                isAvailable: (context) =>
                    context.hasPermission('purchasing.view') &&
                    context.hasFeature('purchasing'),
                href: () => PurchaseOrderController.index().url,
            },
            {
                label: 'Open suppliers',
                isAvailable: (context) =>
                    context.hasPermission('purchasing.view') &&
                    context.hasFeature('purchasing'),
                href: () => SupplierController.index().url,
            },
            {
                label: 'Open receiving',
                isAvailable: (context) =>
                    context.hasPermission('purchasing.view') &&
                    context.hasFeature('purchasing'),
                href: () => GoodsReceiptController.index().url,
            },
        ],
    },
    {
        slug: 'recipes',
        title: 'Recipes',
        description:
            'Define recipe ingredients and understand their calculated cost.',
        overview:
            'Recipes bring item quantities together for production planning and costing. Use the correct inventory items and base-unit conversions so calculated costs remain meaningful.',
        tutorials: [
            {
                id: 'build-a-recipe',
                title: 'Build a recipe',
                steps: [
                    'Create the recipe and add the yield information.',
                    'Add each ingredient with its required quantity and unit.',
                    'Review the calculated cost and update ingredient data only through the correct master-data workflow.',
                    'Revisit the recipe after material changes to ingredients or yields.',
                ],
            },
        ],
        fields: [
            {
                name: 'Yield',
                description: 'The output quantity the recipe produces.',
            },
            {
                name: 'Ingredient',
                description: 'An inventory item consumed by the recipe.',
            },
            {
                name: 'Ingredient quantity',
                description:
                    'The amount of an ingredient needed for the yield.',
            },
        ],
        troubleshooting: [
            'If recipes are unavailable, confirm recipe entitlement and recipe-view permission.',
            'Unexpected costs usually require checking the ingredient, quantity, unit conversion, and current inventory cost.',
        ],
        actions: [
            {
                label: 'Open recipes',
                isAvailable: (context) =>
                    context.hasPermission('recipes.view') &&
                    context.hasFeature('recipes'),
                href: () => RecipeController.index().url,
            },
        ],
    },
    {
        slug: 'reports',
        title: 'Reports',
        description:
            'Inspect stock, movement history, valuation, and purchasing activity.',
        overview:
            'Reports read from the authoritative stock ledger and its balance projections. Use filters to narrow the organization, location, item, and date context before drawing conclusions.',
        tutorials: [
            {
                id: 'investigate-stock-activity',
                title: 'Investigate stock activity',
                steps: [
                    'Start with Stock on hand to understand current location quantities.',
                    'Use Stock movement ledger to trace why a quantity changed.',
                    'Use Inventory valuation or Purchasing history when cost and procurement context is needed.',
                    'Adjust filters rather than relying on a broad, unreviewed result set.',
                ],
            },
        ],
        fields: [
            {
                name: 'Location and item filters',
                description: 'Narrow results to the business context you need.',
            },
            {
                name: 'Date range',
                description:
                    'Limits historical reports to the period under review.',
            },
            {
                name: 'Movement reference',
                description:
                    'Identifies the workflow that created a ledger event.',
            },
        ],
        troubleshooting: [
            'If a report is empty, check the active organization, filters, and whether the selected period contains activity.',
            'Use the ledger report to investigate a balance before attempting a corrective workflow.',
        ],
        actions: [],
    },
    {
        slug: 'organization',
        title: 'Organization',
        description: 'Manage organization identity, locations, and members.',
        overview:
            'Organization settings establish the context for all operational work. Permissions are assigned through membership and must be reviewed whenever a person’s responsibilities change.',
        tutorials: [
            {
                id: 'manage-organization-access',
                title: 'Manage organization access',
                steps: [
                    'Select the correct active organization before making an administrative change.',
                    'Invite or manage members according to their operational responsibilities.',
                    'Configure locations before recording location-specific inventory activity.',
                    'Keep organization timezone, currency, and active status accurate.',
                ],
            },
        ],
        fields: [
            {
                name: 'Organization name and slug',
                description:
                    'The public and system identifiers for the organization.',
            },
            {
                name: 'Timezone and currency',
                description:
                    'Defaults used for business dates and monetary presentation.',
            },
            {
                name: 'Member role and permissions',
                description:
                    'The server-authoritative access granted to a member.',
            },
        ],
        troubleshooting: [
            'If a location or member page is unavailable, ask an administrator to review your permissions and feature access.',
            'Changing organization activity does not replace commercial subscription access decisions.',
        ],
        actions: [
            {
                label: 'Open organization settings',
                isAvailable: (context) =>
                    context.activeOrganizationId !== null &&
                    context.hasPermission('organization.manage'),
                href: (context) =>
                    context.activeOrganizationId === null
                        ? null
                        : OrganizationController.edit(
                              context.activeOrganizationId,
                          ).url,
            },
            {
                label: 'Open locations',
                isAvailable: (context) =>
                    context.activeOrganizationId !== null &&
                    context.hasPermission('locations.manage') &&
                    context.hasFeature('locations.multi'),
                href: (context) =>
                    context.activeOrganizationId === null
                        ? null
                        : OrganizationLocationController.index(
                              context.activeOrganizationId,
                          ).url,
            },
            {
                label: 'Open members',
                isAvailable: (context) =>
                    context.activeOrganizationId !== null &&
                    context.hasPermission('users.manage'),
                href: (context) =>
                    context.activeOrganizationId === null
                        ? null
                        : OrganizationMemberController.index(
                              context.activeOrganizationId,
                          ).url,
            },
        ],
    },
    {
        slug: 'billing',
        title: 'Billing',
        description:
            'Review the organization subscription, plan access, and billing recovery options.',
        overview:
            'Billing controls commercial access, while organization activity controls a separate administrative state. Billing changes never alter stock history, balances, or valuation.',
        tutorials: [
            {
                id: 'review-commercial-access',
                title: 'Review commercial access',
                steps: [
                    'Open Billing for the active organization.',
                    'Review the current plan, subscription status, and available feature access.',
                    'Use the provided billing management flow for plan changes or payment recovery.',
                    'Wait for the validated provider settlement path before treating a payment as confirmed.',
                ],
            },
        ],
        fields: [
            {
                name: 'Plan',
                description:
                    'The stable internal plan assigned to the organization.',
            },
            {
                name: 'Subscription status',
                description: 'The current commercial lifecycle status.',
            },
            {
                name: 'Access mode',
                description:
                    'Whether normal business writes are available or read-only.',
            },
        ],
        troubleshooting: [
            'If a feature is read-only, use Billing to review recovery options. Historical records remain available.',
            'A browser success page, QR scan, or pending invoice is not payment confirmation on its own.',
        ],
        actions: [
            {
                label: 'Open billing',
                isAvailable: (context) =>
                    context.activeOrganizationId !== null &&
                    context.hasPermission('billing.manage'),
                href: (context) =>
                    context.activeOrganizationId === null
                        ? null
                        : OrganizationBillingController.show(
                              context.activeOrganizationId,
                          ).url,
            },
        ],
    },
    {
        slug: 'settings',
        title: 'Settings',
        description: 'Maintain your profile, password, and sign-in security.',
        overview:
            'Personal settings apply to your user account, while organization settings apply to the active organization. Keep account security current without sharing credentials or recovery codes.',
        tutorials: [
            {
                id: 'secure-your-account',
                title: 'Secure your account',
                steps: [
                    'Review your profile name and email address.',
                    'Use a strong, unique password and update it when needed.',
                    'Enable two-factor authentication or passkeys when available to your account.',
                    'Store recovery codes securely and never share them.',
                ],
            },
        ],
        fields: [
            {
                name: 'Profile',
                description: 'Your account name and email address.',
            },
            {
                name: 'Password',
                description: 'The credential used to sign in to your account.',
            },
            {
                name: 'Two-factor authentication',
                description: 'An additional account verification method.',
            },
        ],
        troubleshooting: [
            'If you lose access to two-factor authentication, use your recovery path or contact an authorized administrator.',
            'Use Organization settings, not personal Settings, when changing shared business information.',
        ],
        actions: [
            {
                label: 'Open profile settings',
                isAvailable: () => true,
                href: () => profileEdit().url,
            },
        ],
    },
];

export const guideModulesBySlug = Object.fromEntries(
    guideModules.map((module) => [module.slug, module]),
) as Record<GuideModuleSlug, GuideModule>;
