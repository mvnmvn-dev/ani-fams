<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckReservationReminders extends Command
{
    protected $signature = 'reservations:reminders';

    protected $description = 'Send reminders for upcoming approved reservations';

    public function handle(NotificationService $notificationService)
    {
        $this->info('Checking for upcoming reservations...');

        $tomorrowStart = Carbon::tomorrow()->startOfDay();
        $tomorrowEnd = Carbon::tomorrow()->endOfDay();

        $upcoming = Reservation::where('status', 'approved')
            ->whereNull('reminder_sent_at')
            ->whereBetween('start_datetime', [$tomorrowStart, $tomorrowEnd])
            ->with(['facility', 'requester'])
            ->get();

        $count = 0;
        foreach ($upcoming as $reservation) {
            if ($reservation->requester) {
                $notificationService->send(
                    $reservation->requester,
                    'reservations',
                    'Reminder: Upcoming Reservation Tomorrow',
                    "You have a reservation for {$reservation->facility->name} starting at {$reservation->start_datetime}.",
                    '/reservations',
                    true
                );
                DB::table('reservations')->where('id', $reservation->id)->update([
                    'reminder_sent_at' => now(),
                ]);
                $count++;
            }
        }

        $this->info("Sent {$count} reservation reminders.");

        return self::SUCCESS;
    }
}
