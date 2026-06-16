<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Models\DocumentVersion;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Riwayat Versi Dokumen';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Read-only, no form needed
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version_number')
            ->columns([
                Tables\Columns\TextColumn::make('version_number')
                    ->label('Versi')
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => 'v'.$state),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Nama File')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Diunggah Oleh')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                // Add a note about the 2 year retention as requested
                Tables\Columns\TextColumn::make('retention_info')
                    ->label('Status Retensi')
                    ->state(function ($record) {
                        $maxVersion = DocumentVersion::where('document_id', $record->document_id)->max('version_number');
                        if ($record->version_number !== $maxVersion) {
                            return '-';
                        }

                        $expirationDate = $record->created_at->copy()->addYears(2);
                        if (now()->greaterThanOrEqualTo($expirationDate)) {
                            return 'Expired (Perlu upload ulang)';
                        }

                        $diff = now()->diff($expirationDate);
                        $parts = [];
                        if ($diff->y > 0) {
                            $parts[] = $diff->y.' tahun';
                        }
                        if ($diff->m > 0) {
                            $parts[] = $diff->m.' bulan';
                        }
                        if ($diff->d > 0) {
                            $parts[] = $diff->d.' hari';
                        }

                        if (empty($parts)) {
                            return 'Aktif (kurang dari 1 hari lagi)';
                        }

                        return 'Aktif ('.implode(', ', $parts).' lagi)';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $maxVersion = DocumentVersion::where('document_id', $record->document_id)->max('version_number');
                        if ($record->version_number !== $maxVersion) {
                            return 'gray';
                        }

                        $expirationDate = $record->created_at->copy()->addYears(2);

                        return now()->greaterThanOrEqualTo($expirationDate) ? 'danger' : 'success';
                    }),
            ])
            ->defaultSort('version_number', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                // No create action, handled automatically
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($record) {
                        if (Storage::disk('public')->exists($record->file_storage_path)) {
                            return Storage::disk('public')->download(
                                $record->file_storage_path,
                                $record->original_filename ?? 'document_v'.$record->version_number
                            );
                        }
                    })
                    ->visible(fn ($record) => Storage::disk('public')->exists($record->file_storage_path)),
            ])
            ->bulkActions([
                // None
            ]);
    }
}
