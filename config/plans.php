<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pro pricing
    |--------------------------------------------------------------------------
    |
    | The fallback one-time price (in ETB) for the lifetime Pro membership.
    | Admins can override it from the Pro page settings; see
    | App\Support\ProPage::price(). Overridable via PRO_PRICE_ETB.
    |
    */

    'pro_price_etb' => env('PRO_PRICE_ETB', 500),

];
