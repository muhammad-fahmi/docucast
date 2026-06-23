<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Override;

use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class DocumentDetail extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = DocumentResource::class;

    protected string $view = 'filament.resources.documents.pages.document-detail';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(Auth::user()?->can('view', $this->record), 403);

        $this->record->load(['uploader', 'versions.uploader']);
    }

    public function getTitle(): string
    {
        return 'Document Detail: ' . $this->record->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to List')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(static::getResource()::getUrl('index')),
        ];
    }
}
