<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiAuth\LoginRequest;
use App\Http\Requests\ApiAuth\RegisterRequest;
use App\Http\Requests\ApiAuth\RequestOtpRequest;
use App\Http\Requests\ApiAuth\VerifyOtpRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Auth\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\NewAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $payload = $this->authenticationService->register($request->validated());

        return response()->json([
            'message' => 'Registration completed.',
            'data' => $this->responsePayload($payload),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->authenticationService->loginWithPassword(
            $request->string('email_or_phone')->toString(),
            $request->string('password')->toString(),
            $request->integer('workspace_id'),
        );

        return response()->json([
            'message' => 'Login successful.',
            'data' => $this->responsePayload($payload),
        ]);
    }

    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $otp = $this->authenticationService->requestOtp(
            $request->string('phone')->toString()
        );

        return response()->json([
            'message' => 'OTP has been issued.',
            'data' => app()->isProduction() ? null : ['otp' => $otp],
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $payload = $this->authenticationService->loginWithOtp(
            $request->string('phone')->toString(),
            $request->string('otp')->toString(),
            $request->integer('workspace_id'),
        );

        return response()->json([
            'message' => 'OTP verified.',
            'data' => $this->responsePayload($payload),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'user' => $user,
                'workspaces' => $user?->workspaces()->get(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * @param  array{user:User,workspace:Workspace,token:NewAccessToken}  $payload
     */
    private function responsePayload(array $payload): array
    {
        return [
            'user' => $payload['user'],
            'workspace' => $payload['workspace'],
            'token' => $payload['token']->plainTextToken,
        ];
    }
}
