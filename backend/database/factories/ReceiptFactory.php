<?php

namespace Database\Factories;

use App\Models\CashSession;
use App\Models\Location;
use App\Models\Receipt;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Receipt> */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'location_id' => Location::factory(),
            'cash_session_id' => CashSession::factory(),
            'number' => $this->faker->unique()->numberBetween(1, 1_000_000),
            'status' => 'draft',
        ];
    }
}
