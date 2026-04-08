<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $roles = $this->form->getRawState()['roles'] ?? [];

        if (!empty($roles)) {
            $roles = $this->enforceRoleConstraints($roles);
            $this->record->syncRoles($roles);
        }
    }

    /**
     * Enforces that super_admin/admin cannot be combined with other roles.
     *
     * @param  array<string>  $roles
     * @return array<string>
     */
    private function enforceRoleConstraints(array $roles): array
    {
        $exclusiveRoles = ['super_admin', 'admin'];

        if (in_array('super_admin', $roles, true)) {
            return ['super_admin'];
        }

        if (in_array('admin', $roles, true)) {
            return ['admin'];
        }

        return array_values(array_diff($roles, $exclusiveRoles));
    }
}
