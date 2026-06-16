<?php

namespace App\Filament\Resources\DocumentVersionResource\Pages;

use App\Filament\Resources\DocumentVersionResource;
use App\Models\DocumentVersion;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Shuchkin\SimpleXLSXGen;

class ListDocumentVersions extends ListRecords
{
    protected static string $resource = DocumentVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_xlsx')
                ->label('Export XLSX')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $records = DocumentVersion::with(['document', 'uploader'])->get();
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
                }),
            Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $records = DocumentVersion::with(['document', 'uploader'])->get();
                    $pdf = Pdf::loadView('exports.document_versions_pdf', ['records' => $records]);

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'document_versions_export_'.now()->format('Y_m_d_His').'.pdf');
                }),
        ];
    }
}
