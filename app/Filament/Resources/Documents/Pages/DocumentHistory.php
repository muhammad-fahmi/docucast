<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Exports\RevisionHistoryExporter;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\RevisionHistory;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class DocumentHistory extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = DocumentResource::class;

    protected static ?string $title = 'Document History';

    protected string $view = 'filament.resources.documents.pages.document-history';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(RevisionHistory::query()->where('document_id', $this->record->id)->latest())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y H:i:s')
                    ->label('Date')
                    ->sortable(),
                Tables\Columns\TextColumn::make('commenter.name')
                    ->label('User')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('action_type')
                    ->label('Action')
                    ->badge(),
                Tables\Columns\TextColumn::make('comments')
                    ->label('Details')
                    ->wrap(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(RevisionHistoryExporter::class),
            ]);
    }
}
