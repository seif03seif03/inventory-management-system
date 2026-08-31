<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->city() . ' Warehouse',
            'location' => $this->faker->address(),
            'description' => $this->faker->sentence(),
            'active' => true,
        ];
    }
}
