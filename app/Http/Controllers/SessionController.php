<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SessionController extends Controller
{
    /**
     * @return array{sessions: list<array<string, mixed>>, databaseSessions: bool, sessionDriver: string, currentId: string}
     */
    public static function deviceState(): array
    {
        $sessions = [];
        $databaseSessions = false;
        $currentId = session()->getId();

        try {
            if (Schema::hasTable('sessions')) {
                $databaseSessions = true;
                $rows = DB::table('sessions')
                    ->where('user_id', Auth::id())
                    ->orderByDesc('last_activity')
                    ->get();

                foreach ($rows as $row) {
                    $sessions[] = [
                        'id' => $row->id,
                        'ip_address' => $row->ip_address,
                        'user_agent' => $row->user_agent,
                        'device' => self::parseUserAgent((string) ($row->user_agent ?? '')),
                        'last_activity' => $row->last_activity
                            ? date('Y-m-d H:i:s', (int) $row->last_activity)
                            : null,
                        'is_current' => $row->id === $currentId,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $databaseSessions = false;
        }

        return [
            'sessions' => $sessions,
            'databaseSessions' => $databaseSessions,
            'sessionDriver' => (string) config('session.driver'),
            'currentId' => $currentId,
        ];
    }

    public function index()
    {
        // Sessions UI is a modal on every page — bounce back and ask the layout to open it.
        $fallback = route('dashboard');
        $previous = url()->previous();
        $target = ($previous && $previous !== url()->current()) ? $previous : $fallback;

        return redirect()->to($target)->with('open_sessions_modal', true);
    }

    public function revoke(string $id)
    {
        $currentId = session()->getId();
        if ($id === $currentId) {
            return back()->with('flash_error', 'You cannot revoke your current session from this screen. Log out instead.')
                ->with('open_sessions_modal', true);
        }

        try {
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')
                    ->where('user_id', Auth::id())
                    ->where('id', $id)
                    ->delete();
            }
        } catch (\Throwable $e) {
            return back()->with('flash_error', 'Unable to revoke session. Database sessions may not be enabled.')
                ->with('open_sessions_modal', true);
        }

        return back()->with('flash_success', 'Session revoked.')->with('open_sessions_modal', true);
    }

    public function revokeOthers(Request $request)
    {
        $currentId = session()->getId();

        try {
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')
                    ->where('user_id', Auth::id())
                    ->where('id', '!=', $currentId)
                    ->delete();
            } else {
                return back()->with('flash_error', 'Database sessions are required to manage devices.')
                    ->with('open_sessions_modal', true);
            }
        } catch (\Throwable $e) {
            return back()->with('flash_error', 'Unable to revoke other sessions.')
                ->with('open_sessions_modal', true);
        }

        return back()->with('flash_success', 'Other sessions revoked.')->with('open_sessions_modal', true);
    }

    private static function parseUserAgent(string $ua): string
    {
        if ($ua === '') {
            return 'Unknown device';
        }
        $browser = 'Browser';
        if (stripos($ua, 'Edg/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'Chrome/') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'Firefox/') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($ua, 'Safari/') !== false) {
            $browser = 'Safari';
        }

        $os = 'Unknown OS';
        if (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }

        return $browser . ' on ' . $os;
    }
}
