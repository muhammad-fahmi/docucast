<?php

namespace App\Filament\Resources\DocumentVersions;

use App\Filament\Resources\DocumentVersions\Pages\ListDocumentVersions;
use App\Filament\Resources\DocumentVersions\Schemas\DocumentVersionForm;
use App\Filament\Resources\DocumentVersions\Tables\DocumentVersionsTable;
use App\Models\DocumentVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentVersionResource extends Resource
{
    protected static ?string $model = DocumentVersion::class;

    protected static ?string $pluralModelLabel = 'Semua Versi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return 'Dokumen';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return $query;
        }

        if ($user->hasRole('uploader')) {
            return $query->whereHas('document', function ($q) use ($user) {
                $q->where('uploader_id', $user->id);
            });
        }

        if ($user->hasRole('recipient')) {
            return $query->whereHas('document.recipients', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentVersionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentVersionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentVersions::route('/'),
        ];
    }
}
