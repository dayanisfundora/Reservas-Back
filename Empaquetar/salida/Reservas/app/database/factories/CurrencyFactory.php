<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating test Currency instances.
 */
class CurrencyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Currency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->currencyCode(),
            'description' => $this->faker->word(),
            'abbr' => $this->faker->unique()->lexify('???'),
            'rate' => $this->faker->randomFloat(4, 0.1, 100),
            'base_currency' => $this->faker->boolean(),
            'state' => $this->faker->boolean(),
        ];
    }
}
