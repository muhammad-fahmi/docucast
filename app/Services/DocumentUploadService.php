<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\RevisionHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentUploadService
{
    public function __construct(
        private DocumentStatusService $statusService,
    ) {
    }

    /**
     * Store initial document upload
     */
    public function storeInitialUpload(
        UploadedFile $file,
        string $title,
        string $description,
        User $uploader,
    ): Document {
        return DB::transaction(function () use ($file, $title, $description, $uploader) {
            // 1. Store file
            $filePath = $this->storeFile($file);

            // 2. Create document
            $document = Document::create([
                'title' => $title,
                'description' => $description,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'uploader_id' => $uploader->id,
                'status' => 'pending',
            ]);

            // 3. Create version
            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'file_storage_path' => $filePath,
                'original_filename' => $file->getClientOriginalName(),
                'uploaded_by' => $uploader->id,
            ]);

            // 4. Record history
            RevisionHistory::create([
                'document_id' => $document->id,
                'related_version_id' => $document->versions()->first()->id,
                'commenter_id' => $uploader->id,
                'action_type' => 'SUBMITTED',
                'comments' => 'Initial document upload.',
            ]);

            return $document;
        });
    }

    /**
     * Store revised document upload
     */
    public function storeRevisionUpload(
        Document $document,
        UploadedFile $file,
        User $uploader,
    ): DocumentVersion {
        return DB::transaction(function () use ($document, $file, $uploader) {
            // 1. Verify document needs revision
            if ($document->status !== 'revision') {
                throw new \InvalidArgumentException('Document does not require revision');
            }

            // 2. Store file
            $filePath = $this->storeFile($file);

            // 3. Get next version number
            $nextVersionNumber = $document->versions()->max('version_number') + 1;

            // 4. Create new version
            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $nextVersionNumber,
                'file_storage_path' => $filePath,
                'original_filename' => $file->getClientOriginalName(),
                'uploaded_by' => $uploader->id,
            ]);

            // 5. Update document status to in_review
            $document->update(['status' => 'in_review']);

            // 6. Record history
            RevisionHistory::create([
                'document_id' => $document->id,
                'related_version_id' => $version->id,
                'commenter_id' => $uploader->id,
                'action_type' => 'RESUBMITTED',
                'comments' => "Uploaded revision v{$nextVersionNumber}",
            ]);

            return $version;
        });
    }

    /**
     * Store file physically
     */
    private function storeFile(UploadedFile $file): string
    {
        return $file->store('documents', config('filesystems.default'));
    }

    /**
     * Delete document file(s)
     */
    public function deleteDocumentFiles(Document $document): void
    {
        $document->versions()->each(function (DocumentVersion $version): void {
            if (Storage::exists($version->file_storage_path)) {
                Storage::delete($version->file_storage_path);
            }
        });
    }
}
