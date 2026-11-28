<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live shares
    |--------------------------------------------------------------------------
    |
    | Live shares are not finished yet, so they ship switched off. When this is
    | false every live-share route returns 404, the UI hides the feature and
    | the liveshares_enabled setting is treated as false.
    |
    */

    'liveshares' => filter_var(env('ERUGO_LIVESHARES', false), FILTER_VALIDATE_BOOLEAN),

];
