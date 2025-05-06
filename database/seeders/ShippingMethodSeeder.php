<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        ShippingMethod::create([
            'name' => 'Standard Shipping',
            'cost' => 5.99,
            'estimated_days' => 5,
            'description' => 'Delivery within 3-7 business days',
            'is_active' => true,
        ]);

        ShippingMethod::create([
            'name' => 'Express Shipping',
            'cost' => 12.99,
            'estimated_days' => 2,
            'description' => 'Delivery within 1-3 business days',
            'is_active' => true,
        ]);

        ShippingMethod::create([
            'name' => 'Next Day Delivery',
            'cost' => 19.99,
            'estimated_days' => 1,
            'description' => 'Delivery next business day for orders placed before 2PM',
            'is_active' => true,
        ]);
    }
}
