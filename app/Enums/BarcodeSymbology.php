<?php

namespace App\Enums;

enum BarcodeSymbology: string
{
    case Ean13 = 'ean_13';
    case Ean8 = 'ean_8';
    case UpcA = 'upc_a';
    case UpcE = 'upc_e';
    case Code128 = 'code_128';
    case Code39 = 'code_39';
    case Other = 'other';
}
