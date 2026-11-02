<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Nothing happens by default — demo data is opt-in so a production install
     * can never be filled with fake orders by accident (feature F-53):
     * `php artisan db:seed --class=DemoSeeder`.
     */
    public function run(): void
    {
        //
    }
}
