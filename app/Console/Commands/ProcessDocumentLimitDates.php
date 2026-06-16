<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Notifications\DocumentReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessDocumentLimitDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:process-limit-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process auto-approvals and send reminder notifications for documents approaching or past their limit dates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();

        $this->info("Processing limit dates for {$today}...");

        // 1. Process Auto-Approvals
        $autoApproveDocuments = Document::query()
            ->where('auto_approve', true)
            ->whereIn('status', ['pending', 'in_review'])
            ->whereDate('limit_date', '<=', $today)
            ->get();

        $this->info('Found '.$autoApproveDocuments->count().' documents for auto-approval.');

        foreach ($autoApproveDocuments as $document) {
            $reviewedUserIds = $document->reviews()->pluck('user_id')->toArray();
            $pendingRecipients = $document->recipients()
                ->whereNotIn('users.id', $reviewedUserIds)
                ->get();

            if ($pendingRecipients->isNotEmpty()) {
                $this->line("Auto-approving document: {$document->title} ({$document->unique_code}) for ".$pendingRecipients->count().' pending recipients.');

                foreach ($pendingRecipients as $recipient) {
                    $document->reviews()->create([
                        'user_id' => $recipient->id,
                        'status' => 'approved',
                        'message' => 'Auto-approved by system',
                    ]);
                }

                $document->updateStatusBasedOnReviews();
            } else {
                // If there were no pending recipients but the document is still in_review/pending,
                // we should still update its status just in case.
                $document->updateStatusBasedOnReviews();
            }
        }

        // 2. Process Reminders (1 day before limit date)
        $reminderDocuments = Document::query()
            ->whereNotNull('limit_date')
            ->whereDate('limit_date', $tomorrow)
            ->whereIn('status', ['pending', 'in_review'])
            ->get();

        $this->info('Found '.$reminderDocuments->count().' documents due tomorrow for reminder notifications.');

        foreach ($reminderDocuments as $document) {
            $reviewedUserIds = $document->reviews()->pluck('user_id')->toArray();
            $pendingRecipients = $document->recipients()
                ->whereNotIn('users.id', $reviewedUserIds)
                ->get();

            if ($pendingRecipients->isNotEmpty()) {
                $this->line("Sending reminder notifications for: {$document->title} ({$document->unique_code}) to ".$pendingRecipients->count().' pending recipients.');

                foreach ($pendingRecipients as $recipient) {
                    $recipient->notify(new DocumentReminderNotification($document));
                }
            }
        }

        $this->info('Limit dates processing complete.');

        return 0;
    }
}
