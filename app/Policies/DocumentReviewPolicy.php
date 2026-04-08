<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DocumentReview;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentReviewPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentReview');
    }

    public function view(AuthUser $authUser, DocumentReview $documentReview): bool
    {
        return $authUser->can('View:DocumentReview');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentReview');
    }

    public function update(AuthUser $authUser, DocumentReview $documentReview): bool
    {
        return $authUser->can('Update:DocumentReview');
    }

    public function delete(AuthUser $authUser, DocumentReview $documentReview): bool
    {
        return $authUser->can('Delete:DocumentReview');
    }

    public function restore(AuthUser $authUser, DocumentReview $documentReview): bool
    {
        return $authUser->can('Restore:DocumentReview');
    }

    public function forceDelete(AuthUser $authUser, DocumentReview $documentReview): bool
    {
        return $authUser->can('ForceDelete:DocumentReview');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DocumentReview');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DocumentReview');
    }

    public function replicate(AuthUser $authUser, DocumentReview $documentReview): bool
    {
        return $authUser->can('Replicate:DocumentReview');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DocumentReview');
    }

}