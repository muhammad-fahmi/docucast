<?php

namespace App\Filament\Exports;

use App\Models\RevisionHistory;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class RevisionHistoryExporter extends Exporter
{
    protected static ?string $model = RevisionHistory::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('document.unique_code')
                ->label('Document Code'),
            ExportColumn::make('created_at')
                ->label('Date'),
            ExportColumn::make('commenter.name')
                ->label('User'),
            ExportColumn::make('action_type')
                ->label('Action'),
            ExportColumn::make('comments')
                ->label('Details'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your revision history export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
