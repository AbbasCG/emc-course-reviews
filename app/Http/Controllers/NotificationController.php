<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = AdminNotification::latest()->paginate(20);
        AdminNotification::whereNull('read_at')->update(['read_at' => now()]);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function open(AdminNotification $notification)
    {
        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        $data = $notification->data ?? [];

        if (!empty($data['campaign_id'])) {
            $url = route('admin.campaigns.show', $data['campaign_id']);

            if (!empty($data['review_id'])) {
                $url .= '#review-' . $data['review_id'];
            }

            return redirect($url);
        }

        return redirect()->route('admin.dashboard');
    }

    public function markRead(AdminNotification $notification)
    {
        $notification->markRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        AdminNotification::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['success' => true, 'unread' => 0]);
    }
}
