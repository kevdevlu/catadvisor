<?php

return [

    /*
    | "Today" for all date math, as YYYY-MM-DD. The dataset is written as of
    | 2026-10-05, so days since contact only make sense from that date, and
    | the default is that date even if APP_TODAY is never set. Set APP_TODAY
    | to an empty value to use the real date. See App\Support\Clock.
    */
    'today' => env('APP_TODAY', '2026-10-05'),

    /*
    | The one shared password for every demo account. The seeder
    | hashes it into each account, so changing it means seeding again.
    */
    'demo_password' => env('DEMO_PASSWORD'),

];
