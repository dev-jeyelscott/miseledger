export type UnitDimension = 'weight' | 'volume' | 'count';

export type InventoryCategoryData = {
    id: number;
    name: string;
    active: boolean;
};

export type InventoryItemType =
    | 'ingredient'
    | 'finished_item'
    | 'prepared_item'
    | 'packaging'
    | 'consumable';

export type UnitOfMeasureData = {
    id: number;
    name: string;
    symbol: string;
    active: boolean;
};

export type UnitOfMeasureMasterData = UnitOfMeasureData & {
    dimension: UnitDimension;
};

export type InventoryItemListItem = {
    id: number;
    name: string;
    sku: string;
    type: InventoryItemType;
    yieldPercentage: string;
    active: boolean;
    conversionCount: number;
    baseUnitOfMeasure: UnitOfMeasureData;
    inventoryCategory: InventoryCategoryData | null;
};

export type InventoryItemUnitData = {
    id: number;
    quantityInBaseUnit: string;
    active: boolean;
    unitOfMeasure: UnitOfMeasureData;
};

export type BarcodeSymbology =
    'ean_13' | 'ean_8' | 'upc_a' | 'upc_e' | 'code_128' | 'code_39' | 'other';

export type BarcodeData = {
    id: number;
    value: string;
    symbology: BarcodeSymbology;
    isPrimary: boolean;
    active: boolean;
    inventoryItemUnit: {
        id: number;
        unitOfMeasure: UnitOfMeasureData;
    } | null;
};

export type InventoryItemDetail = {
    id: number;
    name: string;
    sku: string;
    type: InventoryItemType;
    yieldPercentage: string;
    active: boolean;
    baseUnitOfMeasure: UnitOfMeasureData;
    inventoryCategory: InventoryCategoryData | null;
    unitConversions: InventoryItemUnitData[];
    barcodes: BarcodeData[];
};
