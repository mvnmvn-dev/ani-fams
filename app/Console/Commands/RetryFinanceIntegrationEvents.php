<?php

namespace App\Console\Commands;

use App\Jobs\SendFinanceEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetryFinanceIntegrationEvents extends Command
{
    protected $signature = 'integration:finance-retry {--limit=100 : Maximum pending/failed events to enqueue}';
    protected $description = 'Retry pending or failed Finance integration callback events';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $events = DB::table('integration_outbound_events')
            ->where('system_code', 'fin')->whereIn('status', ['pending', 'failed'])
            ->orderBy('id')->limit($limit)->get();
        foreach ($events as $event) {
            SendFinanceEvent::dispatch((string) $event->event_type, (array) json_decode($event->payload, true));
        }
        $this->info('Enqueued ' . $events->count() . ' Finance event(s).');
        return self::SUCCESS;
    }
}
