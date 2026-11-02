<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active data store (decision D13, feature F-57)
    |--------------------------------------------------------------------------
    |
    | json  - data is kept in JSON files under storage/app/data. No database is
    |         required, so the application starts on a shared host right away.
    | mysql - data is kept in the MySQL/MariaDB database configured in the
    |         standard DB_* variables. Run `php artisan migrate --force` first.
    |
    | Switching is a single change in .env plus `php artisan store:check`.
    | Actions, views and controllers never learn which store is active.
    |
    */

    'driver' => env('DATA_STORE', 'json'),

    'json' => [

        // Directory outside the web root that keeps services.json, users.json,
        // service_events.json, service_photos.json and meta.json.
        'path' => env('DATA_STORE_PATH', storage_path('app/data')),

        // Pretty printed files are readable during development but bigger.
        'pretty' => (bool) env('DATA_STORE_PRETTY', false),

        // Write lock around every read-modify-write cycle (flock).
        'lock' => true,

        // Keep a .bak copy before a schema upgrade rewrites the files.
        'backup_on_schema_change' => true,

        // Above this number of services `store:check` suggests moving to MySQL.
        'scale_warning' => (int) env('DATA_STORE_SCALE_WARNING', 5000),
    ],

    'mysql' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
    ],

];
