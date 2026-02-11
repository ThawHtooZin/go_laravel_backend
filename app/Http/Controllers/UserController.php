<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DriverStatusLog;
use App\Models\DriverLocationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        $users = $query->orderByDesc('id')->paginate(50);

        return response()->json([
            'data' => $users,
        ]);
    }

    public function drivers(Request $request)
    {
        $status = $request->query('status');

        $query = User::where('role', 'driver');

        if ($status === 'pending') {
            $query->where('is_active', false);
        } elseif ($status === 'approved') {
            $query->where('is_active', true);
        }

        $drivers = $query->orderByDesc('id')->paginate(50);

        return response()->json([
            'data' => $drivers,
        ]);
    }

    public function approveDriver(User $user)
    {
        if ($user->role !== 'driver') {
            abort(Response::HTTP_BAD_REQUEST, 'User is not a driver.');
        }

        $user->forceFill([
            'is_active' => true,
        ])->save();

        return response()->json([
            'data' => $user,
        ]);
    }

    public function rejectDriver(User $user)
    {
        if ($user->role !== 'driver') {
            abort(Response::HTTP_BAD_REQUEST, 'User is not a driver.');
        }

        $user->delete();

        return response()->json([
            'data' => [
                'message' => 'Driver rejected and deleted.',
            ],
        ]);
    }

    /**
     * Create a new driver from dashboard (admin/super_admin). Driver is created approved.
     */
    public function storeDriver(Request $request)
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'display_name' => $validated['display_name'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'role' => 'driver',
            'is_active' => true,
        ]);

        return response()->json(['data' => $user], Response::HTTP_CREATED);
    }

    /**
     * Create a new user from dashboard (admin/super_admin). Role: passenger, driver, or admin (super_admin only).
     */
    public function storeUser(Request $request)
    {
        $me = $request->user();
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:passenger,driver,admin'],
        ]);

        if ($validated['role'] === 'admin' && $me->role !== 'super_admin') {
            abort(Response::HTTP_FORBIDDEN, 'Only Super Admin can create Admins.');
        }

        $user = User::create([
            'display_name' => $validated['display_name'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_active' => $validated['role'] === 'driver' ? false : true,
        ]);

        return response()->json(['data' => $user], Response::HTTP_CREATED);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:32', 'unique:users,phone,'.$user->id],
            'role' => ['sometimes', 'string', 'in:admin,driver,passenger'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Only super_admin can change roles to/from admin or super_admin
        $me = $request->user();
        if (isset($validated['role']) && in_array($validated['role'], ['admin', 'super_admin'], true) && $me->role !== 'super_admin') {
            abort(Response::HTTP_FORBIDDEN, 'Only Super Admin can assign admin roles.');
        }
        if (isset($validated['role']) && $user->role === 'super_admin' && $me->id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot change a Super Admin from this screen.');
        }

        $user->fill($validated)->save();

        return response()->json([
            'data' => $user,
        ]);
    }

    /**
     * List admins and super_admins (super_admin only).
     */
    public function indexAdmins()
    {
        $admins = User::whereIn('role', ['admin', 'super_admin'])
            ->orderByDesc('id')
            ->get(['id', 'display_name', 'phone', 'role', 'is_active', 'created_at']);

        return response()->json(['data' => $admins]);
    }

    /**
     * Create a new admin (super_admin only).
     */
    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'display_name' => $validated['display_name'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'role' => 'admin',
            'is_active' => true,
        ]);

        return response()->json(['data' => $user], Response::HTTP_CREATED);
    }

    /**
     * Update an admin (super_admin only): change role to passenger (demote) or update details.
     */
    public function updateAdmin(Request $request, User $user)
    {
        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            abort(Response::HTTP_BAD_REQUEST, 'User is not an admin.');
        }

        $me = $request->user();
        if ($user->role === 'super_admin' && $me->id !== $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'Cannot modify another Super Admin.');
        }

        $validated = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:32', 'unique:users,phone,'.$user->id],
            'role' => ['sometimes', 'string', 'in:admin,passenger'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Cannot demote the last super_admin
        if (isset($validated['role']) && $user->role === 'super_admin') {
            abort(Response::HTTP_FORBIDDEN, 'Super Admins cannot be demoted from this screen.');
        }

        $user->fill($validated)->save();

        return response()->json(['data' => $user]);
    }

    public function stats()
    {
        return response()->json([
            'data' => [
                'total_users' => User::count(),
                'total_drivers' => User::where('role', 'driver')->count(),
                'total_passengers' => User::where('role', 'passenger')->count(),
            ],
        ]);
    }

    /**
     * Driver status change logs for admin dashboard.
     */
    public function driverStatusLogs(Request $request)
    {
        $query = DriverStatusLog::with(['user' => function ($q) {
            $q->select('id', 'display_name', 'phone');
        }])->orderByDesc('id');

        if ($driverId = $request->query('driver_id')) {
            $query->where('user_id', $driverId);
        }

        $logs = $query->paginate(100);

        return response()->json([
            'data' => $logs,
        ]);
    }

    /**
     * Driver location send logs (one row per /driver/location call).
     */
    public function driverLocationLogs(Request $request)
    {
        $query = DriverLocationLog::with(['user' => function ($q) {
            $q->select('id', 'display_name', 'phone');
        }])->orderByDesc('id');

        if ($driverId = $request->query('driver_id')) {
            $query->where('user_id', $driverId);
        }

        $logs = $query->paginate(200);

        return response()->json([
            'data' => $logs,
        ]);
    }

    /**
     * God's Eye: all online drivers with their last known position.
     */
    public function driverLocations()
    {
        $drivers = User::where('role', 'driver')
            ->where('is_online', true)
            ->whereNotNull('last_latitude')
            ->whereNotNull('last_longitude')
            ->get(['id', 'display_name', 'phone', 'last_latitude', 'last_longitude', 'last_location_at']);

        return response()->json([
            'data' => $drivers->map(fn ($d) => [
                'id' => $d->id,
                'display_name' => $d->display_name,
                'phone' => $d->phone,
                'latitude' => (float) $d->last_latitude,
                'longitude' => (float) $d->last_longitude,
                'last_location_at' => $d->last_location_at?->toIso8601String(),
            ]),
        ]);
    }
}

