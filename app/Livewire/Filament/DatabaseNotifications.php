<?php

namespace App\Livewire\Filament;

use Filament\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Illuminate\Notifications\DatabaseNotification;

class DatabaseNotifications extends BaseDatabaseNotifications
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $selectedNotificationData = null;

    public function openNotification(string $id): void
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $this->getNotificationsQuery()
            ->whereKey($id)
            ->first();

        if (!$notification) {
            return;
        }

        if (is_null($notification->read_at)) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        $this->selectedNotificationData = $notification->data;

        $this->dispatch('open-modal', id: 'database-notification-document-detail');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.notifications.database-notifications');
    }
}
