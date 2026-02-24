<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Path to your CSV file
        $filePath = base_path('DATA KARYAWAN REVISI.csv');

        if (!file_exists($filePath)) {
            $this->command->error("CSV file not found at $filePath");
            return;
        }

        $file = fopen($filePath, 'r');
        $header = fgetcsv($file); // Skip the header row

        while (($data = fgetcsv($file)) !== FALSE) {
            // Mapping based on your CSV structure:
            // 0: employee_no, 1: name, 2: job_title, 3: role, 4: password

            User::updateOrCreate(
                ['email' => $data[0] . '@example.com'],
                [
                    'name' => $data[1],
                    'role' => strtolower($data[3]) != 'qa' ? 'reviewer' : 'uploader',
                    'password' => Hash::make($data[4]),
                ]
            );
        }

        fclose($file);
        $this->command->info('Users seeded successfully from CSV!');

        // 3. (Optional) Create an Admin
        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Charlie (Admin)',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
    }
}
