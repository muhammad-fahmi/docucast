<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Services\DocumentRecipientResolver;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploader_id'] = Auth::id();
        $data['status'] = 'pending';

        // Strip virtual recipient fields before saving
        unset($data['recipient_selection_type'], $data['recipient_user_ids'], $data['recipient_division_id']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $state = $this->form->getRawState();
        $recipientIds = app(DocumentRecipientResolver::class)->syncRecipientsFromState($this->record, $state);

        if (count($recipientIds) > 0) {
            $this->record->update(['status' => 'in_review']);

            $recipients = User::query()
                ->whereIn('id', $recipientIds)
                ->get();

            if ($recipients->isNotEmpty()) {
                $dashboardNotification = FilamentNotification::make()
                    ->title('New Document Assigned')
                    ->body(sprintf('A new document "%s" (%s) has been assigned to you.', $this->record->title, $this->record->unique_code))
                    ->info()
                    ->viewData([
                        'detail' => [
                            'document_id' => $this->record->id,
                            'document_title' => $this->record->title,
                            'document_unique_code' => $this->record->unique_code,
                            'review_status' => 'pending',
                            'review_message' => 'You have a new document to review.',
                            'reviewer_name' => null,
                        ],
                    ]);

                $dashboardNotification->sendToDatabase($recipients);
                $dashboardNotification->broadcast($recipients);
            }
        }
    }
}
