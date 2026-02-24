<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\DocumentApproval;
use App\Models\DocumentVersion;
use App\Models\RevisionHistory;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;
    // ADD THIS METHOD to change the redirect destination
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        return DB::transaction(function () use ($data, $user) {
            // 1. Create the Master Document
            $document = static::getModel()::create([
                'title' => $data['title'],
                'initiator_id' => $user->id,
                'overall_status' => 'PENDING_REVIEW',
            ]);

            // 2. Create the Version entry linking to the physical file
            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_storage_path' => $data['document_file'],
                'uploaded_by' => $user->id,
            ]);

            // 3. THE FIX: Loop through the new 'reviewers' array
            // It will create a separate inbox entry for every selected supervisor
            foreach ($data['reviewers'] as $reviewerId) {
                DocumentApproval::create([
                    'document_id' => $document->id,
                    'reviewer_id' => $reviewerId, // Assigns the current ID from the loop
                    'status' => 'PENDING',
                ]);
            }

            // 4. Record the Audit Trail
            RevisionHistory::create([
                'document_id' => $document->id,
                'related_version_id' => $version->id,
                'commenter_id' => $user->id,
                'action_type' => 'SUBMITTED',
                'comments' => 'Initial document upload.',
            ]);

            return $document;
        });
    }
}
