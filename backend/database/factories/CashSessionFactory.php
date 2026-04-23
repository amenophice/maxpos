<?php

namespace Database\Factories;

use App\Models\CashSession;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CashSession> */
class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'location_id' => Location::factory(),
            'user_id' => User::factory(),
            'opened_at' => now(),
            'initial_cash' => 100.00,
            'status' => 'open',
        ];
    }
}
