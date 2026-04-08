<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserFromCsvSeeder extends Seeder
{
    /**
     * Maps CSV role column values to division slugs.
     *
     * @var array<string, string>
     */
    private array $roleToDivisionSlug = [
        'Warehouse RM' => 'warehouse-raw',
        'Warehouse FG' => 'warehouse-package',
        'Warehouse Logistic' => 'warehouse-package',
        'Maintenance' => 'maintenance',
        'Production' => 'production',
        'QC' => 'quality-control',
        'QA' => 'quality-assurance',
        'RnD' => 'research-and-development',
        'PPIC' => 'ppic',
        'HR' => 'hr',
        'GA' => 'general-affair',
    ];

    public function run(): void
    {
        $divisions = Division::all()->keyBy('slug');
        $csvPath = base_path('data_keryawan.csv');

        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found at: {$csvPath}");

            return;
        }

        $handle = fopen($csvPath, 'r');

        // Skip header row
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            [$employeeNo, $name, $jobTitle, $csvRole, $password, $email] = $row;

            $divisionId = null;
            $divisionSlug = $this->roleToDivisionSlug[trim($csvRole)] ?? null;

            if ($divisionSlug && $divisions->has($divisionSlug)) {
                $divisionId = $divisions->get($divisionSlug)->id;
            }

            $user = User::firstOrCreate(
                ['email' => trim($email)],
                [
                    'name' => trim($name),
                    'employee_no' => trim($employeeNo),
                    'job_title' => trim($jobTitle),
                    'division_id' => $divisionId,
                    'password' => Hash::make(trim($password)),
                    'email_verified_at' => now(),
                ]
            );

            if (!$user->hasRole('super_admin') && !$user->hasRole('admin')) {
                $user->assignRole('recipient');
            }
        }

        fclose($handle);
    }
}
