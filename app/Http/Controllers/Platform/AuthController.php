<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = auth('platform_admin')->getProvider()->retrieveByCredentials([
            'email' => $validated['email'],
        ]);

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            return response()->json(['message' => 'Invalid platform credentials.'], 422);
        }

        Auth::guard('platform_admin')->login($admin);
        $admin->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'message' => 'Platform admin authenticated.',
            'data' => ['admin' => $admin],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        auth('platform_admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Platform admin logged out.']);
    }

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'data' => [
                'admin' => auth('platform_admin')->user(),
                'stats' => [
                    'users' => User::query()->count(),
                    'workspaces' => Workspace::query()->count(),
                    'subscriptions' => Subscription::query()->count(),
                ],
            ],
        ]);
    }
}
