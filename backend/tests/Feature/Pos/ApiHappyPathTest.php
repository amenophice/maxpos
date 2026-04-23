<?php

use App\Models\Barcode;
use App\Models\User;
use App\Services\ReceiptService;
use Laravel\Sanctum\Sanctum;

it('POST /auth/login returns a token for valid credentials', function () {
    posFixture();
    $user = User::factory()->create(['email' => 'cashier@demo.ro', 'password' => bcrypt('secret123')]);
    $user->syncRoles(['cashier']);

    $r = $this->postJson('/api/v1/auth/login', [
        'email' => 'cashier@demo.ro',
        'password' => 'secret123',
        'device_name' => 'test',
    ]);

    $r->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'roles', 'permissions']]]);
});

it('POST /cash-sessions/open then full receipt flow end-to-end', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);

    $openResp = $this->postJson('/api/v1/cash-sessions/open', [
        'location_id' => $f['location']->id,
        'initial_cash' => 100.00,
    ])->assertCreated();

    $sessionId = $openResp->json('data.id');

    $receiptResp = $this->postJson('/api/v1/receipts', ['cash_session_id' => $sessionId])
        ->assertCreated();
    $receiptId = $receiptResp->json('data.id');
    expect($receiptResp->json('data.number'))->toBe(1);

    $this->postJson("/api/v1/receipts/{$receiptId}/items", [
        'article_id' => $f['articles'][2]->id,
        'quantity' => 2,
    ])->assertCreated();

    $resp = $this->postJson("/api/v1/receipts/{$receiptId}/complete", [
        'payments' => [
            ['method' => 'cash', 'amount' => 100.00],
            ['method' => 'card', 'amount' => 100.00],
        ],
    ])->assertOk();

    expect($resp->json('data.status'))->toBe('completed')
        ->and($resp->json('data.payments'))->toHaveCount(2);
});

it('GET /articles/by-barcode/{code} returns the matching article', function () {
    $f = posFixture();
    $article = $f['articles'][0];
    Barcode::create([
        'tenant_id' => $f['tenant']->id,
        'article_id' => $article->id,
        'barcode' => '5949000000017',
        'type' => 'ean13',
    ]);

    Sanctum::actingAs($f['user']);

    $this->getJson('/api/v1/articles/by-barcode/5949000000017')
        ->assertOk()
        ->assertJsonPath('data.id', $article->id);
});

it('GET /articles/by-barcode returns 404 on unknown code', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);

    $this->getJson('/api/v1/articles/by-barcode/9999999999999')->assertNotFound();
});

it('POST /receipts/{id}/complete rejects mismatched payment total with 422', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);

    $session = openSessionFor($f['user'], $f['location']);
    $receipt = app(ReceiptService::class)->createDraftReceipt($session);
    app(ReceiptService::class)->addItem($receipt, $f['articles'][2], 1); // 100

    $this->postJson("/api/v1/receipts/{$receipt->id}/complete", [
        'payments' => [['method' => 'cash', 'amount' => 50]],
    ])->assertStatus(422);
});
