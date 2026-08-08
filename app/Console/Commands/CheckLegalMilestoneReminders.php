<?php

namespace App\Console\Commands;

use App\Repositories\LegalCaseRepository;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckLegalMilestoneReminders extends Command
{
    protected $signature = 'legal:milestone-reminders';

    protected $description = 'Remind legal managers of upcoming case milestones and deadlines';

    public function handle(LegalCaseRepository $repo, NotificationService $notifications)
    {
        $until = Carbon::now()->addDays(3)->endOfDay()->format('Y-m-d H:i:s');
        $milestones = $repo->upcomingMilestonesNeedingReminder($until);
        $managers = $repo->legalManagerUserIds();
        $count = 0;

        foreach ($milestones as $m) {
            $recipients = $managers;
            if (! empty($m['counsel_user_id'])) {
                $recipients[] = (int) $m['counsel_user_id'];
            }
            $recipients = array_values(array_unique(array_filter($recipients)));

            $type = str_replace('_', ' ', (string) ($m['milestone_type'] ?? 'milestone'));
            $title = "Legal deadline: {$m['case_number']}";
            $body = ucfirst($type)." for \"{$m['case_title']}\" is due on {$m['milestone_date']}.";
            $link = '/legal?open='.$m['case_id'];

            foreach ($recipients as $uid) {
                $notifications->send($uid, 'legal', $title, $body, $link, true);
            }

            $repo->markMilestoneReminderSent((int) $m['id']);
            $count++;
        }

        $this->info("Sent reminders for {$count} milestone(s).");

        return self::SUCCESS;
    }
}
