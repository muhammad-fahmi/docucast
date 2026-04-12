<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;

class UserPolicy extends BaseResourcePolicy
{
    protected function modelName(): string
    {
        return 'User';
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('RestoreAny'));
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('Replicate'));
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('Reorder'));
    }
}
