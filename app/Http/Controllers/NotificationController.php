<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;
use App\Services\ArnelAiService;

class NotificationController extends Controller
{
    public function index(ArnelAiService $arnel)
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return view('notifications.index', [
            'pageTitle' => 'My Notifications',
            'notifications' => $notifications,
            'priorityGroups' => $arnel->groupNotifications($notifications->getCollection()),
        ]);
    }

    public function markAsRead(Request $request, int $id)
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        $notification->update(['is_read' => true]);
        
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return back();
    }
    
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);
            
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return back();
    }

    public function click(Request $request, int $id)
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        $notification->update(['is_read' => true]);
        
        return redirect($notification->link ?? '/dashboard');
    }

    public function poll()
    {
        $topbarAlerts = Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')->take(5)->get();
        $topbarAlertTotal = Notification::where('user_id', Auth::id())
            ->where('is_read', false)->count();

        return response()->json([
            'total' => $topbarAlertTotal,
            'alerts' => $topbarAlerts->map(function($note) {
                return [
                    'id' => $note->id,
                    'title' => $note->title,
                    'body' => $note->body,
                    'link' => url('/notifications/' . $note->id . '/click'),
                    'module' => $note->module,
                    'is_read' => $note->is_read,
                    'time' => $note->created_at->diffForHumans(),
                ];
            })
        ]);
    }
}
