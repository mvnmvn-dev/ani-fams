<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Mail\ActionRequiredMail;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send a notification and optional email to a user.
     *
     * @param int|User $user ID or User model
     * @param string $module The module generating the notification (e.g., 'reservations', 'contracts')
     * @param string $title
     * @param string $body
     * @param string|null $link
     * @param bool $sendEmail
     * @return Notification
     */
    public function send($user, string $module, string $title, string $body, ?string $link = null, bool $sendEmail = true): Notification
    {
        $userModel = $user instanceof User ? $user : User::findOrFail($user);

        // 1. Create In-App Notification (schema has created_at only)
        $notification = Notification::create([
            'user_id' => $userModel->id,
            'module'  => $module,
            'title'   => $title,
            'body'    => $body,
            'link'    => $link,
            'is_read' => false,
            'created_at' => now(),
        ]);

        // 2. Optional email (never fail the primary action if mail/queue is misconfigured)
        if ($sendEmail && $userModel->email) {
            try {
                Mail::to($userModel->email)->send(new ActionRequiredMail($title, $body, $link ? url($link) : null));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $notification;
    }
}
