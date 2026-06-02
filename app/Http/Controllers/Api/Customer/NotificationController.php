<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use App\Events\UnseenNotificationCountUpdated;

/**
 * @group CustomerApi
 * @subgroup Notifications
 */
class NotificationController extends Controller
{
    /**
     * Get a paginated list of notifications for the authenticated user.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('perPage', 15);
        
        $notifications = $request->user()->notifications()
            ->latest()
            ->paginate($perPage);

        return apiResponse(true, 'Notifications fetched successfully', $notifications->items(), 200, [], [
            'total'        => $notifications->total(),
            'per_page'     => $notifications->perPage(),
            'current_page' => $notifications->currentPage(),
            'last_page'    => $notifications->lastPage(),
            'unseen_count' => $this->getUnseenCount($request->user()->id),
        ]);
    }

    /**
     * Get the count of unseen notifications for the authenticated user.
     */
    public function unseenCount(Request $request)
    {
        $count = $this->getUnseenCount($request->user()->id);
        
        return apiResponse(true, 'Unseen count fetched successfully', [
            'unseen_count' => $count
        ]);
    }

    /**
     * Mark a specific notification as seen (socket).
     */
    public function markSeen(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        
        if (!$notification->seen_at) {
            $notification->update(['seen_at' => now()]);
            
            // Broadcast the new count
            $this->broadcastUnseenCount($request->user()->id);
        }

        return apiResponse(true, 'Notification marked as seen');
    }

    /**
     * Mark all notifications as seen for the authenticated user (socket).
     */
    public function markAllSeen(Request $request)
    {
        $request->user()->notifications()
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        // Broadcast the new count (which will be 0)
        $this->broadcastUnseenCount($request->user()->id);

        return apiResponse(true, 'All notifications marked as seen');
    }

    /**
     * Internal helper to get unseen count.
     */
    protected function getUnseenCount($userId)
    {
        return UserNotification::where('user_id', $userId)
            ->whereNull('seen_at')
            ->count();
    }

    /**
     * Internal helper to broadcast unseen count.
     */
    protected function broadcastUnseenCount($userId)
    {
        try {
            $count = $this->getUnseenCount($userId);
            broadcast(new UnseenNotificationCountUpdated($userId, $count));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast failed for unseen notification count: ' . $e->getMessage());
        }
    }
}
