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

        // Find active contracts where today is (end_date - renewal_notice_days)
        $expiringContracts = Contract::whereIn('status', ['active', 'renewed'])
            ->whereNotNull('end_date')
            ->where(DB::raw('DATEDIFF(end_date, CURRENT_DATE)'), '<=', DB::raw('renewal_notice_days'))
            ->where(DB::raw('DATEDIFF(end_date, CURRENT_DATE)'), '>', 0)
            ->with('owner')
            ->get();

        $count = 0;
        foreach ($expiringContracts as $contract) {
            if ($contract->owner) {
                // Ensure we don't spam. In a real app we'd track if we already sent this, 
                // but for now, we just notify.
                $notificationService->send(
                    $contract->owner,
                    'contracts',
                    "Contract Expiring Soon: {$contract->contract_number}",
                    "The contract '{$contract->title}' with {$contract->counterparty_name} is expiring on {$contract->end_date}.",
                    "/contracts", 
                    true
                );
                $count++;
            }
        }

        $this->info("Sent {$count} contract expiration warnings.");
    }
}
