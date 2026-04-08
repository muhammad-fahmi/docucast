@php
    use Filament\Support\Enums\Alignment;
    use Filament\Support\View\Components\BadgeComponent;
    use Illuminate\Support\Str;
    use Illuminate\View\ComponentAttributeBag;

    $notifications = $this->getNotifications();
    $unreadNotificationsCount = $this->getUnreadNotificationsCount();
    $hasNotifications = $notifications->count();
    $isPaginated = $notifications instanceof \Illuminate\Contracts\Pagination\Paginator && $notifications->hasPages();
    $pollingInterval = $this->getPollingInterval();
@endphp

<div class="fi-no-database">
    <x-filament::modal :alignment="$hasNotifications ? null : Alignment::Center" close-button
        :description="$hasNotifications ? null : __('filament-notifications::database.modal.empty.description')"
        :heading="$hasNotifications ? null : __('filament-notifications::database.modal.empty.heading')"
        :icon="$hasNotifications ? null : \Filament\Support\Icons\Heroicon::OutlinedBellSlash" :icon-alias="
            $hasNotifications
            ? null
            : \Filament\Notifications\View\NotificationsIconAlias::DATABASE_MODAL_EMPTY_STATE
        " :icon-color="$hasNotifications ? null : 'gray'" id="database-notifications" slide-over
        :sticky-header="$hasNotifications" teleport="body" width="md" class="fi-no-database" :attributes="
            new ComponentAttributeBag([
            'wire:poll.' . $pollingInterval => $pollingInterval ? '' : false,
        ])
        ">
        @if ($trigger = $this->getTrigger())
            <x-slot name="trigger">
                {{ $trigger->with(['unreadNotificationsCount' => $unreadNotificationsCount]) }}
            </x-slot>
        @endif

        @if ($hasNotifications)
            <x-slot name="header">
                <div>
                    <h2 class="fi-modal-heading">
                        {{ __('filament-notifications::database.modal.heading') }}

                        @if ($unreadNotificationsCount)
                                    <span {{
                            (new ComponentAttributeBag)->color(BadgeComponent::class, 'primary')->class([
                                'fi-badge fi-size-xs',
                            ])
                                                }}>
                                        {{ $unreadNotificationsCount }}
                                    </span>
                        @endif
                    </h2>

                    <div class="fi-ac">
                        @if ($unreadNotificationsCount && $this->markAllNotificationsAsReadAction?->isVisible())
                            {{ $this->markAllNotificationsAsReadAction }}
                        @endif

                        @if ($this->clearNotificationsAction?->isVisible())
                            {{ $this->clearNotificationsAction }}
                        @endif
                    </div>
                </div>
            </x-slot>

            @foreach ($notifications as $notification)
                @php
                    $filamentNotification = $this->getNotification($notification);
                    $notificationBody = trim(strip_tags((string) $filamentNotification->getBody()));
                @endphp

                <button type="button" wire:click="openNotification('{{ $notification->id }}')" @class([
                    'fi-no-notification-read-ctn w-full text-left transition hover:bg-gray-50' => !$notification->unread(),
                    'fi-no-notification-unread-ctn w-full text-left transition hover:bg-primary-50/40' => $notification->unread(),
                ])>
                    <div class="space-y-1">
                        <div class="text-sm font-semibold text-gray-900">
                            {{ $filamentNotification->getTitle() }}
                        </div>

                        @if (filled($notificationBody))
                            <div class="text-sm text-gray-600">
                                {{ Str::limit($notificationBody, 160) }}
                            </div>
                        @endif

                        <div class="text-xs text-gray-500">
                            {{ $filamentNotification->getDate() }}
                        </div>
                    </div>
                </button>
            @endforeach

            @if ($broadcastChannel = $this->getBroadcastChannel())
                @script
                <script>
                    window.addEventListener('EchoLoaded', () => {
                        window.Echo.private(@js($broadcastChannel)).listen(
                            '.database-notifications.sent',
                            () => {
                                setTimeout(
                                    () => $wire.call('$refresh'),
                                    500,
                                )
                            },
                        )
                    })

                    if (window.Echo) {
                        window.dispatchEvent(new CustomEvent('EchoLoaded'))
                    }
                </script>
                @endscript
            @endif

            @if ($isPaginated)
                <x-slot name="footer">
                    <x-filament::pagination :paginator="$notifications" />
                </x-slot>
            @endif
        @endif
    </x-filament::modal>

    <x-filament::modal id="database-notification-document-detail" heading="Document Review Details" width="2xl"
        close-button teleport="body">
        @php
            $details = data_get($this->selectedNotificationData, 'viewData.detail', []);
            $revisionMessage = $details['review_message'] ?? null;
        @endphp

        @if (!empty($details))
            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="font-medium text-gray-500">Document Title</dt>
                    <dd class="text-gray-900">{{ $details['document_title'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Unique Code</dt>
                    <dd class="text-gray-900">{{ $details['document_unique_code'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Reviewer</dt>
                    <dd class="text-gray-900">{{ $details['reviewer_name'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Decision</dt>
                    <dd class="text-gray-900">{{ ucfirst((string) ($details['review_status'] ?? '-')) }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-gray-500">Revision Message</dt>
                    <dd class="rounded-lg bg-gray-50 p-3 text-gray-900">
                        {{ filled($revisionMessage) ? $revisionMessage : 'No revision message provided.' }}
                    </dd>
                </div>
            </dl>
        @else
            <div class="text-sm text-gray-600">
                Notification details are unavailable.
            </div>
        @endif
    </x-filament::modal>
</div>