<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller {

    private function schoolId(): ?int {
        if (app()->bound('active_school')) return app('active_school')->id;
        return auth()->user()->school_id ?? null;
    }

    public function index(Request $request) {
        $user  = auth()->user();
        $roles = $user->getRoleNames()->toArray();

        $notifications = SchoolNotification::where('school_id', $this->schoolId())
            ->where(function ($q) use ($roles) {
                $q->whereIn('recipient_role', $roles)
                  ->orWhere('recipient_role', 'all');
            })
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function markRead(Request $request, SchoolNotification $notification) {
        if ($notification->school_id !== $this->schoolId()) abort(403, 'Unauthorized');

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return response()->json($notification);
    }

    public function markAllRead(Request $request) {
        $user  = auth()->user();
        $roles = $user->getRoleNames()->toArray();

        SchoolNotification::where('school_id', $this->schoolId())
            ->where(function ($q) use ($roles) {
                $q->whereIn('recipient_role', $roles)
                  ->orWhere('recipient_role', 'all');
            })
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'Arifa zote zimewekwa kuwa zimesomwa']);
    }

    public function unreadCount(Request $request) {
        $user  = auth()->user();
        $roles = $user->getRoleNames()->toArray();

        $count = SchoolNotification::where('school_id', $this->schoolId())
            ->where(function ($q) use ($roles) {
                $q->whereIn('recipient_role', $roles)
                  ->orWhere('recipient_role', 'all');
            })
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }
}
