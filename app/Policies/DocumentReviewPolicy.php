<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentReview;
use Illuminate\Foundation\Auth\User as AuthUser;

class DocumentReviewPolicy extends BaseResourcePolicy
{
    protected function modelName(): string
    {
        return 'DocumentReview';
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('RestoreAny'));
    }

    public function replicate(AuthUser $authUser, DocumentReview $documentReview): bool
    {
        return $authUser->can($this->permissionName('Replicate'));
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can($this->permissionName('Reorder'));
    }
}
