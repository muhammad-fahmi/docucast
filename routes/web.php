<?php

use App\Http\Controllers\DocumentPreviewController;
use App\Http\Controllers\DocumentReviewAttachmentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/documents/{document}/preview', DocumentPreviewController::class)
        ->name('documents.preview');

    Route::get('/reviews/{review}/attachment', DocumentReviewAttachmentController::class)
        ->name('filament.documents.attachment.download');
});
