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
        if (\App\Models\User::query()->count() === 0) {
            $this->call([
                DivisionSeeder::class,
                ShieldSeeder::class,
                RoleAndPermissionSeeder::class,
                UserFromCsvSeeder::class,
            ]);
        } else {
            $this->command->info('⏭️  Database already seeded. Skipping seeders...');
        }
    }
}
