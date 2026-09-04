<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Every amount the app displays is formatted from here, so switching
    | currency is a one-line change rather than a hunt through the views.
    |
    | `symbol` is passed through the translator before it is displayed, which
    | lets lang/ar.json localise it (EGP -> ج.م) without a second config entry.
    | The symbol is written after the amount ("1,234.56 EGP"): that reads
    | correctly in both the English and the Arabic layout, whereas a prefixed
    | symbol has to swap sides under dir="rtl".
    |
    */

    'currency' => [
        'code'     => env('INVENTORY_CURRENCY_CODE', 'EGP'),
        'symbol'   => env('INVENTORY_CURRENCY_SYMBOL', 'EGP'),
        'decimals' => 2,
    ],

];
