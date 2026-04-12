<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case RequiresRevision = 'revision';

    public function displayName(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::InReview => 'In Review',
            self::Approved => 'Approved',
            self::RequiresRevision => 'Revision Required',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InReview => 'info',
            self::Approved => 'success',
            self::RequiresRevision => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Approved || $this === self::RequiresRevision;
    }

    public function canTransitionTo(self $targetStatus): bool
    {
        return match ($this) {
            self::Pending => $targetStatus === self::InReview,
            self::InReview => in_array($targetStatus, [self::Approved, self::RequiresRevision]),
            self::RequiresRevision => $targetStatus === self::InReview,
            self::Approved => false,
        };
    }
}
