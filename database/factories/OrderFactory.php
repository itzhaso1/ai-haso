<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'customer_id' => Customer::factory(),
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'shipping_status' => 'not_shipped',
            'currency' => 'USD',
            'subtotal' => 100,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 100,
            'placed_at' => now(),
        ];
    }
}
