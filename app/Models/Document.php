<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    // $fillable protects against mass-assignment vulnerabilities.
    // Only these columns can be saved directly via Document::create([])
    protected $fillable = [
        'title',
        'initiator_id',
        'overall_status'
    ];

    // The document belongs to the user who originally uploaded it
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    // A document has many uploaded versions (V1, V2, V3...)
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    // MAGIC BULLET: This gets ONLY the newest version.
    // When you need to show the supervisor the "current" file, you call $document->latestVersion
    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('version_number');
    }

    // The workflow state (whose inbox is it in?)
    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }

    // The full timeline of comments and status changes
    public function revisionHistory(): HasMany
    {
        return $this->hasMany(RevisionHistory::class);
    }
}
