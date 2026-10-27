<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pro pricing
    |--------------------------------------------------------------------------
    |
    | The one-time price (in ETB) for the lifetime Pro membership, charged via
    | Chapa or Verify Checkout. Overridable via PRO_PRICE_ETB (falls back to
    | the legacy CHAPA_PLAN_PRICE_ETB name).
    |
    */

    'pro_price_etb' => env('PRO_PRICE_ETB', env('CHAPA_PLAN_PRICE_ETB', 500)),

];
