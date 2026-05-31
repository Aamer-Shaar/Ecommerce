<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {

         \Illuminate\Database\Eloquent\Model::unguard();
        User::create([
            'name'     => 'Admin User',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        User::create([
            'name'     => 'Regular User',
            'email'    => 'user@example.com',
            'password' => Hash::make('password'),
            'is_admin' => false,
        ]);

        User::factory(50)->create(['is_admin' => false]);


        $categories = Category::factory(50)->create();

        $categories->each(function ($category) {
            $products = Product::factory(10)->create([
                'category_id' => $category->id,
            ]);

            $products->each(function ($product) {
                Inventory::factory()->create([
                    'product_id' => $product->id,
                    'quantity'   => fake()->numberBetween(20, 200),
                ]);
            });
        });

    }
}