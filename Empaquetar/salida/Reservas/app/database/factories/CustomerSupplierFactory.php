<?php

namespace Database\Factories;

use App\Models\CustomerSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating test CustomerSupplier instances.
 */
class CustomerSupplierFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CustomerSupplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('CS-####'),
            'name' => $this->faker->company(),
            'type' => $this->faker->randomElement(['supplier', 'customer']),
            'origin' => $this->faker->randomElement(['national', 'international']),
            'abbr' => $this->faker->unique()->lexify('???'),
            'amount' => $this->faker->randomFloat(2, 100, 100000),
            'contract_type' => $this->faker->randomElement(['service', 'product']),
            'mail' => $this->faker->email(),
            'phone' => $this->faker->phoneNumber(),
            'mintur' => $this->faker->boolean(),
            'state' => $this->faker->boolean(),
            'chain_id' => null,
            'province_id' => null,
            'brigade_id' => null,
        ];
    }
}
