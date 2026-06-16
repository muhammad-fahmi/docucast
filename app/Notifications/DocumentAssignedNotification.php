<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class DocumentAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $document;

    /**
     * Create a new notification instance.
     */
    public function __construct(Document $document)
    {
        $this->document = $document;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (! empty($notifiable->telegram_chat_id)) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/admin/documents');

        return (new MailMessage)
            ->subject('New Document Assigned: '.$this->document->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('You have been assigned a new document to review.')
            ->line('Title: '.$this->document->title)
            ->line('Code: '.$this->document->unique_code)
            ->action('View Document Dashboard', $url)
            ->line('Thank you for using our application!');
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->to($notifiable->telegram_chat_id)
            ->content("📄 *New Document Assigned*\n\nYou have been assigned a new document to review.\n\n*Title:* {$this->document->title}\n*Code:* {$this->document->unique_code}\n\n_Log in to your dashboard to view it._");
    }
}
