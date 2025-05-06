<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Vegetables',
                'icon' => 'vegetables-icon.svg',
                'color' => '#d3f9d8',
            ],
            [
                'name' => 'Fruits',
                'icon' => 'fruits-icon.svg',
                'color' => '#ffd8d8',
            ],
            [
                'name' => 'Beverages',
                'icon' => 'beverages-icon.svg',
                'color' => '#fff3bf',
            ],
            [
                'name' => 'Grocery',
                'icon' => 'grocery-icon.svg',
                'color' => '#e5dbff',
            ],
            [
                'name' => 'Edible oil',
                'icon' => 'oil-icon.svg',
                'color' => '#c5f6fa',
            ],
            [
                'name' => 'Household',
                'icon' => 'household-icon.svg',
                'color' => '#ffdeeb',
            ],
            [
                'name' => 'Babycare',
                'icon' => 'baby-icon.svg',
                'color' => '#d0ebff',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
