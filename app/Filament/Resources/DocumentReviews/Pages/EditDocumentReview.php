<?php

namespace App\Filament\Resources\DocumentReviews\Pages;

use App\Filament\Resources\DocumentReviews\DocumentReviewResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumentReview extends EditRecord
{
    protected static string $resource = DocumentReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
