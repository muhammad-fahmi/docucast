<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

class DocumentStatusService
{
    /**
     * Calculate the next status based on reviews
     */
    public function calculateNextStatus(Document $document): DocumentStatus
    {
        $summary = $this->getReviewSummary($document);

        if ($summary['totalRecipients'] === 0) {
            return DocumentStatus::Pending;
        }

        $hasRevision = (bool) $summary['hasRevision'];
        $allApproved = $summary['approvedCount'] === $summary['totalRecipients'];

        if ($hasRevision || !$allApproved) {
            return DocumentStatus::InReview;
        }

        return DocumentStatus::Approved;
    }

    /**
     * Update document status based on reviews
     */
    public function updateStatus(Document $document): void
    {
        $nextStatus = $this->calculateNextStatus($document);

        if ($document->status !== $nextStatus->value) {
            $document->update(['status' => $nextStatus->value]);
        }
    }

    /**
     * Update document status to a specific status if transition is valid
     */
    public function transitionStatus(Document $document, DocumentStatus $targetStatus): bool
    {
        $currentStatus = DocumentStatus::tryFrom($document->status);

        if ($currentStatus === null) {
            return false;
        }

        if (!$currentStatus->canTransitionTo($targetStatus)) {
            return false;
        }

        $document->update(['status' => $targetStatus->value]);

        return true;
    }

    /**
     * Get aggregated review summary
     */
    private function getReviewSummary(Document $document): array
    {
        $result = DB::table('document_recipients')
            ->leftJoin('document_reviews', function ($join): void {
                $join->on('document_reviews.document_id', '=', 'document_recipients.document_id')
                    ->on('document_reviews.user_id', '=', 'document_recipients.user_id');
            })
            ->where('document_recipients.document_id', $document->id)
            ->selectRaw('COUNT(DISTINCT document_recipients.user_id) AS total_recipients')
            ->selectRaw("COUNT(DISTINCT CASE WHEN document_reviews.status = 'approved' THEN document_reviews.user_id END) AS approved_recipients")
            ->selectRaw("MAX(CASE WHEN document_reviews.status = 'revision' THEN 1 ELSE 0 END) AS has_revision")
            ->first();

        if ($result === null) {
            return [
                'totalRecipients' => 0,
                'approvedCount' => 0,
                'hasRevision' => false,
            ];
        }

        return [
            'totalRecipients' => (int) ($result->total_recipients ?? 0),
            'approvedCount' => (int) ($result->approved_recipients ?? 0),
            'hasRevision' => (bool) ($result->has_revision ?? false),
        ];
    }
}
