<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pro pricing
    |--------------------------------------------------------------------------
    |
    | The fallback one-time price (in ETB) for the lifetime Pro membership.
    | Admins can override it from the Pro page settings; see
    | App\Support\ProPage::price(). Overridable via PRO_PRICE_ETB (falls
    | back to the legacy CHAPA_PLAN_PRICE_ETB name).
    |
    */

    'pro_price_etb' => env('PRO_PRICE_ETB', env('CHAPA_PLAN_PRICE_ETB', 500)),

];
