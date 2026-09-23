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

export type {
    ItemSearchResponse,
    MobileActiveLocation,
    MobileLocationOption,
    MobileOrganizationSummary,
    ScanLookupResponse,
    ScannedItem,
    ScannedItemAction,
    ScannedItemStorageBalance,
    ScannedItemUnit,
};
