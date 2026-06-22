<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        return $document->uploader_id === $user->id
            || $document->recipients()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin', 'uploader']);
    }

    public function update(User $user, Document $document): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        return $document->uploader_id === $user->id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function restore(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:Document');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:Document');
    }

    public function replicate(User $user, Document $document): bool
    {
        return $user->can('Replicate:Document');
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:Document');
    }
}
