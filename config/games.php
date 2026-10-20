<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Remove Limits
    |--------------------------------------------------------------------------
    |
    | For testing only: when true, the daily rule (one vote or one new game per
    | user per day) is not enforced. Duplicate titles and one vote per game are
    | still rejected. This is always ignored in production.
    |
    */

    'remove_limits' => (bool) env('REMOVE_LIMITS', false),

];
