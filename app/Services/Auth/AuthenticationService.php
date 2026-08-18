<?php

namespace App\Services\Auth;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Workspace\WorkspaceService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

class AuthenticationService
{
    public function __construct(
        private readonly WorkspaceService $workspaceService,
        private readonly OtpService $otpService,
    ) {}

    /**
     * @param  array{name:string,email:string,password:string,phone?:string,workspace_type:string,workspace_name?:string}  $payload
     */
    public function register(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'] ?? null,
                'password' => $payload['password'],
            ]);

            $workspace = $this->workspaceService->createForUser(
                $user,
                $payload['workspace_type'],
                $payload['workspace_name'] ?? null
            );

            $this->attachDefaultPlan($workspace->id, $workspace->type);

            $token = $this->issueToken($user, $workspace->id, ['*']);

            return compact('user', 'workspace', 'token');
        });
    }

    public function loginWithPassword(string $emailOrPhone, string $password, int $workspaceId): array
    {
        $user = User::query()
            ->where('email', $emailOrPhone)
            ->orWhere('phone', $emailOrPhone)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $workspace = $user->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->wherePivot('status', 'active')
            ->first();

        if (! $workspace) {
            throw new ModelNotFoundException('Workspace not found for this user.');
        }

        $token = $this->issueToken($user, $workspace->id, ['*']);

        return compact('user', 'workspace', 'token');
    }

    public function requestOtp(string $phone): string
    {
        return $this->otpService->request($phone);
    }

    public function loginWithOtp(string $phone, string $otp, int $workspaceId): array
    {
        if (! $this->otpService->verify($phone, $otp)) {
            throw new AuthenticationException('Invalid OTP.');
        }

        $user = User::query()
            ->where('phone', $phone)
            ->firstOrFail();

        $workspace = $user->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->wherePivot('status', 'active')
            ->firstOrFail();

        $user->forceFill(['phone_verified_at' => now()])->save();

        $token = $this->issueToken($user, $workspace->id, ['*']);

        return compact('user', 'workspace', 'token');
    }

    private function issueToken(User $user, int $workspaceId, array $abilities): NewAccessToken
    {
        $token = $user->createToken(
            name: 'api',
            abilities: $abilities,
            expiresAt: now()->addDays(30)
        );

        $token->accessToken->forceFill(['workspace_id' => $workspaceId])->save();

        return $token;
    }

    private function attachDefaultPlan(int $workspaceId, string $workspaceType): void
    {
        $plan = Plan::query()
            ->where('workspace_type', $workspaceType)
            ->where('is_active', true)
            ->orderBy('price')
            ->first();

        if (! $plan) {
            return;
        }

        Subscription::query()->create([
            'workspace_id' => $workspaceId,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(14),
        ]);
    }
}
