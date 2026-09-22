<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contract;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CheckContractExpirations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:check-expirations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for contracts nearing their end date and notify owners';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $this->info('Checking for expiring contracts...');

        $driver = DB::connection()->getDriverName();
        $query = Contract::whereIn('status', ['active', 'renewed'])
            ->whereNotNull('end_date');

        if ($driver === 'pgsql') {
            $query->whereRaw('(end_date - CURRENT_DATE) <= renewal_notice_days')
                ->whereRaw('(end_date - CURRENT_DATE) > 0');
        } else {
            $query->whereRaw('DATEDIFF(end_date, CURRENT_DATE) <= renewal_notice_days')
                ->whereRaw('DATEDIFF(end_date, CURRENT_DATE) > 0');
        }

        $expiringContracts = $query->with('owner')->get();

        $count = 0;
        foreach ($expiringContracts as $contract) {
            if ($contract->owner) {
                $days = Carbon::today()->diffInDays(Carbon::parse($contract->end_date), false);
                $priority = $days <= 7 ? 'HIGH PRIORITY' : 'Renewal reminder';
                // Ensure we don't spam. In a real app we'd track if we already sent this, 
                // but for now, we just notify.
                $notificationService->send(
                    $contract->owner,
                    'contracts',
                    "{$priority}: Contract {$contract->contract_number}",
                    "The contract '{$contract->title}' with {$contract->counterparty_name} expires in {$days} day(s) on {$contract->end_date}. Please review renewal options.",
                    "/contracts", 
                    true
                );
                $count++;
            }
        }

        $this->info("Sent {$count} contract expiration warnings.");
    }
}
