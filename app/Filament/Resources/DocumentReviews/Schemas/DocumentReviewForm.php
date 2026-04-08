<?php

namespace App\Filament\Resources\DocumentReviews\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DocumentReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_id')
                    ->relationship('document', 'title')
                    ->required(),
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('status')
                    ->required(),
                Textarea::make('message')
                    ->columnSpanFull(),
            ]);
    }
}
