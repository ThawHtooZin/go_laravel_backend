<?php

namespace App\Http\Controllers;

use App\Models\DriverLocationLog;
use App\Models\DriverStatusLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DriverStatusController extends Controller
{
    public function online(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'driver') {
            return response()->json([
                'errors' => ['message' => 'Only drivers can go online.'],
            ], Response::HTTP_FORBIDDEN);
        }

        $user->forceFill(['is_online' => true])->save();

        DriverStatusLog::create([
            'user_id' => $user->id,
            'status' => 'online',
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'is_online' => true,
                'message' => 'You are now online.',
            ],
        ]);
    }

    public function offline(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'driver') {
            return response()->json([
                'errors' => ['message' => 'Only drivers can go offline.'],
            ], Response::HTTP_FORBIDDEN);
        }

        $user->forceFill([
            'is_online' => false,
            'last_latitude' => null,
            'last_longitude' => null,
            'last_location_at' => null,
        ])->save();

        DriverStatusLog::create([
            'user_id' => $user->id,
            'status' => 'offline',
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'is_online' => false,
                'message' => 'You are now offline.',
            ],
        ]);
    }

    public function location(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'driver') {
            return response()->json([
                'errors' => ['message' => 'Only drivers can send location.'],
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $user->is_online) {
            return response()->json([
                'errors' => ['message' => 'You must be online to send location.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $user->forceFill([
            'last_latitude' => $validated['latitude'],
            'last_longitude' => $validated['longitude'],
            'last_location_at' => now(),
        ])->save();

        DriverLocationLog::create([
            'user_id' => $user->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'created_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'latitude' => $user->last_latitude,
                'longitude' => $user->last_longitude,
                'updated_at' => $user->last_location_at?->toIso8601String(),
            ],
        ]);
    }
}
