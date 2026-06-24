<?php

namespace App\Filament\Resources\DocumentVersions\Tables;

use App\Models\DocumentVersion;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DocumentVersionsTable
{
    public static function configure(Table $table): Table
    {
        $user = Auth::user();

        $table = $table
            ->columns([
                TextColumn::make('document.title')
                    ->label('Document Title')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('version_number')
                    ->label('Version')
                    ->badge()
                    ->sortable(),

                TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->icon('heroicon-o-document')
                    ->url(fn (DocumentVersion $record): string => route('documents.preview', [
                        'document' => $record->document_id,
                        'version' => $record->version_number,
                    ]))
                    ->openUrlInNewTab(),

                TextColumn::make('document.uploader.name')
                    ->label('Uploader')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => $user->hasAnyRole(['super_admin', 'admin', 'recipient'])),

                TextColumn::make('document.recipients.name')
                    ->label('Recipients')
                    ->badge()
                    ->searchable()
                    ->visible(fn () => $user->hasAnyRole(['super_admin', 'admin', 'uploader'])),

                TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // We typically just want to view or download from here, Edit might not be needed for Version List
                // We'll keep it empty or maybe a View action if you want.
            ])
            ->toolbarActions([
                //
            ]);

        // Group by Uploader if the user is a recipient
        // if ($user->hasRole('recipient') && !$user->hasAnyRole(['super_admin', 'admin'])) {
        //     $table->defaultGroup('document.uploader.name');
        // }

        return $table;
    }
}
