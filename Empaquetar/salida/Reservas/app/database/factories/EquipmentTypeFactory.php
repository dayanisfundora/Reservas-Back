<?php

namespace Database\Factories;

use App\Models\EquipmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating test EquipmentType instances.
 */
class EquipmentTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EquipmentType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => $this->faker->sentence(),
            'code' => $this->faker->unique()->bothify('EQ-####'),
            'origin' => $this->faker->country(),
            'fabricator' => $this->faker->boolean(),
            'state' => $this->faker->boolean(),
        ];
    }
}
