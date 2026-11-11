<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Get unread count for topbar badge.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::forUser($request->user()->id)->unread()->count();

        return response()->json(['unread_count' => $count]);
    }

    /**
     * Get recent notifications for topbar dropdown.
     */
    public function recent(Request $request): JsonResponse
    {
        $notifications = Notification::forUser($request->user()->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($notif) {
                return [
                    'id'          => $notif->id,
                    'type'        => $notif->type,
                    'title'       => $notif->title,
                    'message'     => $notif->message,
                    'module'      => strtoupper($notif->module),
                    'priority'    => $notif->priority,
                    'is_read'     => $notif->is_read,
                    'target_url'  => route('notifications.read', $notif),
                    'created_at'  => $notif->created_at->diffForHumans(),
                ];
            });

        $unreadCount = Notification::forUser($request->user()->id)->unread()->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * View Notification Center page.
     */ 
    public function index(Request $request): View
    {
        $query = Notification::forUser($request->user()->id)->latest();

        if ($request->filled('filter')) {
            if ($request->filter === 'unread') {
                $query->unread();
            } elseif (in_array($request->filter, ['lis', 'ris', 'pms', 'sors', 'dnms', 'admin', 'clinical'])) {
                $query->where('module', $request->filter);
            }
        }

        $notifications = $query->paginate(15)->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Mark a single notification as read and redirect to its target URL.
     */
    public function markAsRead(Request $request, Notification $notification): RedirectResponse
    {
        // Enforce ownership authorization policy (system admins can view/mark any notification)
        if ($notification->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            abort(403, 'Unauthorized access to notification.');
        }

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        if ($notification->target_url) {
            return redirect()->to($notification->target_url);
        }

        return redirect()->back();
    }

    /**
     * Mark all unread notifications for current user as read.
     */
    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        Notification::forUser($request->user()->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }
}
