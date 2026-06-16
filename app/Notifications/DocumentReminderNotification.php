<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class DocumentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Document $document) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail', 'database'];

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
        $limitDate = $this->document->limit_date?->format('Y-m-d') ?? 'N/A';

        return (new MailMessage)
            ->subject('Reminder: Pending Document Review - '.$this->document->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('This is a reminder that you have a pending document review.')
            ->line('Title: '.$this->document->title)
            ->line('Code: '.$this->document->unique_code)
            ->line('Limit Date: '.$limitDate)
            ->action('View Document Dashboard', $url)
            ->line('Please submit your review as soon as possible.');
    }

    /**
     * Get the Telegram representation of the notification.
     */
    public function toTelegram(object $notifiable)
    {
        $limitDate = $this->document->limit_date?->format('Y-m-d') ?? 'N/A';

        return TelegramMessage::create()
            ->to($notifiable->telegram_chat_id)
            ->content("⚠️ *Reminder: Pending Document Review*\n\nYou have a pending document review that is due soon.\n\n*Title:* {$this->document->title}\n*Code:* {$this->document->unique_code}\n*Limit Date:* {$limitDate}\n\n_Please log in and submit your review soon._");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'document_id' => $this->document->id,
            'document_title' => $this->document->title,
            'document_unique_code' => $this->document->unique_code,
            'review_status' => 'pending',
            'review_message' => 'Reminder: This document review is due in 1 day.',
            'reviewer_name' => null,
        ];
    }
}
