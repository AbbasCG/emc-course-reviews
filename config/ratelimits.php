<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Student review submission (POST /review/{token})
    |--------------------------------------------------------------------------
    |
    | Two independent limits, both per minute:
    |
    | per_ip      A wide ceiling for one public IP. A classroom, campus or mobile
    |             carrier puts hundreds of different students behind one address,
    |             so this only stops floods. (The old limit was 5 per minute per
    |             IP, which blocked everyone on a shared Wi-Fi after 5 students.)
    |
    | per_session Stops naive repeat-submits from one browser/device (double clicks,
    |             a script reusing its cookie) without affecting anyone else on the
    |             network. A script that fetches a fresh session each time is NOT
    |             stopped by this; the per-IP ceiling is the flood control.
    |
    */
    'review_submit' => [
        'per_ip'      => (int) env('REVIEW_RATE_PER_IP', 300),
        'per_session' => (int) env('REVIEW_RATE_PER_SESSION', 6),
    ],
];
