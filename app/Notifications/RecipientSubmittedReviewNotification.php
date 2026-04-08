<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\DocumentReview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecipientSubmittedReviewNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Document $document,
        protected DocumentReview $review,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $reviewerName = $this->review->reviewer?->name ?? 'A reviewer';

        $mail = (new MailMessage)
            ->subject("Document Review: {$this->document->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$reviewerName} has submitted a review for your document.")
            ->line("**Document:** {$this->document->title}")
            ->line("**Unique Code:** {$this->document->unique_code}")
            ->line("**Decision:** " . ucfirst($this->review->status))
            ->when($this->review->message, function (MailMessage $mailMessage): MailMessage {
                return $mailMessage->line("**Message:**")
                    ->line($this->review->message);
            });

        // Add action link if the route exists
        try {
            $mail->action('View Document', route('filament.app.resources.documents.index'));
        } catch (\Exception) {
            // Route doesn't exist in test environment, skip the action
        }

        return $mail->line('Thank you for using our document management system!');
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
            'reviewer_id' => $this->review->user_id,
            'reviewer_name' => $this->review->reviewer?->name,
            'review_status' => $this->review->status,
            'review_message' => $this->review->message,
            'review_id' => $this->review->id,
        ];
    }
}
