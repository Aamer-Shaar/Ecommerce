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
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create(['name' => 'Admin User','email' => 'admin@example.com','password' => Hash::make('password'),'is_admin' => true]);
        User::create(['name' => 'Regular User','email' => 'user@example.com','password' => Hash::make('password'),'is_admin' => false]);
        $categories = Category::factory(5)->create();
        $categories->each(function ($category) {
            $products = Product::factory(4)->create(['category_id' => $category->id]);
            $products->each(fn($product) => Inventory::factory()->create(['product_id' => $product->id]));
        });
    }
}
