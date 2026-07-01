<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserNotificationResource;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('unread_only'), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return UserNotificationResource::collection($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    public function markAsRead(Request $request, UserNotification $notification): UserNotificationResource
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update([
            'read_at' => now(),
        ]);

        return new UserNotificationResource($notification->fresh());
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'message' => 'All notifications marked as read.',
        ]);
    }
}
