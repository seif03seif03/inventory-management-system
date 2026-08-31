<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => $this->faker->word() . ' ' . $this->faker->randomNumber(3),
            'sku' => strtoupper(Str::random(8)),
            'barcode' => $this->faker->unique()->ean13(),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'minimum_stock' => $this->faker->numberBetween(5, 50),
            'active' => true,
        ];
    }
}
