<?php

use App\Services\ReceiptService;
use Laravel\Sanctum\Sanctum;

it('returns paginated receipts list', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);
    $session = openSessionFor($f['user'], $f['location']);
    $service = app(ReceiptService::class);

    // Create 3 completed receipts
    foreach (range(1, 3) as $i) {
        $receipt = $service->createDraftReceipt($session);
        $service->addItem($receipt, $f['articles'][0], 1);
        $service->completeReceipt($receipt, [['method' => 'cash', 'amount' => 10.00]]);
    }

    $response = $this->getJson('/api/v1/pos/receipts?per_page=2');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonStructure([
            'data' => [['id', 'number', 'status', 'total', 'items', 'payments']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('filters receipts by date range', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);
    $session = openSessionFor($f['user'], $f['location']);
    $service = app(ReceiptService::class);

    // Create a completed receipt
    $receipt = $service->createDraftReceipt($session);
    $service->addItem($receipt, $f['articles'][0], 1);
    $service->completeReceipt($receipt, [['method' => 'cash', 'amount' => 10.00]]);

    $today = now()->format('Y-m-d');
    $yesterday = now()->subDay()->format('Y-m-d');
    $tomorrow = now()->addDay()->format('Y-m-d');

    // Should find the receipt with today's range
    $this->getJson("/api/v1/pos/receipts?date_from={$today}&date_to={$today}")
        ->assertOk()
        ->assertJsonPath('meta.total', 1);

    // Should not find any with yesterday-only range
    $this->getJson("/api/v1/pos/receipts?date_from={$yesterday}&date_to={$yesterday}")
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
});

it('filters receipts by search term (receipt number)', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);
    $session = openSessionFor($f['user'], $f['location']);
    $service = app(ReceiptService::class);

    // Create 2 completed receipts (numbers 1, 2)
    foreach (range(1, 2) as $i) {
        $receipt = $service->createDraftReceipt($session);
        $service->addItem($receipt, $f['articles'][0], 1);
        $service->completeReceipt($receipt, [['method' => 'cash', 'amount' => 10.00]]);
    }

    $this->getJson('/api/v1/pos/receipts?search=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.number', '1');
});

it('returns 401 for unauthenticated requests', function () {
    $this->getJson('/api/v1/pos/receipts')
        ->assertUnauthorized();
});

it('excludes draft receipts from the list', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);
    $session = openSessionFor($f['user'], $f['location']);
    $service = app(ReceiptService::class);

    // Create a draft (not completed)
    $service->createDraftReceipt($session);

    // Create a completed one
    $receipt = $service->createDraftReceipt($session);
    $service->addItem($receipt, $f['articles'][0], 1);
    $service->completeReceipt($receipt, [['method' => 'cash', 'amount' => 10.00]]);

    $this->getJson('/api/v1/pos/receipts')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});
