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
};
