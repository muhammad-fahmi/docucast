<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\RevisionHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DocumentReviewService
{
    public function __construct(
        private DocumentStatusService $statusService,
        private DocumentReviewAuthorizationService $authorizationService,
    ) {
    }

    /**
     * Submit a review for a document
     */
    public function submitReview(
        Document $document,
        User $reviewer,
        string $reviewStatus,
        ?string $comments = null,
    ): DocumentReview {
        return DB::transaction(function () use ($document, $reviewer, $reviewStatus, $comments) {
            // 1. Check authorization
            if (!$this->authorizationService->canUserSubmitReview($document, $reviewer)) {
                throw new \InvalidArgumentException('User cannot submit review for this document');
            }

            // 2. Create or update review
            $review = DocumentReview::updateOrCreate(
                [
                    'document_id' => $document->id,
                    'user_id' => $reviewer->id,
                ],
                [
                    'status' => $reviewStatus,
                    'review_comments' => $comments,
                ]
            );

            // 3. Record history
            RevisionHistory::create([
                'document_id' => $document->id,
                'commenter_id' => $reviewer->id,
                'action_type' => strtoupper($reviewStatus),
                'comments' => $comments,
            ]);

            // 4. Update document status based on all reviews
            $this->statusService->updateStatus($document);

            return $review;
        });
    }

    /**
     * Request revision for a document
     */
    public function requestRevision(
        Document $document,
        User $requester,
        ?string $revisionComments = null,
    ): void {
        DB::transaction(function () use ($document, $requester, $revisionComments) {
            // 1. Update document status to RequiresRevision
            $this->statusService->transitionStatus($document, DocumentStatus::RequiresRevision);

            // 2. Record revision history
            RevisionHistory::create([
                'document_id' => $document->id,
                'commenter_id' => $requester->id,
                'action_type' => 'REVISION_REQUESTED',
                'comments' => $revisionComments,
            ]);

            // 3. Optionally: Send notification to uploader
            // This would typically be done via events/listeners
        });
    }

    /**
     * Get review summary for a document
     */
    public function getReviewSummary(Document $document): array
    {
        $reviews = $document->reviews()
            ->with('user')
            ->get()
            ->groupBy('status');

        return [
            'approved' => $reviews->get('approved', collect())->map->user,
            'revision' => $reviews->get('revision', collect())->map->user,
            'pending' => $document->recipients()
                ->whereNotIn('users.id', $document->reviews()->pluck('user_id'))
                ->get(),
        ];
    }

    /**
     * Reset reviews for a document (used when re-submitting revision)
     */
    public function resetReviews(Document $document): void
    {
        DB::transaction(function () use ($document) {
            $document->reviews()->delete();
            $this->statusService->updateStatus($document);
        });
    }
}
