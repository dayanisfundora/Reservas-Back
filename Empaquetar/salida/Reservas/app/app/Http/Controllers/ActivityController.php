<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller for managing activities.
 */
class ActivityController extends Controller
{
    /**
     * Get all activities.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $activities = Activity::all();
        return response()->json($activities);
    }

    /**
     * Store a new activity.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $activity = Activity::create([
            'description' => $request->description,
        ]);

        return response()->json($activity, 201);
    }

    /**
     * Update an existing activity.
     *
     * @param Request $request
     * @param Activity $activity
     * @return JsonResponse
     */
    public function update(Request $request, Activity $activity): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|max:255',
        ]);

        $activity->update([
            'description' => $request->description,
        ]);

        return response()->json($activity);
    }

    /**
     * Delete an activity.
     *
     * @param Activity $activity
     * @return JsonResponse
     */
    public function destroy(Activity $activity): JsonResponse
    {
        $activity->delete();
        return response()->json(['message' => 'Activity deleted successfully']);
    }
}
