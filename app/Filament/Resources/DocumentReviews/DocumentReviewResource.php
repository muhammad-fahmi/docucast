<?php

namespace App\Filament\Resources\DocumentReviews;

use App\Filament\Resources\DocumentReviews\Pages\ListDocumentReviews;
use App\Filament\Resources\DocumentReviews\Schemas\DocumentReviewForm;
use App\Filament\Resources\DocumentReviews\Tables\DocumentReviewsTable;
use App\Models\DocumentReview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DocumentReviewResource extends Resource
{
    protected static ?string $model = DocumentReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 3;

    protected static bool $shouldSkipAuthorization = true;

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()->hasAnyRole(['super_admin', 'admin']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentReviewForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentReviewsTable::configure($table);
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
            'index' => ListDocumentReviews::route('/'),
        ];
    }
}
