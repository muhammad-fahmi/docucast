<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;

class DocumentReviewAuthorizationService
{
    public function __construct(
        private DocumentStatusService $statusService,
    ) {
    }

    /**
     * Check if user can submit a review for this document
     */
    public function canUserSubmitReview(Document $document, User $user): bool
    {
        if (!$this->isRecipient($document, $user)) {
            return false;
        }

        return !$this->hasApprovedReview($document, $user);
    }

    /**
     * Check if user can revise a document (is the uploader)
     */
    public function canUserReviseDocument(Document $document, User $user): bool
    {
        return $document->uploader_id === $user->id && $document->status === 'revision';
    }

    /**
     * Allow a recipient to review again
     */
    public function allowReviewAgain(Document $document, int $recipientId): void
    {
        $document->reviews()
            ->where('user_id', $recipientId)
            ->where('status', 'approved')
            ->delete();

        $this->statusService->updateStatus($document);
    }

    /**
     * Check if user is a recipient
     */
    private function isRecipient(Document $document, User $user): bool
    {
        return $document->recipients()
            ->where('users.id', $user->id)
            ->exists();
    }

    /**
     * Check if user has submitted an approved review
     */
    private function hasApprovedReview(Document $document, User $user): bool
    {
        return $document->reviews()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }
}
