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
            'The AI Assistant answers questions about your active organization using its current inventory and procurement data. It is available only when your organization plan and individual member access permit it, and its answers are assistance, not authoritative product state.',
        keywords: [
            'ask',
            'question',
            'organization context',
            'conversation',
            'unavailable',
            'verify',
        ],
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
        pages: [
            {
                id: 'opening-the-ai-assistant',
                title: 'Open the AI Assistant',
                summary:
                    'Open AI Assistant from the navigation to start or continue a conversation scoped to your active organization.',
                whenToUse:
                    'Use this when you want a quick answer about the active organization instead of building a report from scratch.',
                controls: [
                    {
                        label: 'AI Assistant',
                        description:
                            'Opens the assistant when it is included in your plan and your member access allows it.',
                    },
                ],
                notes: [
                    {
                        title: 'Conversations are per organization',
                        description:
                            'A conversation reflects the organization that was active when you asked. Switch organizations first if your question is about a different business.',
                    },
                ],
            },
            {
                id: 'asking-useful-questions',
                title: 'Ask useful inventory and procurement questions',
                summary:
                    'The AI Assistant works best with specific, active-organization questions about inventory and procurement, such as current stock, recent movements, or purchasing activity.',
                whenToUse:
                    'Use these as a starting point when deciding how to phrase a question.',
                fields: [
                    {
                        name: 'Example: current stock',
                        description:
                            '"How much of [item] do we have on hand at [location]?"',
                    },
                    {
                        name: 'Example: recent activity',
                        description:
                            '"What stock movements happened for [item] in the last week?"',
                    },
                    {
                        name: 'Example: purchasing status',
                        description:
                            '"Which purchase orders from [supplier] are still outstanding?"',
                    },
                    {
                        name: 'Example: low stock',
                        description:
                            '"Which items are currently out of stock at [location]?"',
                    },
                ],
                notes: [
                    {
                        title: 'Cost questions depend on your access',
                        description:
                            'Cost and value figures are only available to members with cost visibility. Do not expect cost answers if your role does not include it.',
                    },
                ],
            },
            {
                id: 'verifying-ai-answers',
                title: 'Verify answers before acting on them',
                summary:
                    'Treat AI Assistant responses as assistance, not as the authoritative record. Confirm important operational decisions against the relevant MiseLedger page.',
                whenToUse:
                    'Use this before making a decision, such as reordering stock or following up with a supplier, based on an assistant response.',
                tutorials: [
                    {
                        id: 'verify-an-answer',
                        title: 'Verify an AI Assistant answer',
                        steps: [
                            'Note the item, location, supplier, or date range the answer refers to.',
                            'Open the matching report, such as Stock on hand, Stock movement ledger, or Purchasing history.',
                            'Apply the same filters and compare the result to the assistant response before acting on it.',
                        ],
                    },
                ],
                notes: [
                    {
                        title: 'When information is unavailable',
                        description:
                            'If the assistant cannot answer or says information is unavailable, check the relevant report directly or ask an organization administrator. Do not assume the requested activity did not happen.',
                    },
                ],
                troubleshooting: [
                    {
                        question:
                            'The assistant gave an answer that looks wrong. What should I do?',
                        answer: 'Verify it against the matching report page using the same organization, item, location, and date range before relying on it.',
                    },
                    {
                        question: 'Why does the assistant say it cannot help?',
                        answer: 'It may be a question outside active-organization inventory and procurement topics, or the underlying data may be unavailable. Try rephrasing with specific items, locations, or dates, or check the relevant report directly.',
                    },
                ],
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
            'Purchasing keeps supplier details, purchase orders, and goods receipts in separate steps. Record a receipt only after you have checked the goods that actually arrived.',
        keywords: [
            'supplier',
            'supplier item',
            'supplier price',
            'purchase order',
            'approve purchase order',
            'cancel purchase order',
            'goods receipt',
            'partial receipt',
            'complete receipt',
        ],
        navigationLabels: ['Suppliers', 'Purchase orders', 'Receiving'],
        tutorials: [
            {
                id: 'create-and-approve-a-purchase-order',
                title: 'Create and approve a purchase order',
                steps: [
                    'Create or select the supplier, then add an active supplier item mapping and current price for every item you will order.',
                    'Create a Purchase Order, choose the supplier and Location, then add the ordered items and quantities.',
                    'Review the draft carefully. You can still edit a Draft Purchase Order.',
                    'Select Approve purchase order only when the order is ready to receive against.',
                ],
            },
            {
                id: 'receive-part-or-all-of-an-order',
                title: 'Receive part or all of an order',
                steps: [
                    'Open an Approved Purchase Order and select Create goods receipt when goods arrive.',
                    'Enter only the quantities you physically received. Leave undelivered quantities for a later receipt.',
                    'Check the receipt details, including its Location and any visible cost information.',
                    'Finalize the Goods Receipt only after the physical check is complete. Create another receipt later if quantities are still outstanding.',
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
            'If Purchasing is not available, it may not be included for the active organization or your access level may not allow it.',
            'Do not finalize a receipt for goods that are still pending delivery.',
        ],
        accessNote:
            'This guide remains available to everyone. Open links appear only when the active organization and your access allow the destination.',
        actions: ['purchase-orders', 'suppliers', 'receiving'],
        pages: [
            {
                id: 'suppliers',
                title: 'Suppliers',
                summary:
                    'Use Suppliers to keep the businesses you buy from organized for the active organization.',
                whenToUse:
                    'Create a supplier before you create a Purchase Order for that business.',
                controls: [
                    {
                        label: 'Create supplier',
                        description:
                            'Adds a supplier from its own page when you can manage purchasing.',
                    },
                    {
                        label: 'New supplier',
                        description:
                            'Adds a supplier without leaving the Suppliers list when this option is available to you.',
                    },
                ],
                fields: [
                    {
                        name: 'Name and Code',
                        description:
                            'Identify the supplier clearly in lists and Purchase Orders.',
                    },
                    {
                        name: 'Contact name, Email, and Phone',
                        description:
                            'Store the business contact details your team needs when ordering or following up.',
                    },
                    {
                        name: 'Payment terms and Lead time (days)',
                        description:
                            'Record the supplier terms and expected lead time when your team uses them.',
                    },
                    {
                        name: 'Status',
                        description:
                            'Use Active for suppliers you can use in day-to-day purchasing. Keep inactive suppliers for past records without selecting them for new work.',
                    },
                ],
                whatHappensNext: [
                    'Open the supplier to add the inventory items and purchase units you buy from it.',
                ],
            },
            {
                id: 'supplier-items-and-prices',
                title: 'Supplier items and prices',
                summary:
                    'A supplier item connects one supplier to an inventory item, the supplier SKU, and the unit in which you buy it.',
                whenToUse:
                    'Set up supplier items before adding lines to a Purchase Order for that supplier.',
                controls: [
                    {
                        label: 'Add supplier item',
                        description:
                            'Adds an item mapping from the supplier detail page.',
                    },
                    {
                        label: 'Record price',
                        description:
                            'Records a new supplier price when price information is available to you.',
                    },
                ],
                fields: [
                    {
                        name: 'Inventory item',
                        description:
                            'The item your organization receives and uses.',
                    },
                    {
                        name: 'Supplier SKU and Description',
                        description:
                            'The supplier-facing item identifier and optional description for clearer ordering.',
                    },
                    {
                        name: 'Purchase unit and Base quantity',
                        description:
                            'Describe the buying unit and how much it represents for the linked inventory item.',
                    },
                    {
                        name: 'Current price',
                        description:
                            'Shows the supplier-specific price when your access includes cost information. An active supplier item needs a current price before it can be added to a new Purchase Order.',
                    },
                ],
                notes: [
                    {
                        title: 'Cost information may be hidden',
                        description:
                            'You can still work with supplier items when price values are not shown. Ask an organization administrator if you need cost information for your role.',
                    },
                ],
            },
            {
                id: 'purchase-order-basics',
                title: 'Purchase Order lifecycle',
                summary:
                    'A Purchase Order moves from Draft to Approved, then can show Partially received or Received as Goods Receipts are finalized. A cancelled order is closed.',
                whenToUse:
                    'Use the status to understand whether the order can still be edited, received, or needs follow-up.',
                notes: [
                    {
                        title: 'Draft',
                        description:
                            'A Draft Purchase Order can be edited before approval.',
                    },
                    {
                        title: 'Approved',
                        description:
                            'An Approved Purchase Order is ready for Goods Receipts. Its order details are no longer editable.',
                    },
                    {
                        title: 'Partially received and Received',
                        description:
                            'Partially received means some accepted quantities were finalized and quantities remain to be received. Received means the full ordered quantities were accepted and finalized.',
                    },
                    {
                        title: 'Cancelled',
                        description:
                            'A cancelled Purchase Order is closed and cannot be used for new receiving.',
                    },
                ],
            },
            {
                id: 'create-and-edit-a-purchase-order',
                title: 'Create and edit a Purchase Order',
                summary:
                    'Create a Draft Purchase Order to record what you intend to buy before it is approved.',
                controls: [
                    {
                        label: 'Create purchase order',
                        description:
                            'Starts a new Draft Purchase Order when you can manage purchasing.',
                    },
                    {
                        label: 'Save draft',
                        description:
                            'Saves the supplier, Location, dates, notes, and order lines while the Purchase Order is still a Draft.',
                    },
                ],
                fields: [
                    {
                        name: 'Supplier and Location',
                        description:
                            'Choose who will supply the order and where the goods are expected to arrive.',
                    },
                    {
                        name: 'Order date and Expected delivery date',
                        description:
                            'Record when the order was placed and, when known, the expected arrival date.',
                    },
                    {
                        name: 'Order lines',
                        description:
                            'Choose supplier items and enter the ordered quantity for each line.',
                    },
                    {
                        name: 'Notes',
                        description:
                            'Add ordering context your team needs to keep with the Purchase Order.',
                    },
                ],
                whatHappensNext: [
                    'Review the saved Draft Purchase Order, then approve it when it is ready to receive against.',
                ],
            },
            {
                id: 'approve-or-cancel-a-purchase-order',
                title: 'Approve or cancel a Purchase Order',
                summary:
                    'Approval makes a Draft Purchase Order available for receiving. Cancellation closes a Draft or Approved order only while it has no active Goods Receipt.',
                controls: [
                    {
                        label: 'Approve purchase order',
                        description:
                            'Confirms the draft is ready for Goods Receipts. Review all lines first because the order can no longer be edited afterward.',
                    },
                    {
                        label: 'Cancel purchase order',
                        description:
                            'Closes a Draft or Approved order with no active Goods Receipt. Confirm the exact order before cancelling. Partially received and Received orders cannot be cancelled.',
                    },
                ],
                troubleshooting: [
                    {
                        question:
                            'Why is approval or cancellation unavailable?',
                        answer: 'Check the current status. Approval applies to Draft orders. Cancellation applies to Draft or Approved orders with no active Goods Receipt. Your access must also allow the action.',
                    },
                    {
                        question: 'Why can I no longer edit the order?',
                        answer: 'Only Draft Purchase Orders are editable. Review the status and use receiving for an approved order.',
                    },
                ],
            },
            {
                id: 'receiving-overview',
                title: 'Receiving and Goods Receipts',
                summary:
                    'A Goods Receipt records what actually arrived for an Approved or Partially received Purchase Order.',
                whenToUse:
                    'Create a receipt as goods arrive, not when they are ordered or expected.',
                controls: [
                    {
                        label: 'Create goods receipt',
                        description:
                            'Starts a Draft Goods Receipt from an order that can still receive goods.',
                    },
                ],
                fields: [
                    {
                        name: 'Accepted',
                        description:
                            'Enter only the quantity you physically checked and accepted for each item. Accepted quantities are added to inventory when the receipt is finalized.',
                    },
                    {
                        name: 'Location',
                        description:
                            'Confirm the location where the received inventory belongs.',
                    },
                    {
                        name: 'Rejected and Damaged',
                        description:
                            'Record quantities that arrived but were rejected or damaged. These remain on the receipt as evidence and are not added to inventory.',
                    },
                ],
            },
            {
                id: 'partial-and-complete-receipts',
                title: 'Partial and complete receipts',
                summary:
                    'You can receive an order over more than one Goods Receipt. A partial receipt leaves the remaining quantity available for a later delivery.',
                tutorials: [
                    {
                        id: 'record-a-partial-receipt',
                        title: 'Record a partial receipt',
                        steps: [
                            'Create a Goods Receipt from the Approved Purchase Order.',
                            'Enter only the quantity you physically checked and accepted, then save the draft for review.',
                            'Finalize after checking the goods. The Purchase Order shows Partially received when accepted quantities remain outstanding.',
                            'Create another Goods Receipt when the remaining goods arrive.',
                        ],
                    },
                    {
                        id: 'record-a-complete-receipt',
                        title: 'Record a complete receipt',
                        steps: [
                            'Create a Goods Receipt for the final quantities you physically checked and accepted.',
                            'Review each receipt line and finalize only after the physical check is complete.',
                            'The Purchase Order shows Received only when its full ordered quantities have been accepted and finalized.',
                        ],
                    },
                ],
                notes: [
                    {
                        title: 'Do not fill in missing goods',
                        description:
                            'Leave undelivered quantities for a later receipt. This keeps the receipt aligned with what actually arrived.',
                    },
                ],
            },
            {
                id: 'finalize-or-cancel-a-goods-receipt',
                title: 'Finalize or cancel a Goods Receipt',
                summary:
                    'Draft Goods Receipts can be checked and updated. Finalized receipts are locked and add only Accepted quantities to inventory. Cancel a Draft receipt when it should not be used.',
                controls: [
                    {
                        label: 'Finalize receipt',
                        description:
                            'Completes the receipt after quantities and other details have been physically verified.',
                    },
                    {
                        label: 'Cancel receipt',
                        description:
                            'Cancels a Draft Goods Receipt that should not be finalized.',
                    },
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not change a receipt?',
                        answer: 'Only Draft Goods Receipts can be changed. Finalized and cancelled receipts stay locked.',
                    },
                    {
                        question: 'Why can I not finalize a receipt?',
                        answer: 'Check the receipt details, the Purchase Order status, and whether your access allows finalization.',
                    },
                ],
            },
        ],
    },
    {
        slug: 'recipes',
        title: 'Recipes',
        description:
            'Manage recipe details, review version coverage, and understand calculated cost.',
        overview:
            'Recipes keep each recipe identity separate from its formulation history. The list shows version coverage, while Cost uses the current effective published formulation for the Location you select.',
        keywords: [
            'ingredients',
            'yield',
            'costing',
            'recipe version',
            'recipe cost',
        ],
        navigationLabels: ['Recipes'],
        tutorials: [
            {
                id: 'build-a-recipe',
                title: 'Build a recipe',
                steps: [
                    'Create the recipe with its Code, Name, Type, and Status.',
                    'Use the Recipes list to review its latest version number and published and draft version coverage.',
                    'Remember that Yield, Components, quantities, and units belong to a recipe version, not the recipe details page.',
                    'Open Cost when it is available to you, choose a Location, and review the current effective published version and its breakdown.',
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
            'If Recipes is not available, it may not be included for the active organization or your access level may not allow it.',
            'Unexpected costs usually require checking the component, quantity, unit, Location, and current item cost.',
        ],
        accessNote:
            'This guide remains available to everyone. Open links appear only when the active organization and your access allow the destination.',
        actions: ['recipes'],
        pages: [
            {
                id: 'recipes-list',
                title: 'Recipes list',
                summary:
                    'The Recipes list helps you find recipe records and see their Type, Status, and version coverage.',
                whenToUse:
                    'Use filters and search to locate a recipe before reviewing its details or cost.',
                controls: [
                    {
                        label: 'Create recipe',
                        description:
                            'Creates a recipe record when your access allows recipe management.',
                    },
                    {
                        label: 'Edit recipe',
                        description:
                            'Updates the recipe Code, Name, Type, and Status.',
                    },
                    {
                        label: 'Cost',
                        description:
                            'Opens the current recipe cost view when cost information is available to you.',
                    },
                ],
                fields: [
                    {
                        name: 'Type',
                        description:
                            'Classifies the recipe as a Menu item, Prepared item, or Batch.',
                    },
                    {
                        name: 'Status',
                        description:
                            'Shows whether the recipe is Active or Inactive.',
                    },
                    {
                        name: 'Versions',
                        description:
                            'Shows the latest version number and the number of published and draft versions.',
                    },
                ],
            },
            {
                id: 'create-and-edit-recipes',
                title: 'Create and edit recipes',
                summary:
                    'Create the recipe record first, then keep its identifying details accurate as the recipe evolves.',
                fields: [
                    {
                        name: 'Code and Name',
                        description:
                            'Give the recipe a clear, consistent identity for your team.',
                    },
                    {
                        name: 'Type',
                        description:
                            'Choose Menu item, Prepared item, or Batch to match how your organization uses the recipe.',
                    },
                    {
                        name: 'Status',
                        description:
                            'Use Active for recipes in use. Inactive recipes remain available for past reference.',
                    },
                ],
                notes: [
                    {
                        title: 'Recipe details and formulation are separate',
                        description:
                            'The Edit recipe page changes the recipe identity. Yield and Components belong to its recipe versions.',
                    },
                ],
            },
            {
                id: 'recipe-components-and-yield',
                title: 'Recipe components and yield',
                summary:
                    'A recipe version defines the Yield and the Components needed to make that yield. The current Recipes screens show version coverage but do not provide version-composition editing controls.',
                fields: [
                    {
                        name: 'Yield',
                        description:
                            'The output quantity and unit the recipe version produces.',
                    },
                    {
                        name: 'Components',
                        description:
                            'The ingredients or nested recipes required for the yield.',
                    },
                    {
                        name: 'Quantity and unit',
                        description:
                            'The required amount and unit for each component. Review both whenever the formulation changes.',
                    },
                ],
                whatHappensNext: [
                    'Use Cost, when it is available to you, to review the current effective published version for a Location.',
                ],
            },
            {
                id: 'recipe-versions',
                title: 'Recipe versions',
                summary:
                    'Versions preserve how a recipe changes over time. The Recipes list shows draft and published version coverage and the latest version number.',
                notes: [
                    {
                        title: 'Draft and published versions',
                        description:
                            'The Recipes list reports draft and published version coverage. A published version is used by the current recipe cost view only when it is in effect.',
                    },
                    {
                        title: 'What you can edit here',
                        description:
                            'Create recipe and Edit recipe change the recipe Code, Name, Type, and Status. They do not change a version Yield or Components, and the current Recipes screens do not include a version editing or publishing action.',
                    },
                ],
            },
            {
                id: 'recipe-cost',
                title: 'Recipe cost',
                summary:
                    'Cost shows the current breakdown for the effective published recipe version at a selected Location.',
                whenToUse:
                    'Use it to review the current total, component costs, and yield-based result for one Location.',
                controls: [
                    {
                        label: 'Location',
                        description:
                            'Choose the Location whose current item costs you want to review.',
                    },
                    {
                        label: 'Cost breakdown',
                        description:
                            'Shows the recipe total and its components when a published version and Location are available.',
                    },
                ],
                notes: [
                    {
                        title: 'Cost access varies by role',
                        description:
                            'You may be able to view and work with recipes without seeing Cost. Ask an organization administrator if your role needs cost information.',
                    },
                    {
                        title: 'Current, location-specific view',
                        description:
                            'The displayed result reflects the selected Location and current item costs. Choose the Location before comparing costs.',
                    },
                ],
                troubleshooting: [
                    {
                        question: 'Why is there no cost result?',
                        answer: 'Choose a Location and confirm that the recipe has a published version currently in effect.',
                    },
                ],
            },
        ],
    },
    {
        slug: 'reports',
        title: 'Reports',
        description:
            'Inspect stock, movement history, valuation, and purchasing activity.',
        overview:
            'Reports are read-only views for viewing and analysis. They do not edit stock. Stock on hand, Low stock, Stock movement ledger, and Inventory valuation read from the authoritative stock ledger and its balance projections; Purchasing history reads from purchase order and receiving records instead. Use filters to narrow the organization, location, item, and date context before drawing conclusions.',
        keywords: [
            'export',
            'filters',
            'history',
            'valuation',
            'stock on hand',
            'low stock',
            'stock movement ledger',
            'inventory valuation',
            'purchasing history',
            'csv',
        ],
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
        accessNote:
            'This guide remains available to everyone. Open links appear only when your access allows the destination, and cost figures only appear for members with cost visibility.',
        actions: [
            'report-stock-on-hand',
            'report-low-stock',
            'report-stock-movements',
            'report-valuation',
            'report-purchasing-history',
        ],
        pages: [
            {
                id: 'stock-on-hand',
                title: 'Stock on hand',
                summary:
                    'Stock on hand shows current balance quantities for every item across your locations and storage locations, right now.',
                whenToUse:
                    'Use this when you need to know how much of an item you currently have before ordering, transferring, or planning production.',
                fields: [
                    {
                        name: 'Location, Storage location, Category, Item',
                        description:
                            'Narrow the report to the part of the business you need. Combine filters to zero in on a single storage location or item.',
                    },
                ],
                notes: [
                    {
                        title: 'Reading the results',
                        description:
                            'Each row shows the item, its SKU, category, quantity on hand, and unit. Average unit cost and inventory value appear only if your access includes cost visibility.',
                    },
                    {
                        title: 'No matching rows',
                        description:
                            'An empty table usually means the filters are too narrow or the location has no recorded stock yet. Clear a filter at a time to widen the search.',
                    },
                ],
                whatHappensNext: [
                    'Export the current view to CSV when your organization plan and access include report exports.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not export this report?',
                        answer: 'Export is only available when your organization plan includes report exports and your access allows it. Ask an organization administrator if you expect to have it.',
                    },
                ],
            },
            {
                id: 'low-stock',
                title: 'Low stock',
                summary:
                    'Low stock lists balances that are at zero or negative quantity for the active organization, so you can spot items that need attention.',
                whenToUse:
                    'Use this to find items that are out of stock or show a negative balance, which usually points to a data or process issue worth investigating.',
                fields: [
                    {
                        name: 'Location, Storage location, Category, Item, Status',
                        description:
                            'Filter by business context, and use Status to separate items that are exactly out of stock from those showing a negative quantity.',
                    },
                ],
                notes: [
                    {
                        title: 'No reorder thresholds',
                        description:
                            'This report does not use minimum-stock or PAR levels. It only shows items already at zero or below, not items approaching a target level.',
                    },
                    {
                        title: 'No matching rows',
                        description:
                            'An empty result is good news here: it means no balances are currently at zero or negative for the selected filters.',
                    },
                ],
                troubleshooting: [
                    {
                        question:
                            'Why is there no export option on this report?',
                        answer: 'Low stock does not currently offer a CSV export. Use Stock on hand or Stock movement ledger if you need an exportable record.',
                    },
                ],
            },
            {
                id: 'stock-movement-ledger',
                title: 'Stock movement ledger',
                summary:
                    'Stock movement ledger lists every recorded stock event for the active organization in the order it happened, so you can trace why a balance changed.',
                whenToUse:
                    'Use this to investigate a specific balance, confirm a receipt or waste entry was recorded, or reconstruct what happened to an item over a date range.',
                fields: [
                    {
                        name: 'Location, Storage location, Item',
                        description:
                            'Narrow the ledger to the part of the business you are investigating.',
                    },
                    {
                        name: 'Movement type',
                        description:
                            'Filter to a specific kind of event, such as a purchase receipt, waste entry, transfer, count adjustment, manual adjustment, or opening balance.',
                    },
                    {
                        name: 'From and To dates',
                        description:
                            'Limit the ledger to the period under review. The From date cannot be after the To date.',
                    },
                    {
                        name: 'Source / reference',
                        description:
                            'Search by the workflow or record that created the event, such as a receipt or transfer number.',
                    },
                ],
                notes: [
                    {
                        title: 'Reading the results',
                        description:
                            'Each row shows when the event occurred, the location, item, movement type, quantity, and who recorded it. Unit cost and total cost appear only with cost visibility.',
                    },
                    {
                        title: 'No matching rows',
                        description:
                            'If the ledger is empty, check that the date range covers the period you expect and that the location or item filters are not too narrow.',
                    },
                ],
                whatHappensNext: [
                    'Export the filtered ledger to CSV when your organization plan and access include report exports.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not export this report?',
                        answer: 'Export is only available when your organization plan includes report exports and your access allows it.',
                    },
                ],
            },
            {
                id: 'inventory-valuation',
                title: 'Inventory valuation',
                summary:
                    'Inventory valuation shows the current monetary value of stock on hand, grouped by location and category, for members with cost visibility.',
                whenToUse:
                    'Use this when you need to understand how much your current stock is worth, broken down by location or category.',
                fields: [
                    {
                        name: 'Location, Category',
                        description:
                            'Narrow the valuation to a single location or category of items.',
                    },
                ],
                notes: [
                    {
                        title: 'Cost visibility required',
                        description:
                            'Value totals, category totals, and the grand total only appear for members with cost visibility. Without it, quantities still show but monetary figures do not.',
                    },
                    {
                        title: 'No matching rows',
                        description:
                            'An empty result usually means the selected location or category has no recorded stock.',
                    },
                ],
                whatHappensNext: [
                    'Export the current view to CSV when your organization plan and access include report exports.',
                ],
                troubleshooting: [
                    {
                        question: 'Why do I not see any cost or value figures?',
                        answer: 'Cost and value figures require cost visibility access. Ask an organization administrator if you believe your role should include it.',
                    },
                ],
            },
            {
                id: 'purchasing-history',
                title: 'Purchasing history',
                summary:
                    'Purchasing history reports purchase orders and their receiving progress, line by line, so you can review procurement activity over time.',
                whenToUse:
                    'Use this to review what was ordered from a supplier, how much has been received, and what remains outstanding.',
                fields: [
                    {
                        name: 'Supplier, Location',
                        description:
                            'Narrow purchasing activity to a specific supplier or location.',
                    },
                    {
                        name: 'From and To dates',
                        description:
                            'Limit results to purchase orders placed within the selected period.',
                    },
                    {
                        name: 'Search',
                        description:
                            'Search by purchase order number, supplier, item, or supplier SKU.',
                    },
                    {
                        name: 'Receipt state',
                        description:
                            'Filter by whether a line is fully received, partially received, not yet received, or over-received.',
                    },
                ],
                notes: [
                    {
                        title: 'Reading the results',
                        description:
                            'Each row shows one purchase order line, including ordered and received quantities and the receipt state. Unit price and line total appear only with cost visibility.',
                    },
                    {
                        title: 'No matching rows',
                        description:
                            'An empty result usually means no purchase orders match the selected supplier, location, date range, or receipt state.',
                    },
                ],
                whatHappensNext: [
                    'Export the filtered report to CSV when your organization plan and access include report exports.',
                    'Open a purchase order from Purchasing when you have access, to see full order and receiving detail.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not export this report?',
                        answer: 'Export is only available when your organization plan includes report exports and your access allows it.',
                    },
                ],
            },
        ],
    },
    {
        slug: 'organization',
        title: 'Organization',
        description:
            'Create, switch, and configure organizations, and manage locations, storage locations, and members.',
        overview:
            'An organization is the workspace for one business. Use Organization settings, Locations, Storage locations, and Members to set up and maintain that workspace. What you can see and change here depends on the role your organization assigned to you.',
        keywords: [
            'switch organization',
            'create organization',
            'members',
            'role',
            'locations',
            'storage location',
            'storage area',
            'access',
            'timezone',
            'currency',
        ],
        navigationLabels: ['Locations', 'Members', 'Settings'],
        tutorials: [
            {
                id: 'manage-organization-access',
                title: 'Manage organization access',
                steps: [
                    'Select the correct active organization before making an administrative change.',
                    'Add or manage members and give each person the role that matches their responsibilities.',
                    'Add locations and their storage locations before recording location-specific inventory activity.',
                    'Keep organization timezone, currency, and status accurate.',
                ],
            },
        ],
        fields: [
            {
                name: 'Organization name and slug',
                description:
                    'The display name and the unique URL-friendly identifier for the organization.',
            },
            {
                name: 'Timezone and currency',
                description:
                    'Defaults used for business dates and monetary presentation.',
            },
            {
                name: 'Member role',
                description:
                    'The set of actions and pages a member can use in this organization, such as Owner, Manager, Inventory Staff, Kitchen Staff, or Auditor.',
            },
        ],
        troubleshooting: [
            'If a location, storage location, member, or settings page is unavailable, ask an organization owner or manager to review your role.',
            'Changing organization status does not change billing. Review Billing separately for subscription and payment topics.',
        ],
        accessNote:
            'You may not see organization features if your role does not allow them.',
        actions: ['organization-settings', 'locations', 'members'],
        pages: [
            {
                id: 'create-and-switch-organizations',
                title: 'Create and switch organizations',
                summary:
                    'The organization switcher in the sidebar shows the organization you are currently working in. Use it to move between every organization you belong to, or to create a new one.',
                whenToUse:
                    'Use this before starting work, and whenever you need to set up a new, fully separate business.',
                controls: [
                    {
                        label: 'Switch organization',
                        description:
                            'Choose another organization that you belong to when you need to work with its information.',
                    },
                    {
                        label: 'Create organization',
                        description:
                            'Starts a brand-new organization with its own locations, inventory, recipes, and members, kept fully separate from every other organization.',
                    },
                ],
                fields: [
                    {
                        name: 'Organization name',
                        description:
                            'The name shown throughout MiseLedger while creating a new organization. You can invite members and add locations afterward.',
                    },
                ],
                whatHappensNext: [
                    'The Dashboard and available menu items update for the organization you selected.',
                    'A newly created organization starts empty. Add locations, members, and inventory items before recording day-to-day activity.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not find an organization?',
                        answer: 'Ask an organization owner to add you as a member, or confirm you signed in with the account that was added.',
                    },
                ],
            },
            {
                id: 'locations',
                title: 'Locations',
                summary:
                    'A location is one physical site, such as a restaurant branch or warehouse. Each location contains one or more storage locations, the specific shelves, coolers, or rooms where inventory is actually tracked.',
                whenToUse:
                    'Set up locations before recording purchases, transfers, counts, or waste for that site.',
                controls: [
                    {
                        label: 'Add location',
                        description:
                            'Creates a location with a name and code. A default storage location is created for it automatically.',
                    },
                    {
                        label: 'Edit',
                        description:
                            'Updates a location name, code, or status.',
                    },
                    {
                        label: 'Storage',
                        description:
                            'Opens the storage locations that belong to this location.',
                    },
                ],
                fields: [
                    {
                        name: 'Location name and code',
                        description:
                            'The display name and the short code used to identify the location. The code must be unique within the organization and can contain letters, numbers, hyphens, and underscores.',
                    },
                    {
                        name: 'Status',
                        description:
                            'Active locations can be used for new inventory activity. Deactivating a location keeps its storage locations and history but blocks new activity there, and may be blocked while the location is still required by an active workflow such as a shipped transfer awaiting receipt.',
                    },
                ],
                whatHappensNext: [
                    'Open Storage for a new location to review or add the storage locations your team uses inside it.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not deactivate a location?',
                        answer: 'A location may still be required by an active workflow, such as a transfer awaiting receipt. Resolve or complete that workflow first.',
                    },
                ],
            },
            {
                id: 'storage-locations',
                title: 'Storage locations',
                summary:
                    'Storage locations are the specific places inside a location where inventory is stored, such as a walk-in chiller, a dry-storage shelf, or a bar well. Open a location and select Storage to manage them.',
                whenToUse:
                    'Use this when a location needs more than one storage area, or when you need to review or rename an existing storage location.',
                controls: [
                    {
                        label: 'Add storage location',
                        description:
                            'Creates a new storage location inside the selected location, with a name and code that must be unique within that location.',
                    },
                    {
                        label: 'Edit',
                        description:
                            'Updates a storage location name, code, or status.',
                    },
                ],
                fields: [
                    {
                        name: 'Storage location name and code',
                        description:
                            'The display name and the short code used to identify the storage location within its parent location.',
                    },
                    {
                        name: 'Status',
                        description:
                            'New storage locations are active by default. Deactivation may be blocked while a shipped stock transfer is awaiting receipt there.',
                    },
                ],
                whatHappensNext: [
                    'Storage locations you add here become available when recording stock counts, waste, transfers, and receiving for that location.',
                ],
                troubleshooting: [
                    {
                        question:
                            'Why can I not deactivate a storage location?',
                        answer: 'Deactivation may be blocked while a shipped stock transfer is still awaiting receipt at that storage location. Complete or resolve the transfer first.',
                    },
                ],
            },
            {
                id: 'members-and-access',
                title: 'Members and access',
                summary:
                    'Organization members are the registered MiseLedger users who can access this organization. Each member has a role that determines what they can see and do.',
                whenToUse:
                    'Use this when adding a teammate, checking who has access, or reviewing why someone can or cannot use a feature.',
                controls: [
                    {
                        label: 'Add member',
                        description:
                            'Adds an existing registered MiseLedger user to this organization by email and assigns their role.',
                    },
                    {
                        label: 'AI access',
                        description:
                            'Turns the AI Assistant on or off for one member, when this control is available to you. Owners always have it enabled.',
                    },
                ],
                fields: [
                    {
                        name: 'Role',
                        description:
                            'Owner has full access, including billing and organization settings. Manager handles day-to-day purchasing, receiving, counts, waste, transfers, recipes, and reports, including cost figures. Inventory Staff records day-to-day inventory work without cost figures. Kitchen Staff can view inventory, record waste, and view recipes. Auditor has read-only access to inventory, purchasing, recipes, reports, and cost figures for review.',
                    },
                ],
                notes: [
                    {
                        title: 'Only registered users can be added',
                        description:
                            'The person must already have a MiseLedger account before you can add them to an organization.',
                    },
                ],
                whatHappensNext: [
                    'A new member sees this organization the next time they open the organization switcher, using the role you assigned.',
                ],
                troubleshooting: [
                    {
                        question: 'Why can I not add a member?',
                        answer: 'Confirm the person already has a registered MiseLedger account and that you have access to manage members. Check the organization member limit if adding fails.',
                    },
                    {
                        question:
                            'Why can a teammate not see a feature I can see?',
                        answer: 'Compare your assigned roles. Ask an organization owner or manager to review the role assigned to their membership.',
                    },
                ],
            },
            {
                id: 'organization-settings',
                title: 'Organization settings',
                summary:
                    'Organization settings hold the shared configuration used across the whole organization: its name, slug, timezone, currency, and whether it is currently active.',
                whenToUse:
                    'Review this after creating an organization, and whenever the business name, timezone, or currency changes.',
                controls: [
                    {
                        label: 'Save changes',
                        description:
                            'Applies updates to the organization name, slug, timezone, currency, or status.',
                    },
                ],
                fields: [
                    {
                        name: 'Name and Slug',
                        description:
                            'The name shown throughout MiseLedger, and the URL-friendly identifier used in links. The slug accepts letters, numbers, dashes, and underscores.',
                    },
                    {
                        name: 'Timezone',
                        description:
                            'Used to display and interpret operational dates and times, such as when waste or a transfer occurred.',
                    },
                    {
                        name: 'Currency',
                        description:
                            'The default currency used for monetary values across the organization.',
                    },
                    {
                        name: 'Status',
                        description:
                            'Active or Inactive. Deactivating an active organization immediately blocks operational access for its members until it is reactivated. Deactivation does not affect billing.',
                    },
                ],
                whatHappensNext: [
                    'Members see the updated timezone and currency the next time they load pages that display dates or money.',
                ],
                troubleshooting: [
                    {
                        question:
                            'Why is Organization settings unavailable to me?',
                        answer: 'Only members whose role includes organization management can open this page. Ask an organization owner for access if you need it.',
                    },
                ],
            },
        ],
    },
    {
        slug: 'billing',
        title: 'Billing',
        description:
            'Review your organization plan, subscription status, and what to do if payment needs attention.',
        overview:
            'Billing shows the plan and subscription currently assigned to your organization, whether the organization can make normal changes or is read-only, and the actions available to fix a payment problem. Billing never changes stock history, balances, or valuation.',
        keywords: [
            'plan',
            'subscription',
            'trial',
            'active',
            'past due',
            'unpaid',
            'read-only',
            'payment',
            'renew',
            'upgrade',
            'cancel renewal',
            'manage billing',
        ],
        navigationLabels: ['Billing'],
        tutorials: [
            {
                id: 'review-your-plan-and-fix-a-payment-problem',
                title: 'Review your plan and fix a payment problem',
                steps: [
                    'Open Billing for the active organization.',
                    'Review the current plan, subscription status, and whether the organization can make normal changes or is read-only.',
                    'Use Manage billing, Renew subscription, or Cancel renewal, whichever is shown for your subscription.',
                    'Treat a payment as confirmed only once Billing shows it, not from a browser success page or QR scan alone.',
                ],
            },
        ],
        fields: [
            {
                name: 'Plan',
                description:
                    'The plan currently assigned to your organization.',
            },
            {
                name: 'Subscription status',
                description:
                    'Shows Trial, Active, Past due, Unpaid, or a similar status. Trial and Active let your organization make normal changes. Past due keeps normal access for now but shows a payment warning. Unpaid or an ended trial or subscription switches the organization to read-only until payment is resolved.',
            },
            {
                name: 'Access',
                description:
                    'Writable means your organization can make normal changes. Read-only means new purchases, counts, waste, transfers, and other changes are blocked until payment is resolved, while your existing records stay available.',
            },
        ],
        troubleshooting: [
            'If a feature is read-only, open Billing and use the available recovery action. Your existing records remain available while you resolve payment.',
            'A browser success page, QR scan, or pending invoice is not payment confirmation on its own. Wait for Billing to show the payment as received.',
        ],
        accessNote: 'You may not see Billing if your role does not allow it.',
        actions: ['billing'],
        pages: [
            {
                id: 'current-plan-and-subscription',
                title: 'Current plan and subscription',
                summary:
                    'Billing shows the plan, subscription status, access, and, when they apply, the trial end date, renewal or end date, billing interval, and next billing date, all in the organization timezone.',
                whenToUse:
                    'Check this whenever you need to confirm what your organization is subscribed to or when its access will change.',
                fields: [
                    {
                        name: 'Trial ends',
                        description:
                            'Shown while the organization is on a trial. Normal access continues until this date unless a subscription starts first.',
                    },
                    {
                        name: 'Renews or cancels on / Ended on',
                        description:
                            'Shows when a writable subscription will renew or stop renewing, or when a read-only subscription already ended.',
                    },
                    {
                        name: 'Billing interval and Next billing date',
                        description:
                            'Shown for an active recurring subscription, such as monthly or yearly billing and its next charge date.',
                    },
                ],
            },
            {
                id: 'subscription-status-meanings',
                title: 'What each subscription status means',
                summary:
                    'The subscription status explains what your organization can currently do and whether payment needs attention.',
                notes: [
                    {
                        title: 'Trial',
                        description:
                            'Your organization is inside a trial period. Normal changes are available.',
                    },
                    {
                        title: 'Active',
                        description:
                            'Your subscription is paid and current. Normal changes are available.',
                    },
                    {
                        title: 'Past due',
                        description:
                            'A recent payment did not go through, but normal changes remain available for now. Billing shows a payment warning. Resolve payment soon to avoid losing write access.',
                    },
                    {
                        title: 'Unpaid',
                        description:
                            'Payment recovery did not succeed. The organization is read-only until payment is resolved.',
                    },
                    {
                        title: 'Cancelled',
                        description:
                            'The subscription was cancelled and is no longer renewing. The organization is read-only until you subscribe again.',
                    },
                    {
                        title: 'None',
                        description:
                            'There is no active subscription, for example after a trial ends without one starting. The organization is read-only until you subscribe.',
                    },
                ],
                troubleshooting: [
                    {
                        question:
                            'Why does Billing show a payment warning banner?',
                        answer: 'This appears when a recent payment needs attention but normal access has not been removed yet. Resolve payment through the available billing action before it becomes read-only.',
                    },
                ],
            },
            {
                id: 'read-only-and-recovery',
                title: 'Read-only access and how to recover',
                summary:
                    'When the organization is read-only, existing records remain available to view, but new purchases, counts, waste, transfers, and similar changes are blocked until payment is resolved.',
                whenToUse:
                    'Use this when your team reports that an action is unexpectedly blocked.',
                controls: [
                    {
                        label: 'Manage billing',
                        description:
                            'Opens the billing management flow for organizations using this recovery path.',
                    },
                    {
                        label: 'Renew subscription',
                        description:
                            'Starts renewal for organizations using this recovery path when it is shown.',
                    },
                    {
                        label: 'Subscribe',
                        description:
                            'Starts a new subscription on a chosen plan and billing period when the organization has no active subscription.',
                    },
                ],
                whatHappensNext: [
                    'Normal write access returns automatically once Billing shows the payment as received and the subscription as Active or Trial.',
                ],
                troubleshooting: [
                    {
                        question:
                            'I paid, but the organization is still read-only. What should I do?',
                        answer: 'Wait a short time for the payment to be confirmed and shown on the Billing page, then refresh. A success screen or QR scan alone does not confirm payment.',
                    },
                ],
            },
            {
                id: 'plan-features-and-usage-limits',
                title: 'Plan features and usage limits',
                summary:
                    'Plan entitlements list the features included in the current plan and the usage limits, such as the number of locations or members, along with how much of each limit is currently used.',
                whenToUse:
                    'Check this when a feature or action seems unavailable, or when you cannot add another location or member.',
                notes: [
                    {
                        title: 'At or over a limit',
                        description:
                            'All existing data remains available, but creating a new record for a limit you have reached is blocked until you upgrade to a plan with enough capacity.',
                    },
                ],
                troubleshooting: [
                    {
                        question:
                            'Why can I not add another location or member?',
                        answer: 'Check the usage limits on the Billing page. If you are at or over your plan limit, upgrade to a plan with enough capacity to add more.',
                    },
                ],
            },
            {
                id: 'billing-management-actions',
                title: 'Managing your subscription',
                summary:
                    'Depending on how your organization is billed, you can manage payment details, upgrade to a higher plan, renew a subscription, or cancel automatic renewal.',
                controls: [
                    {
                        label: 'Manage billing',
                        description:
                            'Opens the billing management flow to update payment details or manage the subscription, when this option is available for your organization.',
                    },
                    {
                        label: 'Upgrade to [plan]',
                        description:
                            'Moves the organization to a higher plan while keeping the current billing interval, when an eligible upgrade is available.',
                    },
                    {
                        label: 'Cancel renewal',
                        description:
                            'Stops automatic renewal. Paid access remains available until the end of the current billing period, then the subscription will not renew.',
                    },
                ],
                whatHappensNext: [
                    'Cancelling renewal does not end access immediately. Your organization keeps normal access until the current billing period ends.',
                ],
            },
        ],
    },
    {
        slug: 'settings',
        title: 'Settings',
        description: 'Maintain your Profile, sign-in security, and Appearance.',
        overview:
            'Personal settings apply only to your own account: Profile, Security, and Appearance. Organization settings, further above, apply to the active organization instead. Keep your account secure without sharing your password, passkeys, or recovery codes with anyone.',
        keywords: [
            'profile',
            'password',
            'two-factor authentication',
            'passkeys',
            'recovery codes',
            'appearance',
            'theme',
            'dark mode',
            'light mode',
        ],
        navigationLabels: [],
        tutorials: [
            {
                id: 'secure-your-account',
                title: 'Secure your account',
                steps: [
                    'Review your name and email address on Profile.',
                    'Use a strong, unique password on Security and update it when needed.',
                    'Add a passkey or enable two-factor authentication on Security when you want extra sign-in protection.',
                    'Store recovery codes somewhere safe and never share them with anyone.',
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
                description:
                    'An additional sign-in check using a code from an authenticator app on your phone.',
            },
        ],
        troubleshooting: [
            'If you lose access to two-factor authentication, use one of your saved recovery codes to sign in. There is no way to sign in without a recovery code or your authenticator app, so keep your recovery codes stored somewhere safe.',
            'Use Organization settings, not personal Settings, when changing shared business information such as timezone or currency.',
        ],
        actions: [
            'profile-settings',
            'security-settings',
            'appearance-settings',
        ],
        pages: [
            {
                id: 'profile-settings',
                title: 'Profile',
                summary:
                    'Update your account name and email address. Your account requires a verified email address, and an unverified address is flagged here.',
                whenToUse:
                    'Use this when your name changes or you need to update the email address you sign in with.',
                controls: [
                    {
                        label: 'Save',
                        description:
                            'Applies your updated name or email address.',
                    },
                    {
                        label: 'Resend verification email',
                        description:
                            'Sends a new verification link when your email address is shown as unverified.',
                    },
                    {
                        label: 'Delete account',
                        description:
                            'Permanently deletes your account and its resources after you confirm your password. This cannot be undone.',
                    },
                ],
                whatHappensNext: [
                    'Changing your email requires verifying the new address before you can continue using the app.',
                ],
                troubleshooting: [
                    {
                        question: 'Why does my email show as unverified?',
                        answer: 'Use Resend verification email, then check your inbox for the verification link.',
                    },
                ],
            },
            {
                id: 'security-password',
                title: 'Password',
                summary:
                    'Change your sign-in password from Security. You must enter your current password along with the new one.',
                whenToUse:
                    'Use this if you suspect your password is known to someone else, or on a routine basis to keep your account secure.',
                fields: [
                    {
                        name: 'Current password',
                        description:
                            'Confirms it is really you making the change.',
                    },
                    {
                        name: 'New password and Confirm password',
                        description:
                            'The replacement password. It must meet your organization’s password policy, such as a minimum length and a mix of character types.',
                    },
                ],
                troubleshooting: [
                    {
                        question: 'Why is my new password rejected?',
                        answer: 'Check that it meets the minimum length and character requirements shown on the page, and that it is not a commonly breached password.',
                    },
                ],
            },
            {
                id: 'security-passkeys',
                title: 'Passkeys',
                summary:
                    'A passkey lets you sign in without typing a password, using your device’s built-in screen lock, security key, or biometric check.',
                whenToUse:
                    'Add a passkey when you want a faster, password-free way to sign in on a trusted device.',
                controls: [
                    {
                        label: 'Add passkey',
                        description:
                            'Registers a new passkey using your current device or a connected security key.',
                    },
                    {
                        label: 'Remove',
                        description:
                            'Deletes a saved passkey so it can no longer be used to sign in.',
                    },
                ],
                notes: [
                    {
                        title: 'Available only when enabled for your account',
                        description:
                            'Passkeys appear on Security only when this option is available to you.',
                    },
                ],
                troubleshooting: [
                    {
                        question:
                            'Why is Passkeys not shown on my Security page?',
                        answer: 'Passkey management is only available when it is enabled for your account.',
                    },
                ],
            },
            {
                id: 'security-two-factor',
                title: 'Two-factor authentication',
                summary:
                    'Two-factor authentication adds a second check at sign-in: a short code from a TOTP-supported authenticator app on your phone, in addition to your password.',
                whenToUse:
                    'Enable this when you want stronger protection than a password alone.',
                controls: [
                    {
                        label: 'Enable 2FA',
                        description:
                            'Starts setup by showing a QR code and manual setup key to scan or enter into your authenticator app.',
                    },
                    {
                        label: 'Continue setup',
                        description:
                            'Resumes an in-progress setup that has not been confirmed yet.',
                    },
                    {
                        label: 'Disable 2FA',
                        description:
                            'Turns off two-factor authentication for your account.',
                    },
                    {
                        label: 'View recovery codes',
                        description:
                            'Shows your one-time recovery codes so you can save them somewhere safe.',
                    },
                    {
                        label: 'Regenerate codes',
                        description:
                            'Replaces your current recovery codes with a new set. Save the new codes immediately, since the old ones stop working.',
                    },
                ],
                notes: [
                    {
                        title: 'Recovery codes are for you only',
                        description:
                            'Store recovery codes in a secure password manager and never share them. Anyone who has one can use it to sign in as you.',
                    },
                ],
                whatHappensNext: [
                    'Once enabled, you will be asked for a code from your authenticator app on future sign-ins.',
                ],
                troubleshooting: [
                    {
                        question:
                            'I lost access to my authenticator app. What should I do?',
                        answer: 'Use one of your saved recovery codes to sign in, then set up two-factor authentication again. There is no other way to sign back in if you have no recovery codes, so save them somewhere safe as soon as you enable two-factor authentication.',
                    },
                ],
            },
            {
                id: 'appearance-settings',
                title: 'Appearance',
                summary:
                    'Choose how MiseLedger looks on this device: Light, Dark, or System, which follows your device’s current setting.',
                whenToUse:
                    'Use this to match your preference or reduce glare in low-light conditions.',
                controls: [
                    {
                        label: 'Light',
                        description: 'Uses a light color theme.',
                    },
                    {
                        label: 'Dark',
                        description: 'Uses a dark color theme.',
                    },
                    {
                        label: 'System',
                        description:
                            'Follows your device’s current light or dark setting automatically.',
                    },
                ],
                whatHappensNext: [
                    'The selected appearance applies immediately and is remembered on this device.',
                ],
            },
        ],
    },
];

export const guideModulesBySlug = Object.fromEntries(
    guideModules.map((module) => [module.slug, module]),
) as Record<GuideModuleSlug, GuideModule>;
