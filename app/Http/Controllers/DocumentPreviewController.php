<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
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

        if (!$user) {
            abort(401);
        }

        /** @var \App\Models\User $user */

        $isAllowed = $user->hasAnyRole(['super_admin', 'admin'])
            || $document->uploader_id === $user->id
            || $document->recipients()->where('users.id', $user->id)->exists();

        abort_unless($isAllowed, 403);

        $disk = Storage::disk(config('filesystems.default'));
        abort_unless($disk->exists($document->file_path), 404);

        $absolutePath = $disk->path($document->file_path);
        $mimeType = File::mimeType($absolutePath) ?: 'application/octet-stream';
        $safeFileName = str_replace('"', '', $document->file_name ?: basename($document->file_path));

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $safeFileName . '"',
        ]);
    }
}
