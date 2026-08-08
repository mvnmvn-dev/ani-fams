<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RetentionAssignment;
use App\Services\NotificationService;
use Carbon\Carbon;

class CheckRetentionDisposals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retention:check-disposals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for records due for retention disposal and notify staff';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $this->info('Checking for due retention assignments...');

        // Find assignments due within 7 days or overdue, status active
        $dueAssignments = RetentionAssignment::where('status', 'active')
            ->where('calculated_disposal_date', '<=', Carbon::now()->addDays(7))
            ->with(['document.owner'])
            ->get();

        $count = 0;
        foreach ($dueAssignments as $assignment) {
            $assignment->update(['status' => 'due_for_review']);

            // Notify document owner or staff
            if ($assignment->document && $assignment->document->owner) {
                $notificationService->send(
                    $assignment->document->owner,
                    'retention',
                    "Action Required: Document Retention Disposal",
                    "The document '{$assignment->document->title}' is due for retention disposal/review on {$assignment->calculated_disposal_date}.",
                    "/retention", // Assuming a retention list route
                    true
                );
            }
            $count++;
        }

        $this->info("Flagged {$count} assignments for review.");
    }
}
