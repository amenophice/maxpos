<?php

use App\Models\Article;
use App\Models\Barcode;
use App\Models\StockLevel;
use Laravel\Sanctum\Sanctum;

it('returns articles with barcodes and stock, plus groups/customers/gestiuni', function () {
    $f = posFixture();
    Barcode::create([
        'tenant_id' => $f['tenant']->id,
        'article_id' => $f['articles'][0]->id,
        'barcode' => '5949000000017',
        'type' => 'ean13',
    ]);

    Sanctum::actingAs($f['user']);

    $r = $this->getJson('/api/v1/pos/bootstrap')->assertOk();

    expect($r->json('data.articles'))->not->toBeEmpty()
        ->and($r->json('data.articles.0'))->toHaveKeys(['id', 'sku', 'barcodes', 'stock_levels', 'updated_at'])
        ->and($r->json('data.gestiuni'))->not->toBeEmpty()
        ->and($r->json('meta.server_time'))->not->toBeNull();
});

it('supports incremental ?since= by excluding older rows', function () {
    $f = posFixture();
    $oldArticle = Article::factory()->create([
        'tenant_id' => $f['tenant']->id,
        'updated_at' => now()->subHour(),
    ]);
    $since = now()->subMinutes(10)->toIso8601String();
    StockLevel::create([
        'tenant_id' => $f['tenant']->id,
        'article_id' => $oldArticle->id,
        'gestiune_id' => $f['gestiune']->id,
        'quantity' => 1,
    ]);

    Sanctum::actingAs($f['user']);

    $ids = collect($this->getJson('/api/v1/pos/bootstrap?since='.urlencode($since))
        ->assertOk()
        ->json('data.articles'))->pluck('id');

    expect($ids)->not->toContain($oldArticle->id);
});
