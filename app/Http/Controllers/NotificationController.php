<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user
            ->notifications()
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($notification) {
                $data = $notification->data;

                return [
                    'id' => $notification->id,
                    'message' => $data['message'] ?? $data['title'] ?? 'New notification',
                    'title' => $data['title'] ?? '',
                    'icon' => $data['icon'] ?? 'mdi-bell',
                    'color' => $data['color'] ?? 'primary',
                    'link' => $data['link'] ?? null,
                    'read' => ! is_null($notification->read_at),
                    'created_at' => $notification->created_at?->toISOString(),
                ];
            });

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'message' => 'Marked as read.',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All marked as read.',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return response()->json([
            'message' => 'Notification deleted.',
        ]);
    }

    public function clearAll(Request $request)
    {
        $request->user()->notifications()->delete();

        return response()->json([
            'message' => 'All notifications cleared.',
        ]);
    }
}
