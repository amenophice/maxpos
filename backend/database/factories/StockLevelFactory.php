<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Gestiune;
use App\Models\StockLevel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockLevel> */
class StockLevelFactory extends Factory
{
    protected $model = StockLevel::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'article_id' => Article::factory(),
            'gestiune_id' => Gestiune::factory(),
            'quantity' => $this->faker->randomFloat(3, 5, 200),
        ];
    }
}
