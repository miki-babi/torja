<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform commission
    |--------------------------------------------------------------------------
    |
    | The percentage the platform keeps on each payment processed through it
    | for a trainer's coaching plan. The remainder accrues to the trainer's
    | payout balance.
    |
    */

    'commission_percent' => (float) env('PLATFORM_COMMISSION_PERCENT', 5),

];
