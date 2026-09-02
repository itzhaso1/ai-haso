<?php

namespace Tests\Feature\Feature\Security;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_and_known_phone_share_generic_request_response(): void
    {
        [$user, $workspace] = $this->makeWorkspace();

        $unknown = $this->postJson('/api/otp/request', ['phone' => '+966511111111']);
        $known = $this->postJson('/api/otp/request', ['phone' => $user->phone]);

        $unknown->assertOk()->assertJsonPath(
            'message',
            'If the phone number is registered, a verification code will be sent.'
        );
        $known->assertOk()->assertJsonPath(
            'message',
            'If the phone number is registered, a verification code will be sent.'
        );
    }

    public function test_invalid_otp_does_not_reveal_account_existence(): void
    {
        [$user, $workspace] = $this->makeWorkspace();

        $this->postJson('/api/otp/verify', [
            'phone' => $user->phone,
            'otp' => '000000',
            'workspace_id' => $workspace->id,
        ])->assertStatus(401)->assertJsonPath('message', 'Invalid or expired verification code.');

        $this->postJson('/api/otp/verify', [
            'phone' => '+966522222222',
            'otp' => '000000',
            'workspace_id' => $workspace->id,
        ])->assertStatus(401)->assertJsonPath('message', 'Invalid or expired verification code.');
    }

    public function test_otp_request_is_throttled_per_phone(): void
    {
        [$user] = $this->makeWorkspace();
        $otp = app(OtpService::class);

        for ($i = 0; $i < 5; $i++) {
            $otp->request($user->phone);
        }

        $this->expectException(\RuntimeException::class);
        $otp->request($user->phone);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function makeWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => 'company',
        ]);
        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }
}
