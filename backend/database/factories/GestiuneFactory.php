<?php

namespace Database\Factories;

use App\Models\Gestiune;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Gestiune> */
class GestiuneFactory extends Factory
{
    protected $model = Gestiune::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'location_id' => Location::factory(),
            'name' => $this->faker->randomElement(['Raion principal', 'Depozit', 'Casă']),
            'type' => $this->faker->randomElement(['global-valoric', 'cantitativ-valoric']),
            'is_active' => true,
        ];
    }
}
