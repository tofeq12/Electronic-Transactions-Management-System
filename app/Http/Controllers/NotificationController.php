<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Notifications page — the list is rendered server-side into .nf-list
     * (the existing notifications.js only filters/marks items in the DOM).
     */
    public function index()
    {
        $notifications = Notification::where('user_id', auth()->id())
            ->with('transaction')
            ->latest('id')
            ->limit(50)
            ->get();

        return $this->page('pages.notifications', ['notifications' => $notifications]);
    }

    /**
     * Mark one of MY notifications as read.
     */
    public function markRead(Request $request, int $id)
    {
        $notification = Notification::where('user_id', auth()->id())->findOrFail($id);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $this->json(true, 'تم تعليم الإشعار كمقروء');
    }

    /**
     * Phase 8 — mark ALL of MY unread notifications as read (scoped to the
     * current user; other people's notifications are never touched).
     */
    public function markAllRead()
    {
        $count = Notification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->json(true, 'تم تعليم جميع الإشعارات كمقروءة', ['count' => $count]);
    }
}
