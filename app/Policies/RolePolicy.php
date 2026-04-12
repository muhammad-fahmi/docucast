<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Permission\Models\Role;

class RolePolicy extends BaseResourcePolicy
{
    protected function modelName(): string
    {
        return 'Role';
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('RestoreAny'));
    }

    public function replicate(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can($this->permissionName('Replicate'));
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('Reorder'));
    }
}
