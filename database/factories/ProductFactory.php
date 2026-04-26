<?php

namespace Database\Factories;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    { 
         $name = $this->faker->unique()->words(2, true);
        return [
            'name' => $name, 'slug' => Str::slug($name), 'description' => $this->faker->paragraph(),
            'price' => $this->faker->randomFloat(2, 10, 500), 'category_id' => \App\Models\Category::factory()
        ];
    }
}
