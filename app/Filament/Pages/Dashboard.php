<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DocumentResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use JibayMcs\FilamentTour\Tour\HasTour;
use JibayMcs\FilamentTour\Tour\Step;
use JibayMcs\FilamentTour\Tour\Tour;

class Dashboard extends BaseDashboard
{
    use HasTour;

    public function tours(): array
    {
        return [
            Tour::make('dashboard_tour')
                ->steps(
                    Step::make('.fi-sidebar-item:has(a[href*="/documents"])')
                        ->title('Menu Dokumen')
                        ->description('Klik disini untuk membuat dokumen/melihat dokumen'),
                    Step::make('a[href*="/documents/create"]')
                        ->title('Buat Dokumen Baru')
                        ->description('Untuk membuat dokumen baru, klik tombol ini.')
                )
                ->nextButtonLabel('Next')
                ->previousButtonLabel('Previous'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_document')
                ->label('New document')
                ->url(DocumentResource::getUrl('create'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
