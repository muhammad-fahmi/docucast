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
    <x-filament::modal :alignment="$hasNotifications ? null : Alignment::Center" close-button :description="$hasNotifications ? null : __('filament-notifications::database.modal.empty.description')" :heading="$hasNotifications ? null : __('filament-notifications::database.modal.empty.heading')" :icon="$hasNotifications ? null : \Filament\Support\Icons\Heroicon::OutlinedBellSlash"
        :icon-alias="$hasNotifications
            ? null
            : \Filament\Notifications\View\NotificationsIconAlias::DATABASE_MODAL_EMPTY_STATE" :icon-color="$hasNotifications ? null : 'gray'" id="database-notifications" slide-over :sticky-header="$hasNotifications" teleport="body"
        width="md" class="fi-no-database" :attributes="$pollingInterval
            ? new ComponentAttributeBag([
                'wire:poll.' . $pollingInterval => '',
            ])
            : new ComponentAttributeBag()">
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
                            <span
                                {{ new ComponentAttributeBag()->color(BadgeComponent::class, 'primary')->class(['fi-badge fi-size-xs']) }}>
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

                <button type="button" wire:click="openNotification('{{ $notification->id }}')"
                    style="
                        width: 100%;
                        text-align: left;
                        transition: background 0.15s ease;
                        padding: 12px 16px;
                        border-radius: var(--border-radius-md);
                        border: none;
                        cursor: pointer;
                        position: relative;
                    ">

                    <div style="display: flex; align-items: flex-start; gap: 12px;">

                        {{-- Unread dot indicator --}}
                        <div style="padding-top: 5px; flex-shrink: 0;">
                            @if ($notification->unread())
                                <div
                                    style="width: 8px; height: 8px; border-radius: 50%; background: var(--color-text-info);">
                                </div>
                            @else
                                <div style="width: 8px; height: 8px;"></div>
                            @endif
                        </div>

                        <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px;">

                            {{-- Title --}}
                            <div
                                style="
                                    font-size: 0.875rem;
                                    font-weight: 600;
                                    color: var(--color-text-primary);
                                    line-height: 1.4;
                                ">
                                {{ $filamentNotification->getTitle() }}
                            </div>

                            {{-- Body --}}
                            @if (filled($notificationBody))
                                <div
                                    style="
                                    font-size: 0.8125rem;
                                    color: var(--color-text-secondary);
                                    line-height: 1.5;
                                    word-break: break-word;
                                ">
                                    {{ Str::limit($notificationBody, 160) }}
                                </div>
                            @endif

                            {{-- Date --}}
                            <div
                                style="
                                font-size: 0.75rem;
                                color: var(--color-text-tertiary);
                                margin-top: 2px;
                            ">
                                {{ $filamentNotification->getDate() }}
                            </div>

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
                    <dd class="dark:text-white">{{ $details['document_title'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Unique Code</dt>
                    <dd class="dark:text-white">{{ $details['document_unique_code'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Reviewer</dt>
                    <dd class="dark:text-white">{{ $details['reviewer_name'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500">Decision</dt>
                    <dd class="dark:text-white">{{ ucfirst((string) ($details['review_status'] ?? '-')) }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="font-medium text-gray-500">Revision Message</dt>
                    <dd class="dark:text-white">
                        {{ filled($revisionMessage) ? $revisionMessage : 'No revision message provided.' }}
                    </dd>
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <a href="/admin/documents/{{ $details['document_id'] }}/review">
                        <button
                            style="padding: 8px 16px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 12px;">
                            Detail
                        </button>
                    </a>
                </div>
            </dl>
        @else
            <div class="text-sm text-gray-600">
                Notification details are unavailable.
            </div>
        @endif
    </x-filament::modal>
</div>
