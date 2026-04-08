<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevisionHistory extends Model
{
    protected $fillable = [
        'document_id', 'related_version_id', 'commenter_id',
        'action_type', 'comments'
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // Links the comment to the specific PDF they were looking at
    public function relatedVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'related_version_id');
    }

    public function commenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commenter_id');
    }
}
