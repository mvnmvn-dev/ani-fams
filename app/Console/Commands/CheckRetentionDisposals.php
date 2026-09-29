<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RetentionAssignment;
use App\Services\NotificationService;
use App\Support\AutomationLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckRetentionDisposals extends Command
{
    protected $signature = 'retention:check-disposals';

    protected $description = 'Check retention countdowns (90/60/30/expiration) and raise compliance alerts';

    public function handle(NotificationService $notificationService)
    {
        $this->info('Checking retention compliance windows...');

        $thresholds = [90 => '90_days', 60 => '60_days', 30 => '30_days', 0 => 'expiration'];
        $count = 0;

        $assignments = RetentionAssignment::whereIn('status', ['active', 'due_for_review'])
            ->with(['document.owner'])
            ->get();

        foreach ($assignments as $assignment) {
            $disposal = Carbon::parse($assignment->calculated_disposal_date)->startOfDay();
            $daysRemaining = (int) Carbon::now()->startOfDay()->diffInDays($disposal, false);

            $compliance = 'compliant';
            if ($daysRemaining < 0) {
                $compliance = 'overdue';
            } elseif ($daysRemaining <= 30) {
                $compliance = 'expiring_soon';
            }
            if (($assignment->status ?? '') === 'disposed') {
                $compliance = 'archived';
            }

            $assignment->compliance_status = $compliance;
            if ($daysRemaining <= 7 && $assignment->status === 'active') {
                $assignment->status = 'due_for_review';
                $assignment->disposal_workflow_status = $assignment->disposal_workflow_status ?: 'pending_disposal';
            }
            $assignment->save();

            foreach ($thresholds as $days => $alertType) {
                if ($daysRemaining !== $days && ! ($days === 0 && $daysRemaining <= 0)) {
                    continue;
                }
                if ($days === 0 && $daysRemaining > 0) {
                    continue;
                }

                $title = $daysRemaining < 0
                    ? 'Overdue retention disposal'
                    : ($days === 0 ? 'Retention expiration day' : "Retention reminder ({$days} days)");

                $message = "Document '".($assignment->document->title ?? 'Record')."' disposal date {$assignment->calculated_disposal_date} ({$daysRemaining} days remaining).";

                if (Schema::hasTable('compliance_alerts')) {
                    $exists = DB::table('compliance_alerts')
                        ->where('retention_assignment_id', $assignment->id)
                        ->where('alert_type', $daysRemaining < 0 ? 'overdue' : $alertType)
                        ->where('status', 'open')
                        ->exists();
                    if (! $exists) {
                        DB::table('compliance_alerts')->insert([
                            'retention_assignment_id' => $assignment->id,
                            'document_id' => $assignment->document_id,
                            'alert_type' => $daysRemaining < 0 ? 'overdue' : $alertType,
                            'compliance_status' => $compliance,
                            'title' => $title,
                            'message' => $message,
                            'days_remaining' => max(0, $daysRemaining),
                            'status' => 'open',
                            'notified_at' => now(),
                            'created_at' => now(),
                        ]);
                    }
                }

                if ($assignment->document && $assignment->document->owner) {
                    $notificationService->send(
                        $assignment->document->owner,
                        'retention',
                        $title,
                        $message,
                        '/retention',
                        true
                    );
                }

                AutomationLogger::log('retention', 'compliance_alert', 'retention_assignment', (int) $assignment->id, [
                    'alert_type' => $alertType,
                    'days_remaining' => $daysRemaining,
                ], 'scheduler');
                $count++;
            }
        }

        $this->info("Processed compliance checks; raised {$count} reminder(s).");
    }
}
