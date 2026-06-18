<?php

namespace App\Http\Controllers;

use App\Models\DocumentReview;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentReviewAttachmentController extends Controller
{
    public function download(DocumentReview $review): StreamedResponse
    {
        $this->authorize($review);

        [$disk, $fileName] = $this->resolve($review);

        return $disk->download($review->attachment_path, $fileName);
    }

    public function preview(DocumentReview $review): StreamedResponse
    {
        $this->authorize($review);

        [$disk, $fileName] = $this->resolve($review);

        $mimeType = $disk->mimeType($review->attachment_path);
        $stream = $disk->readStream($review->attachment_path);

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);
            },
            200,
            [
                'Content-Type'        => $mimeType,
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'Cache-Control'       => 'no-cache, private',
            ]
        );
    }

    private function authorize(DocumentReview $review): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user, 401);

        $document = $review->document;

        $isAllowed = $user->hasAnyRole(['super_admin', 'admin'])
            || $document->uploader_id === $user->id
            || $document->recipients()->where('users.id', $user->id)->exists();

        abort_unless($isAllowed, 403);
        abort_unless(filled($review->attachment_path), 404);
    }

    private function resolve(DocumentReview $review): array
    {
        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($review->attachment_path)) {
            $disk = Storage::disk('public');
            abort_unless($disk->exists($review->attachment_path), 404);
        }

        $fileName = $review->attachment_name ?: basename($review->attachment_path);

        return [$disk, $fileName];
    }
}
