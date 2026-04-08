<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DivisionSeeder::class,
            ShieldSeeder::class,
            RoleAndPermissionSeeder::class,
            UserFromCsvSeeder::class,
        ]);
    }
}
