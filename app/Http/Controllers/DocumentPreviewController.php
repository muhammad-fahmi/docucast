<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentPreviewController extends Controller
{
    /**
     * @throws FileNotFoundException
     */
    public function __invoke(Document $document): BinaryFileResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        /** @var User $user */
        $isAllowed = $user->hasAnyRole(['super_admin', 'admin'])
            || $document->uploader_id === $user->id
            || $document->recipients()->where('users.id', $user->id)->exists();

        abort_unless($isAllowed, 403);

        $filePath = $document->file_path;
        $fileName = $document->file_name;

        if ($versionNumber = request('version')) {
            $version = $document->versions()->where('version_number', $versionNumber)->first();
            if ($version) {
                $filePath = $version->file_storage_path;
                $fileName = $version->original_filename;
            }
        }

        $disk = Storage::disk(config('filesystems.default'));
        if (! $disk->exists($filePath)) {
            $disk = Storage::disk('public');
            abort_unless($disk->exists($filePath), 404);
        }

        $absolutePath = $disk->path($filePath);
        $mimeType = File::mimeType($absolutePath) ?: 'application/octet-stream';
        $safeFileName = str_replace('"', '', $fileName ?: basename($filePath));

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$safeFileName.'"',
        ]);
    }
}
