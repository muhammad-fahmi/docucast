<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        // Fetch ALL assigned reviewers as an array of IDs
        $data['reviewers'] = $this->record->approvals()->pluck('reviewer_id')->toArray();

        $latestVersion = $this->record->latestVersion;
        if ($latestVersion) {
            $data['document_file'] = $latestVersion->file_storage_path;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        DB::transaction(function () use ($record, $data) {

            $record->update([
                'title' => $data['title'],
            ]);

            // Sync the multiple reviewers
            if (isset($data['reviewers'])) {
                $existingReviewers = $record->approvals()->pluck('reviewer_id')->toArray();
                $newReviewers = $data['reviewers'];

                // Find reviewers to add
                $toAdd = array_diff($newReviewers, $existingReviewers);
                foreach ($toAdd as $id) {
                    \App\Models\DocumentApproval::create([
                        'document_id' => $record->id,
                        'reviewer_id' => $id,
                        'status' => 'PENDING'
                    ]);
                }

                // Find reviewers to remove
                $toRemove = array_diff($existingReviewers, $newReviewers);
                if (!empty($toRemove)) {
                    $record->approvals()->whereIn('reviewer_id', $toRemove)->delete();
                }
            }

            $latestVersion = $record->latestVersion;
            if ($latestVersion && isset($data['document_file'])) {
                $latestVersion->update([
                    'file_storage_path' => $data['document_file']
                ]);
            }
        });

        return $record;
    }
}
