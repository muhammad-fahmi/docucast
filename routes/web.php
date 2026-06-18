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

    Route::get('/reviews/{review}/attachment/download', [DocumentReviewAttachmentController::class, 'download'])
        ->name('filament.documents.attachment.download');

    Route::get('/reviews/{review}/attachment/preview', [DocumentReviewAttachmentController::class, 'preview'])
        ->name('filament.documents.attachment.preview');
});
