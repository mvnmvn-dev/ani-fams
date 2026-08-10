<?php

namespace App\Services;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionAnomalyService
{
    /**
     * Compare the new login against known sessions and alert on new IP / device.
     *
     * @return list<string> anomaly labels detected
     */
    public function checkAndAlert(User $user, Request $request): array
    {
        $ip = (string) $request->ip();
        $ua = substr((string) $request->userAgent(), 0, 500);
        $anomalies = [];

        try {
            $query = DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->limit(25);

            $currentSessionId = $request->session()->getId();
            if ($currentSessionId) {
                $query->where('id', '!=', $currentSessionId);
            }

            $prior = $query->get(['ip_address', 'user_agent', 'last_activity']);
        } catch (\Throwable $e) {
            return [];
        }

        if ($prior->isEmpty()) {
            return [];
        }

        $knownIps = $prior->pluck('ip_address')->filter()->unique()->all();
        $knownUas = $prior->pluck('user_agent')->filter()->unique()->all();

        $newIp = $ip !== '' && ! in_array($ip, $knownIps, true);
        $newUa = $ua !== '' && ! $this->uaMatchesKnown($ua, $knownUas);

        if ($newIp) {
            $anomalies[] = 'new_ip';
        }
        if ($newUa) {
            $anomalies[] = 'new_device';
        }

        // Concurrent sessions from different IPs in the last hour
        $recentDistinctIps = $prior
            ->filter(fn ($s) => (int) ($s->last_activity ?? 0) >= time() - 3600)
            ->pluck('ip_address')
            ->filter()
            ->unique()
            ->values();
        if ($recentDistinctIps->count() >= 2 && $newIp) {
            $anomalies[] = 'concurrent_locations';
        }

        if ($anomalies === []) {
            return [];
        }

        $labels = [
            'new_ip' => 'login from a new IP address (' . $ip . ')',
            'new_device' => 'login from a new or unrecognized device/browser',
            'concurrent_locations' => 'multiple concurrent session locations',
        ];
        $parts = [];
        foreach ($anomalies as $key) {
            $parts[] = $labels[$key] ?? $key;
        }
        $summary = implode('; ', $parts);

        AuditLogger::log('auth', 'session_anomaly', 'user', $user->id, null, [
            'anomalies' => $anomalies,
            'ip' => $ip,
            'user_agent' => $ua,
        ]);

        try {
            app(NotificationService::class)->send(
                $user,
                'auth',
                'Unusual sign-in activity',
                'We detected unusual activity on your account: ' . $summary . '. If this was not you, revoke other sessions and change your password.',
                '/settings/sessions',
                true
            );
        } catch (\Throwable $e) {
            report($e);
        }

        // Also notify admins when concurrent location anomaly
        if (in_array('concurrent_locations', $anomalies, true)) {
            try {
                $admins = User::query()
                    ->whereNull('deleted_at')
                    ->where('status', 'active')
                    ->whereHas('role', fn ($q) => $q->where('code', 'admin'))
                    ->where('id', '!=', $user->id)
                    ->limit(10)
                    ->get();
                foreach ($admins as $admin) {
                    app(NotificationService::class)->send(
                        $admin,
                        'auth',
                        'Session anomaly: ' . $user->full_name,
                        $user->email . ' — ' . $summary,
                        '/users',
                        false
                    );
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $anomalies;
    }

    /**
     * @param  list<string>  $knownUas
     */
    private function uaMatchesKnown(string $ua, array $knownUas): bool
    {
        $needle = $this->uaFingerprint($ua);
        foreach ($knownUas as $known) {
            if ($this->uaFingerprint((string) $known) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function uaFingerprint(string $ua): string
    {
        // Normalize: browser family + OS hints, ignore minor version noise
        $ua = strtolower($ua);
        $parts = [];
        foreach (['windows', 'macintosh', 'linux', 'android', 'iphone', 'ipad', 'chrome', 'firefox', 'safari', 'edg'] as $token) {
            if (str_contains($ua, $token)) {
                $parts[] = $token;
            }
        }

        return $parts !== [] ? implode('|', $parts) : md5($ua);
    }
}
