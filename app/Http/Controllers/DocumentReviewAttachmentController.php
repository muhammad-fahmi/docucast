<?php

namespace App\Http\Controllers;

use App\Models\DocumentReview;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentReviewAttachmentController extends Controller
{
    public function __invoke(DocumentReview $review): StreamedResponse
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

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($review->attachment_path)) {
            $disk = Storage::disk('public');
            abort_unless($disk->exists($review->attachment_path), 404);
        }

        $fileName = $review->attachment_name ?: basename($review->attachment_path);

        return $disk->download($review->attachment_path, $fileName);
    }
}
