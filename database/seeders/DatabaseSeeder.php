<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Run `ddev artisan migrate:fresh --seed` to reset the demo data.
     */
    public function run(): void
    {
        $this->call([
            AdvisorSeeder::class,
            DatasetSeeder::class,
            FixtureSeeder::class,
        ]);
    }
}
