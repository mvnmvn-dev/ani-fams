<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return view('notifications.index', [
            'pageTitle' => 'My Notifications',
            'notifications' => $notifications,
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
}
