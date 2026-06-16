<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\User;
use App\Notifications\DocumentAssignedNotification;
use App\Services\DocumentRecipientResolver;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
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
        unset($data['recipient_selection_type'], $data['recipient_user_ids'], $data['recipient_division_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $state = $this->form->getRawState();
        $newRecipientIds = app(DocumentRecipientResolver::class)->syncRecipientsFromState($this->record, $state);

        if (count($newRecipientIds) > 0) {
            $recipients = User::query()
                ->whereIn('id', $newRecipientIds)
                ->get();

            if ($recipients->isNotEmpty()) {
                $dashboardNotification = Notification::make()
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
                try {
                    $dashboardNotification->broadcast($recipients);
                } catch (\Exception $e) {
                    // Ignore broadcast exceptions
                }

                // Send Notification (Mail, Telegram)
                foreach ($recipients as $recipient) {
                    $recipient->notify(new DocumentAssignedNotification($this->record));
                }
            }
        }

        $this->record->refresh();
        $this->record->updateStatusBasedOnReviews();
    }
}
