<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use Illuminate\Foundation\Auth\User as AuthUser;

class DocumentPolicy extends BaseResourcePolicy
{
    protected function modelName(): string
    {
        return 'Document';
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('RestoreAny'));
    }

    public function replicate(AuthUser $authUser, Document $document): bool
    {
        return $authUser->can($this->permissionName('Replicate'));
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('Reorder'));
    }
}
