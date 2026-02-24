<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// We wrap the document routes in the 'auth' middleware
// so only logged-in users can upload or review documents.
Route::middleware(['auth'])->group(function () {

    // 1. Initial Upload (Triggered by the Uploader)
    Route::post('/documents/upload', [DocumentController::class, 'store'])
        ->name('documents.store');

    // 2. Review Decision: Approve or Reject (Triggered by the Reviewer)
    Route::post('/documents/{document}/review', [DocumentController::class, 'review'])
        ->name('documents.review');

    // 3. Re-upload / Revision (Triggered by the Uploader)
    Route::post('/documents/{document}/reupload', [DocumentController::class, 'reupload'])
        ->name('documents.reupload');

});
