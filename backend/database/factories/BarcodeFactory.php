<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Barcode;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Barcode> */
class BarcodeFactory extends Factory
{
    protected $model = Barcode::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'article_id' => Article::factory(),
            'barcode' => (string) $this->faker->unique()->ean13,
            'type' => 'ean13',
        ];
    }
}
