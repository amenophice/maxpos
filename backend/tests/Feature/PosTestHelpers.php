<?php

use App\Models\Article;
use App\Models\CashSession;
use App\Models\Gestiune;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReceiptService;
use Database\Seeders\RolesSeeder;

/**
 * Bootstraps a tenant + location + gestiune + N articles + a user with roles.
 * Returns ['tenant', 'location', 'gestiune', 'user', 'articles'].
 */
function posFixture(array $roleNames = ['cashier'], ?array $articleSpecs = null, int $initialStockPerArticle = 100): array
{
    test()->seed(RolesSeeder::class);

    $tenant = Tenant::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $gestiune = Gestiune::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'type' => 'cantitativ-valoric',
    ]);

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->syncRoles($roleNames);

    $articleSpecs ??= [
        ['price' => 10.00, 'vat_rate' => 19.00],
        ['price' => 5.50, 'vat_rate' => 9.00],
        ['price' => 100.00, 'vat_rate' => 19.00],
    ];

    $articles = collect($articleSpecs)->map(function ($spec, $i) use ($tenant, $gestiune, $initialStockPerArticle) {
        $article = Article::factory()->create([
            'tenant_id' => $tenant->id,
            'default_gestiune_id' => $gestiune->id,
            'sku' => 'SKU-'.$i.'-'.bin2hex(random_bytes(3)),
            'price' => $spec['price'],
            'vat_rate' => $spec['vat_rate'],
        ]);
        StockLevel::create([
            'tenant_id' => $tenant->id,
            'article_id' => $article->id,
            'gestiune_id' => $gestiune->id,
            'quantity' => $initialStockPerArticle,
        ]);

        return $article;
    })->all();

    return compact('tenant', 'location', 'gestiune', 'user', 'articles');
}

function stockOf(string $articleId, string $gestiuneId): string
{
    return (string) StockLevel::where('article_id', $articleId)
        ->where('gestiune_id', $gestiuneId)
        ->value('quantity');
}

function openSessionFor(User $user, Location $location, float $initial = 100.00): CashSession
{
    return app(ReceiptService::class)->openCashSession($location, $user, $initial);
}
