<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Api\Mobile\Concerns\ResolvesMobileWorkspace;
use App\Http\Controllers\Api\Mobile\MobileController;
use App\Http\Resources\Mobile\UserResource;
use App\Http\Resources\Mobile\WorkspaceResource;
use App\Services\Mobile\MobileAuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends MobileController
{
    use ResolvesMobileWorkspace;

    public function __construct(
        private readonly MobileAuthService $mobileAuthService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required_without:phone', 'nullable', 'string'],
            'phone' => ['required_without:email', 'nullable', 'string'],
            'password' => ['required', 'string'],
            'workspace_id' => ['nullable', 'integer'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'max:32'],
        ]);

        $identifier = trim((string) ($validated['email'] ?? $validated['phone'] ?? ''));
        if ($identifier === '') {
            return $this->fail('يرجى إدخال البريد الإلكتروني أو رقم الجوال.', 422);
        }

        try {
            $result = $this->mobileAuthService->loginWithPassword(
                emailOrPhone: $identifier,
                password: $validated['password'],
                workspaceId: isset($validated['workspace_id']) ? (int) $validated['workspace_id'] : null,
                device: [
                    'device_name' => $validated['device_name'] ?? null,
                    'device_type' => $validated['device_type'] ?? 'mobile',
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                ],
            );
        } catch (AuthenticationException) {
            return $this->fail('بيانات الدخول غير صحيحة.', 401);
        } catch (ModelNotFoundException $exception) {
            return $this->fail($exception->getMessage(), 404);
        }

        return $this->ok([
            'token' => $result['token']->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => optional($result['token']->accessToken->expires_at)?->toIso8601String(),
            'user' => new UserResource($result['user']),
            'workspace' => $result['workspace'] ? new WorkspaceResource($result['workspace']) : null,
            'workspaces' => WorkspaceResource::collection($result['workspaces']),
        ], message: 'تم تسجيل الدخول بنجاح.');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->fail('غير مصرح.', 401);
        }

        $this->mobileAuthService->logoutCurrent($user, $user->currentAccessToken());

        return $this->ok(message: 'تم تسجيل الخروج بنجاح.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->fail('غير مصرح.', 401);
        }

        $workspaces = $user->workspaces()
            ->wherePivot('status', 'active')
            ->get();

        $workspaceId = $user->currentAccessToken()?->workspace_id;
        $workspace = $workspaceId
            ? $workspaces->firstWhere('id', $workspaceId)
            : $workspaces->first();

        return $this->ok([
            'user' => new UserResource($user),
            'workspace' => $workspace ? new WorkspaceResource($workspace) : null,
            'workspaces' => WorkspaceResource::collection($workspaces),
        ]);
    }
}
