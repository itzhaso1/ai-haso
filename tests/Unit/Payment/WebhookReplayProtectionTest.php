<?php

namespace Tests\Unit\Payment;

use App\Events\PaymentConfirmed;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Models\Workspace;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebhookReplayProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_webhook_event_is_processed_once(): void
    {
        Event::fake([PaymentConfirmed::class]);

        [$owner, $workspace] = $this->createWorkspaceOwner('company');
        $gateway = PaymentGateway::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'provider' => 'local',
            'status' => 'connected',
            'config' => [],
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'order_number' => 'APT-ORD-00009999',
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'shipping_status' => 'not_shipped',
            'currency' => 'SAR',
            'subtotal' => 100,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 100,
            'placed_at' => now(),
        ]);

        $payment = Payment::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'order_id' => $order->id,
            'payment_gateway_id' => $gateway->id,
            'provider' => 'local',
            'provider_payment_id' => 'pay_123',
            'idempotency_key' => 'idem_123',
            'status' => 'pending',
            'amount' => 100,
            'currency' => 'SAR',
            'payment_link' => 'https://example.test/pay',
            'provider_payload' => [],
        ]);

        /** @var PaymentService $service */
        $service = app(PaymentService::class);
        $payload = [
            'event_id' => 'evt_replay_1',
            'status' => 'paid',
            'reference' => $order->order_number,
        ];

        $service->processWebhook('local', [], $payload, json_encode($payload));
        $service->processWebhook('local', [], $payload, json_encode($payload));

        $this->assertSame(1, WebhookEvent::withoutGlobalScopes()->count());
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        Event::assertDispatchedTimes(PaymentConfirmed::class, 1);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function createWorkspaceOwner(string $workspaceType): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'owner_user_id' => $user->id,
            'type' => $workspaceType,
            'status' => 'active',
        ]);

        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }
}
