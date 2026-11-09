<?php

namespace Database\Factories;

use App\Models\Annex;
use App\Models\ContractAbroad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating test Annex instances.
 */
class AnnexFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Annex::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'total_amount' => $this->faker->randomFloat(2, 100, 10000),
            'contract_abroad_id' => ContractAbroad::factory(),
        ];
    }
}
