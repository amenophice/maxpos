<?php

use App\Models\Receipt;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('completes a full receipt in a single call', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);

    $session = openSessionFor($f['user'], $f['location']);

    $response = $this->postJson('/api/v1/pos/checkout', [
        'cash_session_id' => $session->id,
        'client_local_id' => 'local-abc-123',
        'items' => [
            ['article_id' => $f['articles'][0]->id, 'quantity' => 2],
            ['article_id' => $f['articles'][2]->id, 'quantity' => 1],
        ],
        'payments' => [
            ['method' => 'cash', 'amount' => 60],
            ['method' => 'card', 'amount' => 60],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.client_local_id', 'local-abc-123');

    expect(stockOf($f['articles'][0]->id, $f['gestiune']->id))->toBe('98.000')
        ->and(stockOf($f['articles'][2]->id, $f['gestiune']->id))->toBe('99.000');
});

it('returns the same receipt on idempotent retry with identical client_local_id', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);

    $session = openSessionFor($f['user'], $f['location']);

    $payload = [
        'cash_session_id' => $session->id,
        'client_local_id' => 'dup-key-1',
        'items' => [['article_id' => $f['articles'][0]->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 10]],
    ];

    $first = $this->postJson('/api/v1/pos/checkout', $payload)->assertCreated();
    $second = $this->postJson('/api/v1/pos/checkout', $payload)->assertOk();

    expect($first->json('data.id'))->toBe($second->json('data.id'))
        ->and($second->json('meta.idempotent_replay'))->toBeTrue()
        ->and(Receipt::count())->toBe(1)
        ->and(stockOf($f['articles'][0]->id, $f['gestiune']->id))->toBe('99.000'); // decremented once only
});

it('rolls back everything on insufficient stock / invalid payload', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);

    $session = openSessionFor($f['user'], $f['location']);

    // Payment total doesn't match item total, complete will throw.
    $this->postJson('/api/v1/pos/checkout', [
        'cash_session_id' => $session->id,
        'items' => [['article_id' => $f['articles'][0]->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 999]],
    ])->assertStatus(422);

    expect(Receipt::count())->toBe(0)
        ->and(stockOf($f['articles'][0]->id, $f['gestiune']->id))->toBe('100.000');
});

it('returns 403 when user lacks pos.sell', function () {
    $f = posFixture();
    $noSell = User::factory()->create(['tenant_id' => $f['tenant']->id]);
    $noSell->syncPermissions(['pos.void']);
    Sanctum::actingAs($noSell);

    $session = openSessionFor($f['user'], $f['location']);

    $this->postJson('/api/v1/pos/checkout', [
        'cash_session_id' => $session->id,
        'items' => [['article_id' => $f['articles'][0]->id, 'quantity' => 1]],
        'payments' => [['method' => 'cash', 'amount' => 10]],
    ])->assertStatus(403);
});
