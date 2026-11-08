<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendVisitStatusCallback implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [10, 30, 120, 300];

    public function __construct(
        public int $visitId,
        public string $status,
        public ?string $checkInTime = null,
        public ?string $checkOutTime = null,
    ) {}

    public function handle(): void
    {
        $visit = DB::table('visits')->where('id', $this->visitId)->first();
        if (! $visit || empty($visit->source_system) || empty($visit->source_ref)) {
            return;
        }
        $system = config('integration.systems.' . $visit->source_system, []);
        $url = $system['callback_url'] ?? null;
        $token = $system['token'] ?? null;
        if (! $url) {
            return;
        }

        /** @var PendingRequest $request */
        $request = Http::timeout(10)->acceptJson();
        if ($token) {
            $request = $request->withToken($token);
        }
        try {
            $response = $request->post($url, [
                'interview_external_id' => $visit->source_ref,
                'visit_id' => $this->visitId,
                'status' => $this->status,
                'check_in_time' => $this->checkInTime,
                'check_out_time' => $this->checkOutTime,
            ]);
            DB::table('integration_log')->insert([
                'source_system' => $visit->source_system,
                'direction' => 'out',
                'resource' => 'visit.status',
                'external_id' => $visit->source_ref,
                'status' => $response->successful() ? 'ok' : 'error',
                'error' => $response->successful() ? null : $response->body(),
                'created_at' => now(),
            ]);
            if (! $response->successful()) {
                throw new \RuntimeException('Visit status callback failed with HTTP ' . $response->status());
            }
        } catch (\Throwable $e) {
            DB::table('integration_log')->insert([
                'source_system' => $visit->source_system,
                'direction' => 'out',
                'resource' => 'visit.status',
                'external_id' => $visit->source_ref,
                'status' => 'error',
                'error' => $e->getMessage(),
                'created_at' => now(),
            ]);
            throw $e;
        }
    }
}
