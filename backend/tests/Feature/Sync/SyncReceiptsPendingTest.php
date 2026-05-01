<?php

use App\Models\Receipt;
use Laravel\Sanctum\Sanctum;

it('returns only completed receipts that are not yet synced', function () {
    $fix = posFixture(['cashier']);
    Sanctum::actingAs($fix['user']);
    $session = openSessionFor($fix['user'], $fix['location']);

    // Create a completed, un-synced receipt
    $receipt = Receipt::create([
        'tenant_id' => $fix['tenant']->id,
        'location_id' => $fix['location']->id,
        'cash_session_id' => $session->id,
        'number' => 1,
        'status' => 'completed',
        'saga_synced_at' => null,
    ]);

    // Create a draft receipt — should not appear
    Receipt::create([
        'tenant_id' => $fix['tenant']->id,
        'location_id' => $fix['location']->id,
        'cash_session_id' => $session->id,
        'number' => 2,
        'status' => 'draft',
    ]);

    // Create a completed, already-synced receipt — should not appear
    Receipt::create([
        'tenant_id' => $fix['tenant']->id,
        'location_id' => $fix['location']->id,
        'cash_session_id' => $session->id,
        'number' => 3,
        'status' => 'completed',
        'saga_synced_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/sync/receipts/pending');

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['id'])->toBe($receipt->id);
});

it('returns empty array when all receipts are synced', function () {
    $fix = posFixture(['cashier']);
    Sanctum::actingAs($fix['user']);

    $response = $this->getJson('/api/v1/sync/receipts/pending');

    $response->assertOk()
        ->assertJsonPath('data', []);
});
