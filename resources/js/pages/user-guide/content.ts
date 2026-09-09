import type { GuideModule, GuideModuleSlug } from '@/user-guide/types';

export type {
    GuideAccessContext,
    GuideModule,
    GuideModuleSlug,
} from '@/user-guide/types';

export const guideModules: GuideModule[] = [
    {
        slug: 'getting-started',
        title: 'Getting started',
        description:
            'Learn the basics before you start working with inventory.',
        overview:
            'Use this guide to sign in, choose the right organization, and set up the essentials in a sensible order.',
        keywords: [
            'setup',
            'organization',
            'location',
            'opening balance',
            'sign in',
        ],
        navigationLabels: [],
        pages: [
            {
                id: 'welcome-to-miseledger',
                title: 'Welcome to MiseLedger',
                summary:
                    'MiseLedger helps your team keep track of inventory across the organizations you belong to.',
                whenToUse:
                    'Read this first when you are new to MiseLedger or joining another organization.',
                controls: [
                    {
                        label: 'User Guide',
                        description:
                            'Open it from your account menu whenever you need help with a task.',
                    },
                ],
                whatHappensNext: [
                    'Sign in to your account.',
                    'Choose the organization you want to work in.',
                ],
            },
            {
                id: 'sign-in-and-account-basics',
                title: 'Sign in and account basics',
                summary:
                    'Use your email address and password to sign in. Keep your profile and security details up to date from the account menu.',
                whenToUse:
                    'Use this when you are signing in for the first time or need to update your own account details.',
                controls: [
                    {
                        label: 'Profile settings',
                        description:
                            'Update your personal account details from the account menu.',
                    },
                    {
                        label: 'Security settings',
                        description:
                            'Manage your password and available sign-in security options.',
                    },
                ],
                troubleshooting: [
                    {
                        question: 'I cannot sign in. What should I do?',
                        answer: 'Check your email address and password, then use the password reset option if you need a new password.',
                    },
                ],
            },
            {
                id: 'organization-selection',
                title: 'Choose the right organization',
                summary:
                    'The organization switcher shows the business you are currently viewing. Its name appears in the sidebar.',
                whenToUse:
                    'Check this before reading the dashboard, adding records, or reviewing reports.',
                controls: [
                    {
                        label: 'Switch organization',
                        description:
                            'Choose another organization that you belong to when you need to work with its information.',
                    },
                ],
                whatHappensNext: [
                    'The Dashboard and available menu items update for the organization you selected.',
                    'Your access can be different in each organization.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not find an organization?',
                        answer: 'Ask an organization owner to invite you, or check that you signed in with the account that was invited.',
                    },
                ],
            },
            {
                id: 'plans-and-access',
                title: 'Plans and access',
                summary:
                    'Your organization plan and your access level can affect which features and actions you see.',
                whenToUse:
                    'Read this when a feature or action is not available to you.',
                notes: [
                    {
                        title: 'Good to know',
                        description:
                            'You can still read the User Guide even when a feature is not available in your current organization.',
                    },
                ],
                troubleshooting: [
                    {
                        question: 'Why is an action missing or unavailable?',
                        answer: 'Make sure you selected the right organization. If it is still unavailable, ask an organization owner about your access level or the organization plan.',
                    },
                ],
            },
            {
                id: 'recommended-setup-order',
                title: 'Set up your workspace',
                summary:
                    'Set up the information your team needs before recording day-to-day inventory activity.',
                whenToUse:
                    'Use this order when you are setting up a new organization.',
                tutorials: [
                    {
                        id: 'prepare-your-workspace',
                        title: 'Prepare your workspace',
                        steps: [
                            'Open Organization settings and confirm the timezone and currency.',
                            'Add Locations where your team stores or uses stock.',
                            'Add Units of measure, then create your inventory items.',
                            'Record Opening balances before processing purchases, transfers, counts, or waste.',
                        ],
                    },
                ],
                fields: [
                    {
                        name: 'Organization',
                        description:
                            'The workspace for one business and its team.',
                    },
                    {
                        name: 'Location',
                        description:
                            'A place where inventory is stored or used.',
                    },
                    {
                        name: 'Base unit',
                        description:
                            'The main unit used for an inventory item, such as piece, bottle, or kilogram.',
                    },
                ],
                whatHappensNext: [
                    'You can begin recording purchases, stock counts, transfers, and waste when your setup is ready.',
                ],
            },
            {
                id: 'find-help-later',
                title: 'Find help later',
                summary:
                    'The User Guide is always available from your account menu after you sign in.',
                controls: [
                    {
                        label: 'User Guide',
                        description:
                            'Search for a topic or open a guide section from the guide home page.',
                    },
                ],
                whatHappensNext: [
                    'Choose Dashboard to learn how to read your organization overview.',
                ],
            },
        ],
        tutorials: [],
        fields: [],
        troubleshooting: [],
        actions: ['dashboard'],
    },
    {
        slug: 'dashboard',
        title: 'Dashboard',
        description:
            'Review the operational picture for your active organization.',
        overview:
            'Dashboard gives you a quick view of the selected organization. Check the organization name before using any information on the page.',
        keywords: ['overview', 'active organization', 'summary'],
        navigationLabels: ['Dashboard'],
        pages: [
            {
                id: 'dashboard-header-and-actions',
                title: 'Dashboard header and actions',
                summary:
                    'The header shows the selected organization, your role, timezone, and when Dashboard was last updated. It can also show actions such as Receive stock and Create purchase order.',
                whenToUse:
                    'Use the header to confirm your context and refresh the overview before you start work.',
                controls: [
                    {
                        label: 'Refresh',
                        description:
                            'Update the Dashboard information without leaving the page.',
                    },
                    {
                        label: 'Receive stock',
                        description:
                            'Open the receiving workflow when this action is available to you.',
                    },
                    {
                        label: 'Create purchase order',
                        description:
                            'Start a new purchase order when this action is available to you.',
                    },
                ],
            },
            {
                id: 'dashboard-at-a-glance',
                title: 'Dashboard at a glance',
                summary:
                    'Dashboard is your starting point for a quick view of the work that needs attention in the selected organization.',
                whenToUse:
                    'Open Dashboard when you sign in, change organizations, or want to decide what to work on next.',
                whatHappensNext: [
                    'Use a panel or link to open the related list, report, or task.',
                ],
            },
            {
                id: 'active-organization',
                title: 'Active organization',
                summary:
                    'The organization name near the top of Dashboard tells you which organization the information belongs to.',
                whenToUse:
                    'Check it before acting on any number, alert, or task shown on Dashboard.',
                controls: [
                    {
                        label: 'Switch organization',
                        description:
                            'Choose another organization from the sidebar when you need to review its Dashboard.',
                    },
                    {
                        label: 'Organization settings',
                        description:
                            'Open the organization details when you need to review its timezone, currency, or status.',
                    },
                ],
                troubleshooting: [
                    {
                        question:
                            'The Dashboard shows the wrong business. What should I do?',
                        answer: 'Use Switch organization in the sidebar, then check the organization name again before continuing.',
                    },
                ],
            },
            {
                id: 'summary-cards',
                title: 'Summary cards',
                summary:
                    'The cards can show Inventory value, Low-stock items, Open purchase orders, Pending receiving, and Open stock counts when they are available to you.',
                whenToUse:
                    'Use the cards to spot areas that may need attention without opening every section.',
                controls: [
                    {
                        label: 'Inventory value',
                        description:
                            'Shows the current value of the inventory you can review.',
                    },
                    {
                        label: 'Low-stock items',
                        description:
                            'Shows how many items are at or below their low-stock level.',
                    },
                    {
                        label: 'Open purchase orders',
                        description:
                            'Shows purchase orders that are still open.',
                    },
                    {
                        label: 'Pending receiving',
                        description:
                            'Shows receipts that still need to be completed.',
                    },
                    {
                        label: 'Open stock counts',
                        description:
                            'Shows stock counts that are still in progress.',
                    },
                ],
            },
            {
                id: 'alerts-and-pending-work',
                title: 'Alerts and pending work',
                summary:
                    'Low-stock alerts help you notice items that need attention. Pending work lists purchase orders, receipts, and stock counts that are waiting for the next step.',
                whenToUse:
                    'Review these sections at the start of a shift or before planning stock work.',
                controls: [
                    {
                        label: 'Low-stock alerts',
                        description:
                            'Open the low-stock report to review the affected items.',
                    },
                    {
                        label: 'Pending work',
                        description:
                            'Open a listed task to continue the related workflow.',
                    },
                    {
                        label: 'Quick actions',
                        description:
                            'Start a task such as New purchase order, Finalize receipt, Start stock count, or Record waste when it is available to you.',
                    },
                ],
                whatHappensNext: [
                    'Open the linked task or report to review the details before making a change.',
                ],
            },
            {
                id: 'recent-inventory-activity',
                title: 'Recent inventory activity',
                summary:
                    'This section lists recent inventory activity so you can see what changed most recently.',
                whenToUse:
                    'Use it when you want a quick check after inventory work is recorded.',
                controls: [
                    {
                        label: 'View ledger',
                        description:
                            'Open the full history when you need more detail about recent activity.',
                    },
                ],
                notes: [
                    {
                        title: 'No activity yet',
                        description:
                            'This section stays empty until inventory activity has been recorded for the organization.',
                    },
                ],
            },
            {
                id: 'organization-summary',
                title: 'Organization summary',
                summary:
                    'This section shows the number of Locations and Members, your role, the organization currency and timezone, and available subscription details.',
                whenToUse:
                    'Use it when you need a quick reminder of the organization setup and your access.',
                fields: [
                    {
                        name: 'Locations',
                        description:
                            'The number of places set up for this organization.',
                    },
                    {
                        name: 'Members',
                        description:
                            'The number of people who belong to this organization.',
                    },
                    {
                        name: 'Subscription',
                        description:
                            'The current plan and access status when this information is available.',
                    },
                ],
            },
        ],
        tutorials: [],
        fields: [],
        troubleshooting: [],
        actions: ['dashboard'],
    },
    {
        slug: 'ai-assistant',
        title: 'AI Assistant',
        description:
            'Ask context-aware questions about the active organization.',
        overview:
            'The AI Assistant uses the current organization context. It is available only when your organization plan and individual member access permit it.',
        keywords: ['ask', 'question', 'organization context'],
        navigationLabels: ['AI Assistant'],
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
        accessNote:
            'You may not see the AI Assistant if it is not included in your plan or your access level.',
        actions: ['ai-assistant'],
    },
    {
        slug: 'inventory',
        title: 'Inventory',
        description:
            'Maintain the item catalog and establish accurate starting quantities.',
        overview:
            'Inventory items, categories, brands, product families, and units of measure are the master data behind stock activity. Changes to quantities must use a ledger-safe workflow.',
        keywords: ['catalog', 'quantity', 'unit conversion', 'adjustment'],
        navigationLabels: [
            'Items',
            'Categories',
            'Brands',
            'Product families',
            'Units of measure',
            'Opening balances',
            'Stock adjustments',
        ],
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
        accessNote:
            'You may not see some inventory features if your access level does not allow them.',
        actions: ['inventory-items'],
    },
    {
        slug: 'stock-counts',
        title: 'Stock counts',
        description:
            'Count on-hand stock and finalize approved variances safely.',
        overview:
            'Use stock counts to compare a recorded physical count with the system quantity at one storage location. A draft does not change stock. Finalizing a submitted count reconciles the system stock with the recorded physical count.',
        keywords: [
            'physical count',
            'counted quantity',
            'submit',
            'finalize',
            'cancel count',
            'variance report',
            'export CSV',
        ],
        navigationLabels: ['Stock counts'],
        pages: [
            {
                id: 'overview-stock-counts',
                title: 'Stock count status and list',
                summary:
                    'The list shows count number, location, storage location, status, dates, and variance information for completed counts. Filter by search, status, location, storage location, and date range to find a count or focus on counts with a variance.',
                controls: [
                    {
                        label: 'Create stock count',
                        description:
                            'Starts a new Draft count when this action is available to you.',
                    },
                    {
                        label: 'Variance',
                        description:
                            'Opens the finalized count variance report when reporting is available to you.',
                    },
                ],
                notes: [
                    {
                        title: 'Status meaning',
                        description:
                            'Draft can be edited, Submitted is ready for review but locked, Finalized is complete, and Cancelled is closed without changing stock.',
                    },
                ],
            },
            {
                id: 'create-and-edit-stock-counts',
                title: 'Create and edit a stock count',
                summary:
                    'Create a Draft for one location and storage location, then enter the physical quantities you observed. You can save and return to a Draft until it is submitted.',
                fields: [
                    {
                        name: 'Count number',
                        description:
                            'The unique reference used to find this count later.',
                    },
                    {
                        name: 'Location and storage location',
                        description:
                            'The exact place where the physical count was made.',
                    },
                    {
                        name: 'Counted quantity and unit',
                        description:
                            'The physical amount observed for each item. Enter zero when none was found, rather than leaving a line incomplete.',
                    },
                    {
                        name: 'Line notes',
                        description:
                            'Optional context for a quantity that needs explanation during review.',
                    },
                ],
                tutorials: [
                    {
                        id: 'create-a-stock-count',
                        title: 'Create a stock count',
                        steps: [
                            'Select Create stock count from the list.',
                            'Enter the count number, location, and storage location.',
                            'Add each counted item, its observed quantity, and the unit used for the count.',
                            'Create or save the Draft and return to it as needed while it remains a Draft.',
                        ],
                    },
                ],
                whatHappensNext: [
                    'Saving creates or updates a Draft only. Stock does not change yet.',
                    'Only Draft counts can be edited. Recheck the selected storage location and quantities before submitting.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not save the count?',
                        answer: 'Check that the number is unique, the selected location and storage location are active, and every line has one valid item, unit, and non-negative quantity.',
                    },
                    {
                        question: 'Why can I not edit this count?',
                        answer: 'Submitted, Finalized, and Cancelled counts are read-only. Open a Draft to correct it, or use the supported next workflow for the current status.',
                    },
                ],
            },
            {
                id: 'submit-a-stock-count',
                title: 'Submit a stock count for review',
                summary:
                    'Submit freezes the Draft so the physical count can be reviewed before it affects stock.',
                controls: [
                    {
                        label: 'Submit count',
                        description:
                            'Available for a saved Draft with at least one line.',
                    },
                    {
                        label: 'Cancel count',
                        description:
                            'Closes a Draft or Submitted count when it should not continue.',
                    },
                ],
                tutorials: [
                    {
                        id: 'submit-a-stock-count',
                        title: 'Submit a completed count',
                        steps: [
                            'Open the Draft and review every item, unit, quantity, and location.',
                            'Select Submit count.',
                            'Confirm that the count is now Submitted and no longer editable.',
                        ],
                    },
                ],
                whatHappensNext: [
                    'The count becomes Submitted and its lines are locked for review.',
                    'Stock does not change at submission. The next valid actions are Finalize or Cancel.',
                ],
            },
            {
                id: 'review-and-finalize-stock-counts',
                title: 'Review and finalize a stock count',
                summary:
                    'Use the submitted count details to verify physical quantities and the displayed variance before completing the count. Finalization is the stock-affecting step and cannot be reversed from the count screen.',
                controls: [
                    {
                        label: 'Finalize count',
                        description:
                            'Available only for a Submitted count to authorized reviewers.',
                    },
                    {
                        label: 'Cancel count',
                        description:
                            'Closes a Submitted count if it should not be finalized.',
                    },
                ],
                tutorials: [
                    {
                        id: 'finalize-a-count',
                        title: 'Finalize a reviewed count',
                        steps: [
                            'Open the Submitted count and review the location, storage location, quantities, and variance.',
                            'Resolve any obvious data-entry issue before finalizing. Submitted lines cannot be edited.',
                            'Select Finalize count and confirm the finalization dialog.',
                        ],
                    },
                ],
                whatHappensNext: [
                    'The count becomes Finalized and the system stock is reconciled to the physical quantities recorded in the count.',
                    'The final result and variance remain available for review. You cannot edit, cancel, or finalize it again from this workflow.',
                ],
                troubleshooting: [
                    {
                        question: 'Why is Finalize count unavailable?',
                        answer: 'The count must be Submitted, and your access must allow finalization. If the quantities are wrong, do not finalize. Review the count with an authorized teammate and use the supported correction path.',
                    },
                ],
            },
            {
                id: 'cancel-a-stock-count',
                title: 'Cancel a stock count',
                summary:
                    'Cancel a Draft or Submitted count that was created for the wrong place, has unusable evidence, or should not continue.',
                tutorials: [
                    {
                        id: 'cancel-a-stock-count',
                        title: 'Cancel an open count',
                        steps: [
                            'Open the Draft or Submitted count.',
                            'Choose Cancel count and confirm the exact count you are closing.',
                            'Create a new Draft if a replacement count is needed.',
                        ],
                    },
                ],
                whatHappensNext: [
                    'The count becomes Cancelled and remains available as read-only history.',
                    'Cancelling a Draft or Submitted count does not change stock. Finalized counts cannot be cancelled.',
                ],
            },
            {
                id: 'stock-count-variance-report',
                title: 'Stock count variance report and export',
                summary:
                    'The variance report shows finalized count lines for the selected location and date range. It identifies whether the recorded physical quantity was lower, higher, or equal to the system quantity at finalization.',
                controls: [
                    {
                        label: 'Apply filters',
                        description:
                            'Narrows the finalized count lines shown in the report.',
                    },
                    {
                        label: 'Export CSV',
                        description:
                            'Downloads the currently filtered variance evidence when CSV export is included for your organization.',
                    },
                ],
                notes: [
                    {
                        title: 'Cost values',
                        description:
                            'Cost columns appear only for users who are allowed to view them.',
                    },
                ],
                troubleshooting: [
                    {
                        question:
                            'Why is the report empty or Export CSV missing?',
                        answer: 'The report contains finalized counts only. Adjust or reset filters, confirm that relevant counts are finalized, and check whether your access and organization plan include reporting or CSV export.',
                    },
                ],
            },
        ],
        tutorials: [],
        fields: [],
        troubleshooting: [],
        accessNote:
            'You may not see stock counts if your access level does not allow them.',
        actions: ['stock-counts'],
    },
    {
        slug: 'waste',
        title: 'Waste',
        description:
            'Record inventory lost through spoilage, damage, or other approved reasons.',
        overview:
            'Use Waste to record a known operational loss once. Recording is irreversible from this workspace and reduces stock at the selected storage location.',
        keywords: [
            'spoilage',
            'damage',
            'waste reason',
            'record waste',
            'insufficient stock',
            'waste report',
            'waste export',
            'export CSV',
        ],
        navigationLabels: ['Waste'],
        pages: [
            {
                id: 'overview-waste',
                title: 'Record and review waste',
                summary:
                    'The Waste workspace combines a record form with a filterable report. Existing records are retained as operational history, so check the details before recording.',
                controls: [
                    {
                        label: 'Review and record waste',
                        description:
                            'Opens a confirmation step before the loss is recorded.',
                    },
                    {
                        label: 'Waste reasons',
                        description:
                            'Authorized users can maintain the active reasons available on the form.',
                    },
                ],
                notes: [
                    {
                        title: 'No draft status',
                        description:
                            'Waste is recorded as one completed operation. There is no editable Draft, shipment, receipt, or undo action in this workspace.',
                    },
                ],
            },
            {
                id: 'record-waste-details',
                title: 'Record waste',
                summary:
                    'Choose the exact stock location, item, amount, time, and reason for the loss. The available storage locations and units update based on your selections.',
                fields: [
                    {
                        name: 'Location and storage location',
                        description:
                            'Where the loss occurred. Choose the place that actually held the stock.',
                    },
                    {
                        name: 'Inventory item and unit',
                        description:
                            'The lost item and the unit used to measure it.',
                    },
                    {
                        name: 'Reason',
                        description:
                            'The approved operational reason, such as spoilage or damage.',
                    },
                    {
                        name: 'Quantity',
                        description:
                            'The positive amount lost. Decimal quantities are supported where the item uses them.',
                    },
                    {
                        name: 'Occurred at',
                        description:
                            'When the loss happened. The form interprets this time in the organization time zone.',
                    },
                    {
                        name: 'Notes',
                        description:
                            'Optional detail that helps explain the record later.',
                    },
                ],
                tutorials: [
                    {
                        id: 'record-waste',
                        title: 'Record an operational loss',
                        steps: [
                            'Choose the location, storage location, item, and unit that match the physical loss.',
                            'Select the correct active reason, enter the positive quantity, and set when it occurred.',
                            'Select Review and record waste, verify the confirmation details, then confirm the record.',
                        ],
                    },
                ],
                whatHappensNext: [
                    'The waste record is saved as history and stock is reduced at the selected storage location.',
                    'Use the report to analyze the loss. This workspace does not provide an edit or undo action for a recorded waste entry.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not record this waste?',
                        answer: 'Confirm that the location, storage location, item, unit, and reason are active and belong to the active organization. Enter a positive quantity and a valid occurred-at time.',
                    },
                    {
                        question:
                            'Why does the record say there is not enough stock?',
                        answer: 'Do not reduce the quantity just to make the record pass. Recheck the physical location, unit, quantity, and earlier stock activity. If the loss was recorded against the wrong place, use the correct supported workflow after verifying the facts.',
                    },
                ],
            },
            {
                id: 'waste-reasons',
                title: 'Waste reasons',
                summary:
                    'Waste reasons make loss reports useful and consistent. Only active reasons can be selected when recording a new loss.',
                controls: [
                    {
                        label: 'Add reason',
                        description:
                            'Adds a new reason for future waste records when this management action is available to you.',
                    },
                    {
                        label: 'Activate or deactivate reason',
                        description:
                            'Controls whether a retained reason is available for future records without changing existing history.',
                    },
                ],
                troubleshooting: [
                    {
                        question:
                            'Why is the reason list empty or unavailable?',
                        answer: 'An active reason is required before waste can be recorded. Ask an authorized teammate to add or reactivate the appropriate reason.',
                    },
                ],
            },
            {
                id: 'waste-reports-and-export',
                title: 'Waste report and export',
                summary:
                    'Filter finalized waste records by location, category, item, reason, and date range. The report summarizes waste quantity and, when allowed, value by reason, employee, item, and location.',
                controls: [
                    {
                        label: 'Apply filters',
                        description:
                            'Updates the report, summary, breakdowns, and evidence list for the selected filters.',
                    },
                    {
                        label: 'Export CSV',
                        description:
                            'Downloads the currently filtered waste evidence when CSV export is included for your organization.',
                    },
                ],
                notes: [
                    {
                        title: 'Cost values',
                        description:
                            'Waste value and cost details appear only for users who are allowed to view them.',
                    },
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not see the report or export?',
                        answer: 'Recording and reporting can be granted separately. Check your active organization, access level, filters, and whether CSV export is included in the organization plan.',
                    },
                ],
            },
        ],
        tutorials: [],
        fields: [],
        troubleshooting: [],
        accessNote:
            'You may not see waste records if your access level does not allow them.',
        actions: ['waste'],
    },
    {
        slug: 'stock-transfers',
        title: 'Stock transfers',
        description:
            'Move stock between locations with clear shipment and receipt stages.',
        overview:
            'Transfers move stock between storage locations in two stages. A Draft changes nothing. Shipping removes stock from the source. Receiving makes the recorded quantity available at the destination.',
        keywords: [
            'ship',
            'receive',
            'source location',
            'destination location',
            'transfer draft',
            'cancel transfer',
            'in transit',
            'transfer variance',
        ],
        navigationLabels: ['Stock transfers'],
        pages: [
            {
                id: 'overview-stock-transfers',
                title: 'Transfer status and list',
                summary:
                    'The transfer list shows the number, source, destination, status, shipment and receipt dates, and variance count. Filter by search, status, source, destination, and date range to find work that is Draft, awaiting receipt, Received, Cancelled, or has a variance.',
                controls: [
                    {
                        label: 'Create stock transfer',
                        description:
                            'Starts a new Draft when this action is available to you.',
                    },
                    {
                        label: 'Variance',
                        description:
                            'Opens received-transfer discrepancy analysis when reporting is available to you.',
                    },
                ],
                notes: [
                    {
                        title: 'Status meaning',
                        description:
                            'Draft is editable, Shipped means awaiting receipt, Received is complete, and Cancelled is closed without stock leaving the source.',
                    },
                ],
            },
            {
                id: 'create-and-edit-transfer-drafts',
                title: 'Create and edit a transfer draft',
                summary:
                    'Set the source and destination before adding the requested item quantities. A transfer must use different source and destination storage locations.',
                fields: [
                    {
                        name: 'Transfer number',
                        description:
                            'The unique reference used to track the transfer.',
                    },
                    {
                        name: 'Source and destination',
                        description:
                            'The location and storage location the stock is leaving and the location and storage location it is going to.',
                    },
                    {
                        name: 'Lines and requested quantity',
                        description:
                            'Each item to move, its unit, and the positive quantity requested for the transfer.',
                    },
                    {
                        name: 'Notes',
                        description:
                            'Optional handoff context for the transfer.',
                    },
                ],
                tutorials: [
                    {
                        id: 'create-a-transfer-draft',
                        title: 'Create a transfer draft',
                        steps: [
                            'Select Create stock transfer.',
                            'Enter the transfer number, source location and storage location, then destination location and a different destination storage location.',
                            'Add each item, its unit, and the positive quantity requested.',
                            'Create the Draft. Return to save changes while the transfer remains a Draft.',
                        ],
                    },
                    {
                        id: 'edit-transfer-draft',
                        title: 'Edit a transfer draft',
                        steps: [
                            'Open the Draft from the transfer list.',
                            'Correct the source, destination, lines, quantities, or notes, then select Save draft.',
                            'Review the saved Draft before requesting shipment.',
                        ],
                    },
                ],
                whatHappensNext: [
                    'Creating or saving a Draft does not change stock.',
                    'Only Draft transfers can be edited or cancelled. Once shipped, the configuration is read-only.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not create or save the Draft?',
                        answer: 'Check that the number is unique, source and destination storage locations are active and different, and every line has a valid item, unit, and positive quantity.',
                    },
                ],
            },
            {
                id: 'ship-a-stock-transfer',
                title: 'Ship a transfer',
                summary:
                    'Ship only when the listed stock has physically left the source. Review the transfer before confirming shipment because it can no longer be edited or cancelled afterward.',
                controls: [
                    {
                        label: 'Confirm shipment',
                        description:
                            'Completes shipment for a Draft transfer when this action is available to you.',
                    },
                ],
                tutorials: [
                    {
                        id: 'ship-a-transfer',
                        title: 'Ship a transfer from the source',
                        steps: [
                            'Open the Draft and verify the source, destination, items, and requested quantities.',
                            'Select the shipment action and confirm shipment.',
                            'Confirm the status changes to Shipped or Awaiting receipt.',
                        ],
                    },
                ],
                whatHappensNext: [
                    'Stock leaves the source storage location when the transfer is shipped.',
                    'The transfer becomes Shipped and waits for receipt. Its details are read-only and it cannot be cancelled.',
                ],
                troubleshooting: [
                    {
                        question: 'Why is shipment rejected?',
                        answer: 'Confirm the transfer is still a Draft, the source details are correct, and the source has enough stock for every line. Do not create a duplicate transfer to bypass a rejected shipment.',
                    },
                ],
            },
            {
                id: 'receive-a-stock-transfer',
                title: 'Receive a transfer',
                summary:
                    'Receive a Shipped transfer after the physical handoff. Enter the actual received quantity for every line, including zero if none arrived.',
                controls: [
                    {
                        label: 'Review receipt',
                        description:
                            'Shows the confirmation step for the quantities entered at the destination.',
                    },
                    {
                        label: 'Confirm receipt',
                        description:
                            'Completes receipt for all transfer lines when this action is available to you.',
                    },
                ],
                tutorials: [
                    {
                        id: 'receive-a-transfer',
                        title: 'Receive stock at the destination',
                        steps: [
                            'Open the Shipped transfer at the destination and compare the shipment with what arrived.',
                            'Enter the received quantity for every line in the displayed base unit.',
                            'Select Review receipt, verify the destination and quantities, then confirm receipt.',
                        ],
                    },
                ],
                whatHappensNext: [
                    'The transfer becomes Received and the recorded quantities become available at the destination storage location.',
                    'Any difference between shipped and received quantities is retained as transfer variance for review. Received transfers cannot be edited, cancelled, or received again with different quantities.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not confirm receipt?',
                        answer: 'The transfer must be Shipped, and a received quantity is required for every line. Verify the destination, use the displayed base units, and check your access before trying again.',
                    },
                ],
            },
            {
                id: 'cancel-a-stock-transfer',
                title: 'Cancel a transfer draft',
                summary:
                    'Cancel only a Draft that should not move forward, such as one created with the wrong source, destination, or quantities.',
                tutorials: [
                    {
                        id: 'cancel-a-transfer',
                        title: 'Cancel a transfer before shipment',
                        steps: [
                            'Open the Draft transfer.',
                            'Select Cancel transfer and confirm the exact transfer you are closing.',
                            'Create a replacement Draft only after confirming the correct source, destination, and quantities.',
                        ],
                    },
                ],
                whatHappensNext: [
                    'The transfer becomes Cancelled and remains visible as read-only history.',
                    'Cancelling a Draft does not change stock. Shipped and Received transfers cannot be cancelled.',
                ],
            },
            {
                id: 'stock-transfer-variance-report',
                title: 'Stock transfer variance report',
                summary:
                    'The variance report compares quantities shipped from the source with quantities received at the destination. It includes received transfer lines only and classifies a shortage, overage, or exact match.',
                controls: [
                    {
                        label: 'Apply filters',
                        description:
                            'Filters received transfer variance by a location that is either the source or destination and by date range.',
                    },
                ],
                notes: [
                    {
                        title: 'No CSV export on this report',
                        description:
                            'The current transfer variance page provides on-screen analysis only.',
                    },
                ],
                troubleshooting: [
                    {
                        question: 'Why is the variance report empty?',
                        answer: 'Only Received transfers appear. Adjust or reset filters, then confirm that the relevant transfer was received and that the selected location is its source or destination.',
                    },
                ],
            },
        ],
        tutorials: [],
        fields: [],
        troubleshooting: [],
        accessNote:
            'You may not see stock transfers if your access level does not allow them.',
        actions: ['stock-transfers'],
    },
    {
        slug: 'purchasing',
        title: 'Purchasing',
        description: 'Manage suppliers, purchase orders, and goods receipts.',
        overview:
            'Purchasing records the intention to buy and the actual receipt of stock separately. Inventory increases through goods receiving, using the established inbound cost and stock-ledger controls.',
        keywords: [
            'supplier',
            'purchase order',
            'goods receipt',
            'partial receipt',
        ],
        navigationLabels: ['Suppliers', 'Purchase orders', 'Receiving'],
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
        accessNote:
            'You may not see purchasing features if they are not included in your plan or your access level.',
        actions: ['purchase-orders', 'suppliers', 'receiving'],
    },
    {
        slug: 'recipes',
        title: 'Recipes',
        description:
            'Define recipe ingredients and understand their calculated cost.',
        overview:
            'Recipes bring item quantities together for production planning and costing. Use the correct inventory items and base-unit conversions so calculated costs remain meaningful.',
        keywords: ['ingredients', 'yield', 'costing'],
        navigationLabels: ['Recipes'],
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
        accessNote:
            'You may not see recipes if they are not included in your plan or your access level.',
        actions: ['recipes'],
    },
    {
        slug: 'reports',
        title: 'Reports',
        description:
            'Inspect stock, movement history, valuation, and purchasing activity.',
        overview:
            'Reports read from the authoritative stock ledger and its balance projections. Use filters to narrow the organization, location, item, and date context before drawing conclusions.',
        keywords: ['export', 'filters', 'history', 'valuation'],
        navigationLabels: [
            'Stock on hand',
            'Low stock',
            'Stock movement ledger',
            'Inventory valuation',
            'Purchasing history',
        ],
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
        keywords: ['switch organization', 'members', 'locations', 'access'],
        navigationLabels: ['Locations', 'Members', 'Settings'],
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
        accessNote:
            'You may not see organization features if your access level does not allow them.',
        actions: ['organization-settings', 'locations', 'members'],
    },
    {
        slug: 'billing',
        title: 'Billing',
        description:
            'Review the organization subscription, plan access, and billing recovery options.',
        overview:
            'Billing controls commercial access, while organization activity controls a separate administrative state. Billing changes never alter stock history, balances, or valuation.',
        keywords: ['plan', 'subscription', 'read-only', 'payment'],
        navigationLabels: ['Billing'],
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
        accessNote:
            'You may not see billing if your access level does not allow it.',
        actions: ['billing'],
    },
    {
        slug: 'settings',
        title: 'Settings',
        description: 'Maintain your profile, password, and sign-in security.',
        overview:
            'Personal settings apply to your user account, while organization settings apply to the active organization. Keep account security current without sharing credentials or recovery codes.',
        keywords: [
            'profile',
            'password',
            'two-factor authentication',
            'passkeys',
        ],
        navigationLabels: [],
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
        actions: ['profile-settings'],
    },
];

export const guideModulesBySlug = Object.fromEntries(
    guideModules.map((module) => [module.slug, module]),
) as Record<GuideModuleSlug, GuideModule>;
