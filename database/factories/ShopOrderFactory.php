<?php

namespace Database\Factories;

use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopOrder>
 */
class ShopOrderFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 3);
        $unit = fake()->numberBetween(200, 3000);

        return [
            'user_id' => User::factory(),
            'shop_product_id' => ShopProduct::factory()->published(),
            'quantity' => $qty,
            'unit_price_etb' => $unit,
            'total_etb' => $unit * $qty,
            'phone' => '09'.fake()->numerify('########'),
            'status' => 'pending',
        ];
    }
}
