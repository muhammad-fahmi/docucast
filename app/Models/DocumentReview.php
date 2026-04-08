<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentReview extends Model
{
    protected $fillable = [
        'document_id',
        'user_id',
        'status',
        'message',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
