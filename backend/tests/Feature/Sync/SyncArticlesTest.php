<?php

use App\Models\Article;
use App\Models\Gestiune;
use App\Models\Group;
use Laravel\Sanctum\Sanctum;

it('upserts groups, gestiuni, and articles from Saga payload', function () {
    $fix = posFixture(['cashier']);
    Sanctum::actingAs($fix['user']);

    $payload = [
        'groups' => [
            ['saga_cod' => 'G001', 'name' => 'Băuturi'],
            ['saga_cod' => 'G002', 'name' => 'Lactate'],
        ],
        'gestiuni' => [
            ['saga_cod' => 'M1', 'name' => 'Magazin 1', 'location_id' => $fix['location']->id, 'type' => 'cantitativ-valoric'],
        ],
        'articles' => [
            [
                'sku' => 'SAGA-001',
                'name' => 'Apă minerală',
                'price' => 3.50,
                'vat_rate' => 19.00,
                'unit' => 'buc',
                'group_saga_cod' => 'G001',
                'gestiune_saga_cod' => 'M1',
            ],
            [
                'sku' => 'SAGA-002',
                'name' => 'Iaurt natural',
                'price' => 5.00,
                'group_saga_cod' => 'G002',
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/sync/articles', $payload);

    $response->assertOk()
        ->assertJsonPath('data.groups', 2)
        ->assertJsonPath('data.gestiuni', 1)
        ->assertJsonPath('data.articles', 2);

    // Verify groups created with saga_cod
    $group = Group::withoutGlobalScopes()->where('saga_cod', 'G001')->first();
    expect($group)->not->toBeNull()
        ->and($group->name)->toBe('Băuturi');

    // Verify article linked to group
    $article = Article::withoutGlobalScopes()->where('sku', 'SAGA-001')->first();
    expect($article)->not->toBeNull()
        ->and($article->group_id)->toBe($group->id)
        ->and((float) $article->price)->toBe(3.50);

    // Verify gestiune linked
    $gestiune = Gestiune::withoutGlobalScopes()->where('saga_cod', 'M1')->first();
    expect($gestiune)->not->toBeNull()
        ->and($article->default_gestiune_id)->toBe($gestiune->id);
});

it('updates existing articles on re-sync', function () {
    $fix = posFixture(['cashier']);
    Sanctum::actingAs($fix['user']);

    // First sync
    $this->postJson('/api/v1/sync/articles', [
        'articles' => [['sku' => 'UPD-001', 'name' => 'Pâine', 'price' => 2.00]],
    ])->assertOk();

    // Second sync — update price
    $this->postJson('/api/v1/sync/articles', [
        'articles' => [['sku' => 'UPD-001', 'name' => 'Pâine albă', 'price' => 2.50]],
    ])->assertOk();

    $articles = Article::withoutGlobalScopes()
        ->where('sku', 'UPD-001')
        ->get();

    expect($articles)->toHaveCount(1)
        ->and($articles->first()->name)->toBe('Pâine albă')
        ->and((float) $articles->first()->price)->toBe(2.50);
});

it('rejects invalid payload', function () {
    $fix = posFixture(['cashier']);
    Sanctum::actingAs($fix['user']);

    $response = $this->postJson('/api/v1/sync/articles', [
        'articles' => [['name' => 'Missing SKU', 'price' => 1.00]],
    ]);

    $response->assertStatus(422);
});
