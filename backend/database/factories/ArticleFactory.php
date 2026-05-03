<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Article> */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sku' => strtoupper($this->faker->unique()->bothify('ART-####')),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence,
            'group_id' => null,
            'default_gestiune_id' => null,
            'vat_rate' => 19.00,
            'price' => $this->faker->randomFloat(2, 1, 500),
            'unit' => 'buc',
            'plu' => null,
            'is_active' => true,
        ];
    }
}
