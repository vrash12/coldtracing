<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    private function authorizeDriver(): void
    {
        if (!Auth::check() || !Auth::user()->isDriver()) {
            abort(403, 'Only drivers can access notifications.');
        }
    }

    public function markAsRead(string $notificationId)
    {
        $this->authorizeDriver();

        $driver = Auth::user();

        $notification = $driver->notifications()
            ->where('id', $notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead()
    {
        $this->authorizeDriver();

        Auth::user()
            ->unreadNotifications
            ->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}