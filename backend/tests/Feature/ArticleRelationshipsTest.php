<?php

use App\Models\Article;
use App\Models\Barcode;
use App\Models\Gestiune;
use App\Models\Group;
use App\Models\StockLevel;
use App\Models\Tenant;

it('links an Article to its Group, Barcodes and StockLevels', function () {
    $tenant = Tenant::factory()->create();
    $group = Group::factory()->create(['tenant_id' => $tenant->id]);
    $article = Article::factory()->create([
        'tenant_id' => $tenant->id,
        'group_id' => $group->id,
    ]);

    Barcode::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'article_id' => $article->id,
    ]);

    $gestiune = Gestiune::factory()->create(['tenant_id' => $tenant->id]);
    StockLevel::factory()->create([
        'tenant_id' => $tenant->id,
        'article_id' => $article->id,
        'gestiune_id' => $gestiune->id,
    ]);

    $fresh = Article::with(['group', 'barcodes', 'stockLevels'])->find($article->id);

    expect($fresh->group->id)->toBe($group->id)
        ->and($fresh->barcodes)->toHaveCount(2)
        ->and($fresh->stockLevels)->toHaveCount(1);
});
