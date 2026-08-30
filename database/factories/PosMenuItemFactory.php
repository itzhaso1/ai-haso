<?php

namespace Database\Factories;

use App\Models\PosMenuItem;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosMenuItem>
 */
class PosMenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(2, true),
            'item_type' => fake()->randomElement(['مشروبات', 'حلويات', 'وجبات']),
            'price' => fake()->randomFloat(2, 2, 80),
            'currency' => 'USD',
            'image_path' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 30),
        ];
    }
}
