<?php

return [

    /*
    | "Today" for all date math, as YYYY-MM-DD. The dataset is written as of
    | 2026-10-05, so days since contact only make sense from that date. Leave
    | it empty to use the real date. See AppServiceProvider::pinToday().
    */
    'today' => env('APP_TODAY'),

    /*
    | The one shared password for every demo account. The seeder
    | hashes it into each account, so changing it means seeding again.
    */
    'demo_password' => env('DEMO_PASSWORD'),

];
