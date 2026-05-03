<?php

use App\Models\Receipt;
use Laravel\Sanctum\Sanctum;

it('marks a completed receipt as synced', function () {
    $fix = posFixture(['cashier']);
    Sanctum::actingAs($fix['user']);
    $session = openSessionFor($fix['user'], $fix['location']);

    $receipt = Receipt::create([
        'tenant_id' => $fix['tenant']->id,
        'location_id' => $fix['location']->id,
        'cash_session_id' => $session->id,
        'number' => 1,
        'status' => 'completed',
    ]);

    $response = $this->postJson("/api/v1/sync/receipts/{$receipt->id}/mark-synced");

    $response->assertOk()
        ->assertJsonStructure(['data' => ['saga_synced_at']]);

    $receipt->refresh();
    expect($receipt->saga_synced_at)->not->toBeNull();
});

it('returns 404 for draft receipt', function () {
    $fix = posFixture(['cashier']);
    Sanctum::actingAs($fix['user']);
    $session = openSessionFor($fix['user'], $fix['location']);

    $receipt = Receipt::create([
        'tenant_id' => $fix['tenant']->id,
        'location_id' => $fix['location']->id,
        'cash_session_id' => $session->id,
        'number' => 1,
        'status' => 'draft',
    ]);

    $response = $this->postJson("/api/v1/sync/receipts/{$receipt->id}/mark-synced");
    $response->assertNotFound();
});

it('returns 404 for already-synced receipt', function () {
    $fix = posFixture(['cashier']);
    Sanctum::actingAs($fix['user']);
    $session = openSessionFor($fix['user'], $fix['location']);

    $receipt = Receipt::create([
        'tenant_id' => $fix['tenant']->id,
        'location_id' => $fix['location']->id,
        'cash_session_id' => $session->id,
        'number' => 1,
        'status' => 'completed',
        'saga_synced_at' => now(),
    ]);

    $response = $this->postJson("/api/v1/sync/receipts/{$receipt->id}/mark-synced");
    $response->assertNotFound();
});
