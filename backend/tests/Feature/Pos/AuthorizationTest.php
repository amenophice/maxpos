<?php

use App\Models\User;
use App\Services\ReceiptService;
use Database\Seeders\RolesSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

it('returns 403 on void if user lacks pos.void', function () {
    $f = posFixture();
    $noVoid = User::factory()->create(['tenant_id' => $f['tenant']->id]);
    // 'tenant-owner' and 'cashier' both hold pos.void in seeder — give a bare role.
    $noVoid->syncPermissions(['pos.sell']);

    Sanctum::actingAs($noVoid);

    $session = openSessionFor($f['user'], $f['location']);
    $receipt = app(ReceiptService::class)->createDraftReceipt($session);

    $this->postJson("/api/v1/receipts/{$receipt->id}/void", ['reason' => 'Test'])
        ->assertStatus(403);
});

it('returns 403 on adding items if user lacks pos.sell', function () {
    $f = posFixture();
    $noSell = User::factory()->create(['tenant_id' => $f['tenant']->id]);
    $noSell->syncPermissions(['pos.void']);

    Sanctum::actingAs($noSell);

    $session = openSessionFor($f['user'], $f['location']);
    $receipt = app(ReceiptService::class)->createDraftReceipt($session);

    $this->postJson("/api/v1/receipts/{$receipt->id}/items", [
        'article_id' => $f['articles'][0]->id,
        'quantity' => 1,
    ])->assertStatus(403);
});
