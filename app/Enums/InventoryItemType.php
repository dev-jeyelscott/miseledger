<?php

namespace App\Enums;

enum InventoryItemType: string
{
    case Ingredient = 'ingredient';
    case FinishedItem = 'finished_item';
    case PreparedItem = 'prepared_item';
    case Packaging = 'packaging';
    case Consumable = 'consumable';
}
