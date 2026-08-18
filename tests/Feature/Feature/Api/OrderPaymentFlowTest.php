<?php

namespace Tests\Feature\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_and_payment_webhook_update_statuses(): void
    {
        $this->seed(FoundationSeeder::class);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_user_id' => $user->id, 'type' => 'company']);
        $workspace->users()->attach($user->id, [
            'membership_role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $product = Product::withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Runner Shoe',
            'slug' => 'runner-shoe',
            'sku' => 'SKU-RUN-42',
            'price' => 150,
            'currency' => 'USD',
            'stock' => 10,
            'status' => 'active',
        ]);

        $token = $user->createToken('api')->plainTextToken;

        $orderResponse = $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/orders', [
                'currency' => 'USD',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                    ],
                ],
            ])->assertCreated();

        $orderId = $orderResponse->json('data.id');
        $orderNumber = $orderResponse->json('data.order_number');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8,
        ]);

        $paymentResponse = $this->withToken($token)
            ->withHeader('X-Workspace-Id', (string) $workspace->id)
            ->postJson('/api/payments', [
                'order_id' => $orderId,
            ])
            ->assertCreated();

        $this->assertNotEmpty($paymentResponse->json('data.payment_link'));

        $this->postJson('/api/webhooks/payments/local', [
            'event_id' => 'evt_local_12345',
            'status' => 'paid',
            'reference' => $orderNumber,
        ])->assertAccepted();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $orderId,
            'status' => 'paid',
        ]);

        $this->postJson('/api/webhooks/payments/local', [
            'event_id' => 'evt_local_12345',
            'status' => 'paid',
            'reference' => $orderNumber,
        ])->assertAccepted();

        $this->assertDatabaseCount('webhook_events', 1);
    }
}
