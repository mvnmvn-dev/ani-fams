<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AutomationLogger
{
    public static function log(
        string $module,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $payload = [],
        string $triggeredBy = 'system'
    ): void {
        if (! Schema::hasTable('automation_history')) {
            return;
        }

        try {
            DB::table('automation_history')->insert([
                'module' => $module,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload' => $payload === [] ? null : json_encode($payload),
                'triggered_by' => $triggeredBy,
                'created_at' => now()->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Never break primary workflows for logging failures.
        }
    }
}
