<?php

namespace App\Filament\Resources\Documents\Widgets;

use App\Models\Document;
use App\Models\DocumentVersion;
use Devletes\FilamentTimelineView\Tables\Columns\TimelineEntry;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

class DocumentTimelineWidget extends TableWidget
{
    public ?Document $record = null;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        if (! $this->record) {
            // Return empty query if no record is loaded
            return $table->query(DocumentVersion::query()->whereRaw('1 = 0'));
        }

        $versionsQuery = DB::table('document_versions')
            ->join('users', 'document_versions.uploaded_by', '=', 'users.id')
            ->select(
                'document_versions.id',
                'document_versions.created_at',
                DB::raw("'version' as event_type"),
                'document_versions.version_number',
                DB::raw('NULL as action_type'),
                'users.name as user_name',
                'document_versions.original_filename as content',
                'document_versions.document_id'
            )
            ->where('document_versions.document_id', $this->record->id);

        $revisionsQuery = DB::table('revision_histories')
            ->join('users', 'revision_histories.commenter_id', '=', 'users.id')
            ->select(
                'revision_histories.id',
                'revision_histories.created_at',
                DB::raw("'revision' as event_type"),
                'revision_histories.related_version_id as version_number',
                'revision_histories.action_type',
                'users.name as user_name',
                'revision_histories.comments as content',
                'revision_histories.document_id'
            )
            ->where('revision_histories.document_id', $this->record->id);

        $reviewsQuery = DB::table('document_reviews')
            ->join('users', 'document_reviews.user_id', '=', 'users.id')
            ->select(
                'document_reviews.id',
                'document_reviews.updated_at as created_at',
                DB::raw("'review' as event_type"),
                DB::raw('NULL as version_number'),
                'document_reviews.status as action_type',
                'users.name as user_name',
                'document_reviews.message as content',
                'document_reviews.document_id'
            )
            ->where('document_reviews.document_id', $this->record->id);

        $unionQuery = $versionsQuery->union($revisionsQuery)->union($reviewsQuery);

        $query = DocumentVersion::query()->fromSub($unionQuery, 'events');
        $query->getModel()->setTable('events');

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->heading('Riwayat & Aktivitas Dokumen')
            ->columns([
                TimelineEntry::make()
                    ->title(fn ($record) => match ($record->event_type) {
                        'version' => 'Diunggah (Versi '.($record->version_number ? "v{$record->version_number}" : 'Baru').')',
                        'revision' => "Revisi ({$record->action_type})",
                        'review' => $record->action_type === 'approved' ? 'Dokumen Disetujui' : 'Meminta Revisi',
                        default => 'Aktivitas',
                    })
                    ->content('content')
                    ->author('user_name')
                    ->time('created_at'),
            ])
            ->defaultGroup(
                Group::make('created_at')
                    ->date()
                    ->collapsible()
                    ->orderQueryUsing(fn ($query) => $query->orderByDesc('created_at'))
            )
            ->asDoubleSidedTimeline();
    }
}
