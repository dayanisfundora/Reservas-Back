<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * List notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()->orderBy('created_at', 'desc')->get()
            ->map(function (DatabaseNotification $n) {
                return [
                    'id' => $n->id,
                    'message' => $n->data['message'] ?? ($n->data['text'] ?? json_encode($n->data)),
                    'data' => $n->data,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ];
            })->values();

        return response()->json(['notifications' => $notifications], 200);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notificación no encontrada'], 404);
        }

        $notification->markAsRead();

        return response()->json(['success' => true, 'message' => 'Marcada como leída'], 200);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notificación no encontrada'], 404);
        }

        $notification->delete();

        return response()->json(['success' => true, 'message' => 'Notificación eliminada'], 200);
    }
}
