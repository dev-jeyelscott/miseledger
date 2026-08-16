<?php

namespace App\Enums;

enum RecipeType: string
{
    case MenuItem = 'menu_item';
    case PreparedItem = 'prepared_item';
    case Batch = 'batch';
}
