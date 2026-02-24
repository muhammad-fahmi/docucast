<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // A user can upload many initial documents
    public function initiatedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'initiator_id');
    }

    // A user can be assigned to review many documents
    public function documentApprovals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class, 'reviewer_id');
    }

    // A user can leave many revision comments
    public function revisionComments(): HasMany
    {
        return $this->hasMany(RevisionHistory::class, 'commenter_id');
    }
}
