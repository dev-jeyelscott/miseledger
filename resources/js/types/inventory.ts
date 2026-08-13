export type UnitDimension = 'weight' | 'volume' | 'count';

export type InventoryCategoryData = {
    id: number;
    name: string;
    active: boolean;
};

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
    active: boolean;
    conversionCount: number;
    baseUnitOfMeasure: UnitOfMeasureData;
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
    active: boolean;
    baseUnitOfMeasure: UnitOfMeasureData;
    unitConversions: InventoryItemUnitData[];
};
