<?php

namespace App\Models;

use App\Services\DocumentReviewAuthorizationService;
use App\Services\DocumentStatusService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        'auto_approve',
        'limit_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auto_approve' => 'boolean',
            'limit_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $document): void {
            if (empty($document->unique_code)) {
                $datePart = $document->created_at?->format('Ymd') ?? now()->format('Ymd');
                $document->forceFill([
                    'unique_code' => self::formatUniqueCode($document->uploader_id, $datePart, $document->id),
                ])->saveQuietly();
            }

            // Create initial version
            if ($document->file_path) {
                $document->versions()->create([
                    'version_number' => 1,
                    'file_storage_path' => $document->file_path,
                    'original_filename' => $document->file_name,
                    'uploaded_by' => auth()->id() ?? $document->uploader_id,
                ]);
            }
        });

        static::updating(function (self $document): void {
            if ($document->isDirty('file_path')) {
                $oldPath = $document->getOriginal('file_path');
                // Archive the old file so it's not deleted by Filament FileUpload
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    $newPath = 'documents/archive/' . basename($oldPath);
                    Storage::disk('public')->copy($oldPath, $newPath);

                    // Update previous versions to point to the archived file
                    $document->versions()->where('file_storage_path', $oldPath)->update([
                        'file_storage_path' => $newPath,
                    ]);
                }
            }
        });

        static::updated(function (self $document): void {
            if ($document->wasChanged('file_path')) {
                $previousVersionId = $document->versions()->max('id');

                $nextVersion = $document->versions()->max('version_number') + 1;
                $newVersion = $document->versions()->create([
                    'version_number'     => $nextVersion,
                    'file_storage_path'  => $document->file_path,
                    'original_filename'  => $document->file_name,
                    'uploaded_by'        => auth()->id() ?? $document->uploader_id,
                ]);

                // Carry forward approvals from the previous version
                // Recipients who approved v1 don't need to re-review v2
                if ($previousVersionId) {
                    $approvedReviews = DocumentReview::query()
                        ->where('document_id', $document->id)
                        ->where('document_version_id', $previousVersionId)
                        ->where('status', 'approved')
                        ->get();

                    $now = now();

                    foreach ($approvedReviews as $review) {
                        DocumentReview::query()->upsert(
                            [
                                [
                                    'document_id'         => $document->id,
                                    'document_version_id' => $newVersion->id,
                                    'user_id'             => $review->user_id,
                                    'status'              => 'approved',
                                    'message'             => null,
                                    'attachment_path'     => null,
                                    'attachment_name'     => null,
                                    'created_at'          => $now,
                                    'updated_at'          => $now,
                                ],
                            ],
                            ['document_version_id', 'user_id'],
                            ['status', 'message', 'attachment_path', 'attachment_name', 'updated_at'],
                        );
                    }
                }
            }
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

    public function updateStatusBasedOnReviews(): void
    {
        app(DocumentStatusService::class)->updateStatus($this);
    }

    public function canRecipientSubmitReview(User $user): bool
    {
        return app(DocumentReviewAuthorizationService::class)->canUserSubmitReview($this, $user);
    }

    public function allowRecipientToReviewAgain(int $recipientId): void
    {
        app(DocumentReviewAuthorizationService::class)->allowReviewAgain($this, $recipientId);
    }

    public static function formatUniqueCode(int $uploaderId, string $datePart, int $documentId): string
    {
        return sprintf('#%d%s%06d', $uploaderId, $datePart, $documentId);
    }

    public function currentVersionReviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class)
            ->where(
                'document_version_id',
                DB::raw('(SELECT MAX(id) FROM document_versions WHERE document_versions.document_id = ' . $this->id . ')')
            );
    }
}
