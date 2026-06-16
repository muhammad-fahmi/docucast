<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentVersionResource\Pages;
use App\Models\DocumentVersion;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Shuchkin\SimpleXLSXGen;

class DocumentVersionResource extends Resource
{
    protected static ?string $model = DocumentVersion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Version List';

    protected static ?string $modelLabel = 'Document Version';

    protected static ?string $slug = 'version-list';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document.title')
                    ->label('Document')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->label('Version')
                    ->sortable(),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File Name')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('export_xlsx')
                        ->label('Export XLSX')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(fn ($records) => static::exportXlsx($records))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('export_pdf')
                        ->label('Export PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(fn ($records) => static::exportPdf($records))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentVersions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Prevent creating versions directly
    }

    protected static function exportXlsx($records)
    {
        $data = [
            ['Document', 'Version', 'File Name', 'Uploaded By', 'Date'],
        ];

        foreach ($records as $record) {
            $data[] = [
                $record->document?->title ?? '-',
                $record->version_number,
                $record->original_filename,
                $record->uploader?->name ?? '-',
                $record->created_at?->format('Y-m-d H:i:s') ?? '-',
            ];
        }

        $xlsx = SimpleXLSXGen::fromArray($data);
        $fileName = 'document_versions_export_'.now()->format('Y_m_d_His').'.xlsx';

        return response()->streamDownload(function () use ($xlsx) {
            echo (string) $xlsx;
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected static function exportPdf($records)
    {
        $pdf = Pdf::loadView('exports.document_versions_pdf', ['records' => $records]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'document_versions_export_'.now()->format('Y_m_d_His').'.pdf');
    }
}
