<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Division;
use Illuminate\Foundation\Auth\User as AuthUser;

class DivisionPolicy extends BaseResourcePolicy
{
    protected function modelName(): string
    {
        return 'Division';
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('RestoreAny'));
    }

    public function replicate(AuthUser $authUser, Division $division): bool
    {
        return $authUser->can($this->permissionName('Replicate'));
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('Reorder'));
    }
}
