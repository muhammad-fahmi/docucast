<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Schemas\DocumentForm;
use App\Filament\Resources\Documents\Tables\DocumentsTable;
use App\Models\Document;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    protected static bool $shouldSkipAuthorization = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Documents';
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return Auth::check() && Auth::user()->hasAnyRole(['super_admin', 'admin', 'uploader']);
    }

    public static function canEdit(Model $record): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();

        return $user->hasAnyRole(['super_admin', 'admin']) || $record->uploader_id === $user->id;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getEloquentQuery(): Builder
    {
        if (!Auth::check()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        $user = Auth::user();
        $query = parent::getEloquentQuery();

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return $query;
        }

        $userId = $user->id;

        return $query->where(function (Builder $q) use ($userId): void {
            $q->where('uploader_id', $userId)
                ->orWhereHas('recipients', fn(Builder $rq) => $rq->where('users.id', $userId));
        });
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
