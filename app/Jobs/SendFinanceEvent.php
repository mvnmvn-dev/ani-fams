<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendFinanceEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 120, 300];

    public function __construct(public string $eventType, public array $payload) {}

    public function handle(): void
    {
        $eventId = (string) ($this->payload['event_id'] ?? hash('sha256', $this->eventType . ':' . json_encode($this->payload)));
        $outboxId = DB::table('integration_outbound_events')->where('event_id', $eventId)->value('id');
        if (! $outboxId) {
            $outboxId = DB::table('integration_outbound_events')->insertGetId([
                'system_code' => 'fin', 'event_id' => $eventId, 'event_type' => $this->eventType,
                'payload' => json_encode($this->payload), 'status' => 'pending', 'attempts' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $finance = config('integration.systems.fin', []);
        $url = $finance['callback_url'] ?? null;
        if (! $url) {
            // Persisted as pending. Configure Finance and run integration:finance-retry.
            return;
        }
        DB::table('integration_outbound_events')->where('id', $outboxId)->update([
            'status' => 'processing', 'attempts' => DB::raw('attempts + 1'), 'last_attempt_at' => now(), 'updated_at' => now(),
        ]);
        $body = ['event_id' => $eventId, 'event_type' => $this->eventType, 'data' => $this->payload];
        try {
            $request = Http::timeout(10)->acceptJson()->withHeaders(['Idempotency-Key' => $eventId]);
            if (! empty($finance['token'])) {
                $request = $request->withToken($finance['token']);
            }
            $response = $request->post($url, $body);
            if (! $response->successful()) {
                throw new \RuntimeException('Finance callback failed with HTTP ' . $response->status());
            }
            DB::table('integration_outbound_events')->where('id', $outboxId)->update([
                'status' => 'sent', 'last_error' => null, 'sent_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('integration_log')->insert([
                'source_system' => 'fin', 'direction' => 'out', 'resource' => $this->eventType,
                'external_id' => $eventId, 'status' => 'ok', 'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            DB::table('integration_outbound_events')->where('id', $outboxId)->update([
                'status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => now(),
            ]);
            DB::table('integration_log')->insert([
                'source_system' => 'fin', 'direction' => 'out', 'resource' => $this->eventType,
                'external_id' => $eventId, 'status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 2000), 'created_at' => now(),
            ]);
            throw $e;
        }
    }
}
