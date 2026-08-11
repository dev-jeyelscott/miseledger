<?php

namespace App\Enums;

enum OrganizationPermission: string
{
    case InventoryView = 'inventory.view';
    case InventoryAdjust = 'inventory.adjust';

    case PurchasingView = 'purchasing.view';
    case PurchasingManage = 'purchasing.manage';

    case ReceivingFinalize = 'receiving.finalize';

    case CountsCreate = 'counts.create';
    case CountsFinalize = 'counts.finalize';

    case WasteRecord = 'waste.record';

    case TransfersCreate = 'transfers.create';
    case TransfersShip = 'transfers.ship';
    case TransfersReceive = 'transfers.receive';

    case RecipesView = 'recipes.view';
    case RecipesManage = 'recipes.manage';

    case ReportsView = 'reports.view';
    case CostsView = 'costs.view';

    case UsersManage = 'users.manage';
}
