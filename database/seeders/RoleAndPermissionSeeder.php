<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = ['super_admin', 'admin', 'uploader', 'recipient'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Create default super admin account
        $superAdmin = User::firstOrCreate(
            ['nik' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('Super@dmin1'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->syncRoles(['super_admin']);

        // Create default admin account
        $admin = User::firstOrCreate(
            ['nik' => 'admin'],
            [
                'name' => 'Admin',
                'password' => bcrypt('Adm!nDoc1'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);
    }
}
