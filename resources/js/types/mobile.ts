type MobileLocationOption = {
    id: number;
    name: string;
    code: string;
};

type MobileActiveLocation = MobileLocationOption | null;

type MobileOrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type ScannedItemUnit = {
    id: number;
    name: string;
    symbol: string;
    isBase: boolean;
};

type ScannedItemStorageBalance = {
    storageLocationId: number;
    name: string;
    quantityOnHand: string;
};

type ScannedItemAction = 'receive' | 'count' | 'waste' | 'transfer' | 'details';

type ScannedItem = {
    inventoryItemId: number;
    name: string;
    sku: string | null;
    matchedUnit: ScannedItemUnit;
    baseUnit: ScannedItemUnit;
    stockAtActiveLocation: {
        quantityOnHand: string;
        unitSymbol: string;
        byStorageLocation: ScannedItemStorageBalance[];
    };
    availableActions: ScannedItemAction[];
};

type ScanLookupResponse =
    | { match: ScannedItem }
    | { matches: ScannedItem[] }
    | { notFound: true; query: string };

type ItemSearchResponse = { matches: ScannedItem[] };

type MobileTaskType =
    'receive' | 'count' | 'ship' | 'receive_transfer' | 'restock';

type MobileTaskUrgency = 'overdue' | 'in_progress' | 'ready' | 'attention';

type MobileTask = {
    type: MobileTaskType;
    urgency: MobileTaskUrgency;
    title: string;
    subtitle: string;
    /** The exact mobile route to resume this task. */
    href: string;
    /** ISO timestamp; drives secondary sort + relative-time display. */
    createdAt: string;
};

type MobileTaskGroup = {
    type: MobileTaskType;
    label: string;
    tasks: MobileTask[];
};

export type {
    ItemSearchResponse,
    MobileActiveLocation,
    MobileLocationOption,
    MobileOrganizationSummary,
    MobileTask,
    MobileTaskGroup,
    MobileTaskType,
    MobileTaskUrgency,
    ScanLookupResponse,
    ScannedItem,
    ScannedItemAction,
    ScannedItemStorageBalance,
    ScannedItemUnit,
};
