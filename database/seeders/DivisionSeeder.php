<?php

namespace Database\Seeders;

use App\Models\Division;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        if (Division::exists()) {
            $this->command->info('Division table is not empty. Division seeding skipped.');
            return;
        }

        $divisions = [
            'Warehouse Raw',
            'Warehouse Package',
            'Maintenance',
            'Production',
            'Quality Control',
            'Quality Assurance',
            'Research and Development',
            'PPIC',
            'HR',
            'General Affair',
        ];

        foreach ($divisions as $division) {
            Division::firstOrCreate(
                ['slug' => Str::slug($division)],
                ['name' => $division],
            );
        }
    }
}
