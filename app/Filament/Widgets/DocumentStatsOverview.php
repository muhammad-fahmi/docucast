<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DocumentStatsOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        $user = Auth::user();

        return $user && $user->hasAnyRole(['super_admin', 'admin']);
    }

    protected function getStats(): array
    {
        $statusCounts = Document::query()
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'in_review') AS in_review_count")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'approved') AS approved_count")
            ->first();

        $revisionCount = Document::query()
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('document_reviews')
                    ->whereColumn('document_reviews.document_id', 'documents.id')
                    ->where('document_reviews.status', 'revision');
            })
            ->count();

        $inReviewCount = (int) ($statusCounts?->in_review_count ?? 0);
        $approvedCount = (int) ($statusCounts?->approved_count ?? 0);

        return [
            Stat::make('In Review', $inReviewCount)
                ->description('Documents currently being reviewed')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Approved', $approvedCount)
                ->description('Documents fully approved')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('Revision Requested', $revisionCount)
                ->description('Documents with revision requests')
                ->color('danger')
                ->icon('heroicon-o-arrow-path'),
        ];
    }
}
