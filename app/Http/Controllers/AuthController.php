<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function registerPassenger(Request $request)
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
            'role' => 'passenger',
            'is_active' => true,
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], Response::HTTP_CREATED);
    }

    public function registerDriver(Request $request)
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
            'is_active' => false,
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], Response::HTTP_CREATED);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        /** @var \App\Models\User|null $user */
        $user = User::where('phone', $validated['phone'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'errors' => [
                    'message' => 'Invalid credentials.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->is_active) {
            return response()->json([
                'errors' => [
                    'message' => 'Account is not active.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'data' => [
                'user' => $request->user(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        return response()->json([
            'data' => [
                'message' => 'Logged out.',
            ],
        ]);
    }
}

