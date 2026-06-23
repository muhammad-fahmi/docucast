<?php

namespace App\Filament\Resources\DocumentVersions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class DocumentVersionsTable
{
    public static function configure(Table $table): Table
    {
        $user = auth()->user();

        $table = $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('document.title')
                    ->label('Document Title')
                    ->searchable()
                    ->sortable(),
                
                \Filament\Tables\Columns\TextColumn::make('version_number')
                    ->label('Version')
                    ->badge()
                    ->sortable(),

                \Filament\Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->icon('heroicon-o-document')
                    ->url(fn (\App\Models\DocumentVersion $record): string => \Illuminate\Support\Facades\Storage::disk('public')->url($record->file_storage_path))
                    ->openUrlInNewTab(),

                \Filament\Tables\Columns\TextColumn::make('document.uploader.name')
                    ->label('Uploader')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => auth()->user()->hasAnyRole(['super_admin', 'admin', 'recipient'])),

                \Filament\Tables\Columns\TextColumn::make('document.recipients.name')
                    ->label('Recipients')
                    ->badge()
                    ->searchable()
                    ->visible(fn () => auth()->user()->hasAnyRole(['super_admin', 'admin', 'uploader'])),

                \Filament\Tables\Columns\TextColumn::make('created_at')
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
        if ($user->hasRole('recipient') && !$user->hasAnyRole(['super_admin', 'admin'])) {
            $table->defaultGroup('document.uploader.name');
        }

        return $table;
    }
}
