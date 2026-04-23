<?php

use App\Models\Article;
use App\Models\Barcode;
use App\Models\Gestiune;
use App\Models\StockLevel;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

it('allows the same barcode across two tenants but not within one tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $articleA = Article::factory()->create(['tenant_id' => $tenantA->id, 'sku' => 'SHARED-1']);
    $articleB = Article::factory()->create(['tenant_id' => $tenantB->id, 'sku' => 'SHARED-2']);

    Barcode::create([
        'tenant_id' => $tenantA->id,
        'article_id' => $articleA->id,
        'barcode' => '5949012345678',
        'type' => 'ean13',
    ]);

    // Same barcode in a different tenant: allowed.
    expect(fn () => Barcode::create([
        'tenant_id' => $tenantB->id,
        'article_id' => $articleB->id,
        'barcode' => '5949012345678',
        'type' => 'ean13',
    ]))->not->toThrow(QueryException::class);

    // Same barcode in the same tenant: rejected.
    expect(fn () => Barcode::create([
        'tenant_id' => $tenantA->id,
        'article_id' => $articleA->id,
        'barcode' => '5949012345678',
        'type' => 'ean13',
    ]))->toThrow(QueryException::class);
});

it('enforces the article_id + gestiune_id unique composite on stock_levels', function () {
    $tenant = Tenant::factory()->create();
    $article = Article::factory()->create(['tenant_id' => $tenant->id]);
    $gestiune = Gestiune::factory()->create(['tenant_id' => $tenant->id]);

    StockLevel::create([
        'tenant_id' => $tenant->id,
        'article_id' => $article->id,
        'gestiune_id' => $gestiune->id,
        'quantity' => 10,
    ]);

    expect(fn () => StockLevel::create([
        'tenant_id' => $tenant->id,
        'article_id' => $article->id,
        'gestiune_id' => $gestiune->id,
        'quantity' => 20,
    ]))->toThrow(QueryException::class);
});
