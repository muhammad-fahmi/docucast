<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\User;
use App\Notifications\DocumentAssignedNotification;
use App\Services\DocumentRecipientResolver;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected static ?string $title = 'Detail Dokumen';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Hapus')->icon('heroicon-o-trash'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Simpan Perubahan')->icon('heroicon-o-clipboard-document-check'),
            $this->getCancelFormAction()->label("Batal")->icon('heroicon-o-x-circle'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
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

        // Notify newly added recipients
        if (count($newRecipientIds) > 0) {
            $recipients = User::query()
                ->whereIn('id', $newRecipientIds)
                ->get();

            if ($recipients->isNotEmpty()) {
                $dashboardNotification = Notification::make()
                    ->title('New Document Assigned')
                    ->body(sprintf('A new document "%s" (%s) has been assigned to you.', $this->record->title, $this->record->unique_code))
                    ->info();

                $dashboardNotification->sendToDatabase($recipients);
                try {
                    $dashboardNotification->broadcast($recipients);
                } catch (\Exception $e) {
                    // Ignore broadcast exceptions
                }

                foreach ($recipients as $recipient) {
                    $recipient->notify(new DocumentAssignedNotification($this->record));
                }
            }
        }

        // Notify ALL current recipients when a new file version is uploaded
        if ($this->record->wasChanged('file_path')) {
            $allRecipients = $this->record->recipients()->get();

            if ($allRecipients->isNotEmpty()) {
                $dashboardNotification = Notification::make()
                    ->title('Document Updated')
                    ->body(sprintf('Document "%s" (%s) has been updated with a new version. Please review.', $this->record->title, $this->record->unique_code))
                    ->info();

                $dashboardNotification->sendToDatabase($allRecipients);
                try {
                    $dashboardNotification->broadcast($allRecipients);
                } catch (\Exception $e) {
                    // Ignore broadcast exceptions
                }

                foreach ($allRecipients as $recipient) {
                    $recipient->notify(new DocumentAssignedNotification($this->record));
                }
            }
        }

        $this->record->refresh();
        $this->record->updateStatusBasedOnReviews();
    }
}
