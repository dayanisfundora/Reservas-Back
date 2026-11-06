<?php

namespace Database\Factories;

use App\Models\ContractAbroad;
use App\Models\Currency;
use App\Models\CustomerSupplier;
use App\Models\EquipmentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating test ContractAbroad instances.
 */
class ContractAbroadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ContractAbroad::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_number' => $this->faker->unique()->numerify('CONTRACT-####'),
            'contract_code' => $this->faker->unique()->bothify('????-####'),
            'contract_name' => $this->faker->sentence(),
            'country' => $this->faker->country(),
            'contracted_value' => $this->faker->randomFloat(2, 1000, 100000),
            'contracted_year' => $this->faker->year(),
            'contracted_place' => $this->faker->city(),
            'delivery_time' => $this->faker->numberBetween(1, 365),
            'expiration_date' => $this->faker->dateTimeBetween('+1 month', '+1 year'),
            'signature_date' => $this->faker->date(),
            'year_validity' => $this->faker->numberBetween(1, 10),
            'state' => $this->faker->boolean(),
            'equipment_type_id' => EquipmentType::factory(),
            'customer_supplier_id' => CustomerSupplier::factory(),
            'currency_id' => Currency::factory(),
            'pending_value' => $this->faker->randomFloat(2, 0, 50000),
        ];
    }
}
