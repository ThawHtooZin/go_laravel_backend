<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RideController extends Controller
{
    private static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earth * $c, 2);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pickup_lat' => ['required', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_lat' => ['required', 'numeric', 'between:-90,90'],
            'dropoff_lng' => ['required', 'numeric', 'between:-180,180'],
            'origin_text' => ['nullable', 'string', 'max:255'],
            'destination_text' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if ($user->role !== 'passenger') {
            return response()->json([
                'errors' => ['message' => 'Only passengers can create rides.'],
            ], Response::HTTP_FORBIDDEN);
        }

        $pickupLat = (float) $validated['pickup_lat'];
        $pickupLng = (float) $validated['pickup_lng'];
        $dropoffLat = (float) $validated['dropoff_lat'];
        $dropoffLng = (float) $validated['dropoff_lng'];
        $distanceKm = self::haversineKm($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $originText = $validated['origin_text'] ?? sprintf('Pickup (%.4f, %.4f)', $pickupLat, $pickupLng);
        $destinationText = $validated['destination_text'] ?? sprintf('Dropoff (%.4f, %.4f)', $dropoffLat, $dropoffLng);

        $ride = Ride::create([
            'passenger_id' => $user->id,
            'status' => 'requested',
            'origin_text' => $originText,
            'destination_text' => $destinationText,
            'pickup_lat' => $pickupLat,
            'pickup_lng' => $pickupLng,
            'dropoff_lat' => $dropoffLat,
            'dropoff_lng' => $dropoffLng,
            'distance_km' => $distanceKm,
        ]);

        return response()->json([
            'data' => $ride->load('passenger:id,display_name,phone'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Driver: list rides with status = requested (available to accept).
     */
    public function availableRides(Request $request)
    {
        if ($request->user()->role !== 'driver') {
            return response()->json(['errors' => ['message' => 'Only drivers can list available rides.']], Response::HTTP_FORBIDDEN);
        }

        $rides = Ride::with('passenger:id,display_name,phone')
            ->where('status', 'requested')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['data' => $rides]);
    }

    /**
     * Driver: accept a requested ride.
     */
    public function accept(Request $request, Ride $ride)
    {
        $user = $request->user();
        if ($user->role !== 'driver') {
            return response()->json(['errors' => ['message' => 'Only drivers can accept rides.']], Response::HTTP_FORBIDDEN);
        }
        if ($ride->status !== 'requested') {
            return response()->json(['errors' => ['message' => 'Ride is not available.']], Response::HTTP_BAD_REQUEST);
        }

        $ride->driver_id = $user->id;
        $ride->status = 'assigned';
        $ride->save();

        return response()->json(['data' => $ride->load(['passenger:id,display_name,phone', 'driver:id,display_name,phone'])]);
    }

    /**
     * Driver: mark arrived at pickup (notifies passenger via status).
     */
    public function arrivedPickup(Request $request, Ride $ride)
    {
        $user = $request->user();
        if ($ride->driver_id !== $user->id) {
            return response()->json(['errors' => ['message' => 'You are not assigned to this ride.']], Response::HTTP_FORBIDDEN);
        }
        if ($ride->status !== 'assigned') {
            return response()->json(['errors' => ['message' => 'Invalid state for arrived at pickup.']], Response::HTTP_BAD_REQUEST);
        }

        $ride->status = 'driver_at_pickup';
        $ride->save();

        return response()->json(['data' => $ride->load(['passenger', 'driver'])]);
    }

    /**
     * Driver: start trip to destination.
     */
    public function startTrip(Request $request, Ride $ride)
    {
        $user = $request->user();
        if ($ride->driver_id !== $user->id) {
            return response()->json(['errors' => ['message' => 'You are not assigned to this ride.']], Response::HTTP_FORBIDDEN);
        }
        if (! in_array($ride->status, ['assigned', 'driver_at_pickup'], true)) {
            return response()->json(['errors' => ['message' => 'Invalid state to start trip.']], Response::HTTP_BAD_REQUEST);
        }

        $ride->status = 'in_progress';
        $ride->save();

        return response()->json(['data' => $ride->load(['passenger', 'driver'])]);
    }

    public function myRides(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'passenger') {
            $rides = Ride::where('passenger_id', $user->id)
                ->orderByDesc('id')
                ->paginate(50);
        } elseif ($user->role === 'driver') {
            $rides = Ride::where('driver_id', $user->id)
                ->orderByDesc('id')
                ->paginate(50);
        } else {
            $rides = Ride::orderByDesc('id')->paginate(50);
        }

        return response()->json([
            'data' => $rides,
        ]);
    }

    public function index()
    {
        $rides = Ride::with(['passenger', 'driver'])
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => $rides,
        ]);
    }

    /**
     * Driver: complete ride (cash in) after trip to destination.
     */
    public function complete(Request $request, Ride $ride)
    {
        $user = $request->user();
        if ($ride->driver_id !== $user->id) {
            return response()->json(['errors' => ['message' => 'You are not assigned to this ride.']], Response::HTTP_FORBIDDEN);
        }
        if ($ride->status !== 'in_progress') {
            return response()->json(['errors' => ['message' => 'Complete the trip first (start trip to destination).']], Response::HTTP_BAD_REQUEST);
        }

        $ride->status = 'completed';
        $ride->save();

        return response()->json(['data' => $ride->load(['passenger', 'driver'])]);
    }

    /**
     * Admin: assign a driver to a requested ride.
     */
    public function assignDriver(Request $request, Ride $ride)
    {
        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $driver = \App\Models\User::findOrFail($validated['driver_id']);
        if ($driver->role !== 'driver' || ! $driver->is_active) {
            return response()->json([
                'errors' => ['message' => 'Selected user is not an active driver.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($ride->status !== 'requested') {
            return response()->json([
                'errors' => ['message' => 'Only requested rides can be assigned.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $ride->driver_id = $validated['driver_id'];
        $ride->status = 'assigned';
        $ride->save();

        return response()->json(['data' => $ride->load(['passenger', 'driver'])]);
    }

    /**
     * Admin: cancel a ride.
     */
    public function cancel(Request $request, Ride $ride)
    {
        if (! in_array($ride->status, ['requested', 'assigned', 'driver_at_pickup', 'in_progress'], true)) {
            return response()->json([
                'errors' => ['message' => 'Ride cannot be cancelled in current state.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $ride->status = 'cancelled';
        $ride->save();

        return response()->json(['data' => $ride->load(['passenger', 'driver'])]);
    }
}

