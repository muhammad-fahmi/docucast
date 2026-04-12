<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * REMOVED: updateStatusBasedOnReviews() - Use DocumentStatusService
     * REMOVED: canRecipientSubmitReview() - Use DocumentReviewAuthorizationService
     * REMOVED: allowRecipientToReviewAgain() - Use DocumentReviewAuthorizationService
     */
    public static function formatUniqueCode(int $uploaderId, string $datePart, int $documentId): string
    {
        return sprintf('#%d%s%06d', $uploaderId, $datePart, $documentId);
    }
}
