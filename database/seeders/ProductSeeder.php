<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $fruitsCategoryId = Category::where('name', 'Fruits')->first()->id;
        $vegetablesCategoryId = Category::where('name', 'Vegetables')->first()->id;

        $products = [
            [
                'name' => 'Fresh Peach',
                'description' => 'Sweet, juicy peaches freshly harvested.',
                'price' => 8.00,
                'unit' => 'dozen',
                'unit_value' => 12,
                'image' => 'products/peach.png',
                'is_featured' => true,
                'is_new' => false,
                'is_organic' => false,
                'stock' => 100,
                'category_id' => $fruitsCategoryId,
            ],
            [
                'name' => 'Avocado',
                'description' => 'Creamy, nutritious avocados perfect for guacamole or toast.',
                'price' => 7.00,
                'unit' => 'lbs',
                'unit_value' => 2.0,
                'image' => 'products/avocado.png',
                'is_featured' => true,
                'is_new' => true,
                'is_organic' => false,
                'stock' => 50,
                'category_id' => $vegetablesCategoryId,
            ],
            [
                'name' => 'Pineapple',
                'description' => 'Sweet and tangy pineapple, great for snacking or desserts.',
                'price' => 9.90,
                'unit' => 'lbs',
                'unit_value' => 1.50,
                'image' => 'products/pineapple.png',
                'is_featured' => true,
                'is_new' => false,
                'is_organic' => false,
                'stock' => 40,
                'category_id' => $fruitsCategoryId,
            ],
            [
                'name' => 'Black Grapes',
                'description' => 'Sweet and juicy black grapes, perfect for snacking.',
                'price' => 7.05,
                'unit' => 'lbs',
                'unit_value' => 5.0,
                'image' => 'products/grapes.png',
                'is_featured' => true,
                'is_new' => false,
                'is_organic' => false,
                'discount_percentage' => 15,
                'original_price' => 8.30,
                'stock' => 75,
                'category_id' => $fruitsCategoryId,
            ],
            [
                'name' => 'Pomegranate',
                'description' => 'Antioxidant-rich pomegranate with sweet-tart flavor.',
                'price' => 2.09,
                'unit' => 'lbs',
                'unit_value' => 1.50,
                'image' => 'products/pomegranate.png',
                'is_featured' => true,
                'is_new' => true,
                'is_organic' => false,
                'stock' => 30,
                'category_id' => $fruitsCategoryId,
            ],
            [
                'name' => 'Fresh Broccoli',
                'description' => 'Nutritious broccoli, perfect for steaming or stir-frying.',
                'price' => 3.00,
                'unit' => 'kg',
                'unit_value' => 1.0,
                'image' => 'products/broccoli.png',
                'is_featured' => true,
                'is_new' => false,
                'is_organic' => false,
                'stock' => 60,
                'category_id' => $vegetablesCategoryId,
            ],
            [
                'name' => 'Organic Lemons',
                'description' => 'Organic Mountain works as a seller for many organic growers of organic lemons. Organic lemons are easy to spot in your produce aisle. They are just like regular lemons, but they will usually have a few more scars on the outside of the lemon skin. Organic lemons are considered to be the world\'s finest lemon for juicing.',
                'price' => 2.22,
                'unit' => 'lbs',
                'unit_value' => 1.50,
                'image' => 'products/lemons.png',
                'is_featured' => false,
                'is_new' => false,
                'is_organic' => true,
                'stock' => 90,
                'category_id' => $fruitsCategoryId,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
