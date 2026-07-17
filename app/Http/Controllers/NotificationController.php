<?php

namespace App\Http\Controllers;

use App\Models\InAppNotification;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function page(Request $request, InAppNotificationService $notifications): View
    {
        $user = $request->user();
        $items = $notifications->paginateFor($user, 30);

        return view('notifications.index', [
            'notifications' => $items,
            'unreadCount' => $notifications->unreadCount($user),
            'service' => $notifications,
        ]);
    }

    public function feed(Request $request, InAppNotificationService $notifications): JsonResponse
    {
        $user = $request->user();
        $limit = max(1, min(50, (int) $request->integer('limit', 10)));

        $items = $notifications->recentFor($user, $limit)->map(function (InAppNotification $n) use ($notifications) {
            return $this->serialize($n, $notifications);
        });

        $latestId = (int) ($items->first()['id'] ?? 0);

        return response()->json([
            'unread_count' => $notifications->unreadCount($user),
            'latest_id' => $latestId,
            'notifications' => $items,
        ]);
    }

    public function markRead(Request $request, int $id, InAppNotificationService $notifications): JsonResponse
    {
        $notification = InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('type', $notifications->typesFor($request->user()))
            ->findOrFail($id);

        $notification->markRead();

        return response()->json([
            'ok' => true,
            'unread_count' => $notifications->unreadCount($request->user()),
        ]);
    }

    public function markAllRead(Request $request, InAppNotificationService $notifications)
    {
        $notifications->markAllRead($request->user());

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'unread_count' => 0,
            ]);
        }

        return back()->with('status', 'All notifications marked as read.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(InAppNotification $n, InAppNotificationService $notifications): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'level' => $n->level,
            'link' => $n->link,
            'icon' => $notifications->iconFor($n->type),
            'unread' => $n->isUnread(),
            'created_at' => $n->created_at?->toIso8601String(),
            'created_at_human' => $n->created_at?->diffForHumans(),
        ];
    }
}
