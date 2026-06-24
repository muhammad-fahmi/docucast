<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            filament()->getUrl() => 'Dashboard',
            DocumentResource::getUrl('index') => 'Dokumen',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-s-plus')
                ->label('Dokumen Baru')
                ->visible(function () {
                    $user = Auth::user();
                    $roles = $user->roles->pluck('name')->toArray();

                    return ! (count($roles) === 1 && in_array('recipient', $roles));
                }),
        ];
    }

    public function getListeners(): array
    {
        if (empty(config('filament.broadcasting.echo'))) {
            return [];
        }

        $authId = Auth::id();

        return [
            "echo-private:App.Models.User.{$authId},.database-notifications.sent" => '$refresh',
        ];
    }
}
