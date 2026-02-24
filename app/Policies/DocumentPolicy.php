<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Everyone can view the list (we filter the list in getEloquentQuery)
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Document $document): bool
    {
        // Allow if Admin
        if ($user->role === 'admin')
            return true;

        // Allow if Uploader
        if ($document->initiator_id === $user->id)
            return true;

        // Allow only if they are in the reviewers list for THIS document
        return $document->approvals()
            ->where('reviewer_id', $user->id)
            ->exists();
    }

    /**
     * THIS CONTROLS THE "NEW DOCUMENT" BUTTON
     */
    public function create(User $user): bool
    {
        // ONLY the uploader role can see the button and create documents
        return $user->role === 'uploader';
    }

    /**
     * THIS CONTROLS THE "EDIT" (Pencil) BUTTON
     */
    public function update(User $user, Document $document): bool
    {
        // Only the original uploader can edit it, and ONLY if it's not approved yet
        return $user->id === $document->initiator_id && $document->overall_status !== 'APPROVED';
    }

    /**
     * THIS CONTROLS THE "DELETE" (Trash) BUTTON
     */
    public function delete(User $user, Document $document): bool
    {
        // Only admins can delete documents, or uploaders if it's still a draft
        return $user->role === 'admin';
    }
}
