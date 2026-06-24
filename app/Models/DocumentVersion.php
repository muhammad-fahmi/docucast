<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentVersion extends Model
{
    protected $fillable = [
        'document_id',
        'version_number',
        'file_storage_path',
        'original_filename',
        'uploaded_by',
    ];

    // Links back to the master stable ID
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // The specific user who uploaded this exact version (usually the initiator)
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Reviews submitted for this specific version
    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class, 'document_version_id');
    }
}
