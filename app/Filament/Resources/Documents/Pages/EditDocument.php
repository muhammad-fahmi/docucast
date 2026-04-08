<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Services\DocumentRecipientResolver;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $existingRecipientIds = $this->record->recipients()->pluck('users.id')->toArray();
        $data['recipient_selection_type'] = 'individual';
        $data['recipient_user_ids'] = $existingRecipientIds;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['recipient_selection_type'], $data['recipient_user_ids'], $data['recipient_division_id']);

        return $data;
    }

    protected function afterSave(): void
    {
        $state = $this->form->getRawState();
        app(DocumentRecipientResolver::class)->syncRecipientsFromState($this->record, $state);

        $this->record->refresh();
        $this->record->updateStatusBasedOnReviews();
    }
}
