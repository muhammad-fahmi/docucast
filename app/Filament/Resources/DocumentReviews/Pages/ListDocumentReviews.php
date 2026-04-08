<?php

namespace App\Filament\Resources\DocumentReviews\Pages;

use App\Filament\Resources\DocumentReviews\DocumentReviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentReviews extends ListRecords
{
    protected static string $resource = DocumentReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
