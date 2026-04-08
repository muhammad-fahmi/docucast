<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Document extends Model
{
    /** @use HasFactory */
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'file_path',
        'file_name',
        'unique_code',
        'uploader_id',
        'status',
    ];

    protected static function booted(): void
    {
        static::created(function (self $document): void {
            if (filled($document->unique_code)) {
                return;
            }

            $datePart = $document->created_at?->format('Ymd') ?? now()->format('Ymd');

            $document->forceFill([
                'unique_code' => self::formatUniqueCode($document->uploader_id, $datePart, $document->id),
            ])->saveQuietly();
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_recipients');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class);
    }

    public function updateStatusBasedOnReviews(): void
    {
        $summary = DB::table('document_recipients')
            ->leftJoin('document_reviews', function ($join): void {
                $join->on('document_reviews.document_id', '=', 'document_recipients.document_id');
            })
            ->where('document_recipients.document_id', $this->id)
            ->selectRaw('COUNT(DISTINCT document_recipients.user_id) AS total_recipients')
            ->selectRaw("COUNT(DISTINCT CASE WHEN document_reviews.status = 'approved' THEN document_reviews.user_id END) AS approved_recipients")
            ->selectRaw("MAX(CASE WHEN document_reviews.status = 'revision' THEN 1 ELSE 0 END) AS has_revision")
            ->first();

        $totalRecipients = (int) ($summary?->total_recipients ?? 0);
        $nextStatus = 'pending';

        if ($totalRecipients > 0) {
            $approvedCount = (int) ($summary?->approved_recipients ?? 0);
            $hasRevision = (int) ($summary?->has_revision ?? 0) === 1;

            $nextStatus = ($hasRevision || $approvedCount < $totalRecipients) ? 'in_review' : 'approved';
        }

        if ($this->status !== $nextStatus) {
            $this->update(['status' => $nextStatus]);
        }
    }

    public function canRecipientSubmitReview(User $user): bool
    {
        $isRecipient = DB::table('document_recipients')
            ->where('document_id', $this->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$isRecipient) {
            return false;
        }

        return !DB::table('document_reviews')
            ->where('document_id', $this->id)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }

    public function allowRecipientToReviewAgain(int $recipientId): void
    {
        $this->reviews()
            ->where('user_id', $recipientId)
            ->where('status', 'approved')
            ->delete();

        $this->refresh();
        $this->updateStatusBasedOnReviews();
    }

    public static function formatUniqueCode(int $uploaderId, string $datePart, int $documentId): string
    {
        return sprintf('#%d%s%06d', $uploaderId, $datePart, $documentId);
    }
}
