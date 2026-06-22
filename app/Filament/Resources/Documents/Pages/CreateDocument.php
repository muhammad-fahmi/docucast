<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\User;
use App\Notifications\DocumentAssignedNotification;
use App\Services\DocumentRecipientResolver;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploader_id'] = Auth::id();
        $data['status'] = 'pending';

        // Strip virtual recipient fields before saving
        unset($data['recipient_selection_type'], $data['recipient_user_ids'], $data['recipient_division_ids']);

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
                    ->body(sprintf('A new document "%s" has been assigned to you.', $this->record->title,))
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
                try {
                    $dashboardNotification->broadcast($recipients);
                } catch (\Exception $e) {
                    // Ignore broadcast exceptions if Reverb/Pusher is down
                }

                // Send Notification
                foreach ($recipients as $recipient) {
                    $recipient->notify(new DocumentAssignedNotification($this->record));
                }
            }
        }
    }
}
