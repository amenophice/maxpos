<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->name,
            'cui' => null,
            'registration_number' => null,
            'address' => $this->faker->streetAddress,
            'city' => $this->faker->city,
            'county' => 'Bihor',
            'is_company' => false,
            'email' => $this->faker->safeEmail,
            'phone' => $this->faker->phoneNumber,
        ];
    }

    public function company(): static
    {
        return $this->state(fn () => [
            'is_company' => true,
            'name' => $this->faker->company,
            'cui' => 'RO'.$this->faker->numerify('########'),
            'registration_number' => 'J'.$this->faker->numerify('##/####/####'),
        ]);
    }
}
