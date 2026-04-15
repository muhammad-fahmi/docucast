<?php

use App\Http\Controllers\DocumentPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/documents/{document}/preview', DocumentPreviewController::class)
        ->name('documents.preview');
});
