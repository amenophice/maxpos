<?php

use App\Exceptions\PosException;
use App\Services\ReceiptService;

it('computes receipt totals with mixed VAT rates', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = openSessionFor($f['user'], $f['location']);
    $receipt = $service->createDraftReceipt($session);

    // Article 0: 10.00 @ 19% x 2 = 20.00 (includes VAT of 20 * 19/119 ≈ 3.19)
    // Article 1: 5.50 @ 9% x 3 = 16.50 (includes VAT of 16.50 * 9/109 ≈ 1.36)
    // Article 2: 100.00 @ 19% x 1 = 100.00 (VAT ≈ 15.97)
    $service->addItem($receipt, $f['articles'][0], 2);
    $service->addItem($receipt, $f['articles'][1], 3);
    $service->addItem($receipt, $f['articles'][2], 1);

    $receipt->refresh();
    expect((string) $receipt->subtotal)->toBe('136.50')
        ->and((string) $receipt->total)->toBe('136.50')
        ->and((string) $receipt->vat_total)->toBe('20.52'); // 3.19 + 1.36 + 15.97
});

it('splits payment between cash and card and completes', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = openSessionFor($f['user'], $f['location']);
    $receipt = $service->createDraftReceipt($session);
    $service->addItem($receipt, $f['articles'][2], 1); // 100.00

    $receipt->refresh();
    $completed = $service->completeReceipt($receipt, [
        ['method' => 'cash', 'amount' => 50.00],
        ['method' => 'card', 'amount' => 50.00],
    ]);

    expect($completed->status)->toBe('completed')
        ->and($completed->payments()->count())->toBe(2);
});

it('rejects completion when payments do not match the total', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = openSessionFor($f['user'], $f['location']);
    $receipt = $service->createDraftReceipt($session);
    $service->addItem($receipt, $f['articles'][2], 1); // 100.00
    $receipt->refresh();

    expect(fn () => $service->completeReceipt($receipt, [
        ['method' => 'cash', 'amount' => 50.00],
    ]))->toThrow(PosException::class);
});

it('decrements stock on completion and reverses it on void', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = openSessionFor($f['user'], $f['location']);
    $receipt = $service->createDraftReceipt($session);
    $service->addItem($receipt, $f['articles'][0], 5);
    $receipt->refresh();

    expect(stockOf($f['articles'][0]->id, $f['gestiune']->id))->toBe('100.000');

    $service->completeReceipt($receipt, [['method' => 'cash', 'amount' => (float) $receipt->total]]);
    expect(stockOf($f['articles'][0]->id, $f['gestiune']->id))->toBe('95.000');

    $service->voidReceipt($receipt, 'Cerere client');
    expect(stockOf($f['articles'][0]->id, $f['gestiune']->id))->toBe('100.000');
});

it('voids a draft receipt without touching stock', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = openSessionFor($f['user'], $f['location']);
    $receipt = $service->createDraftReceipt($session);
    $service->addItem($receipt, $f['articles'][0], 3);

    expect(stockOf($f['articles'][0]->id, $f['gestiune']->id))->toBe('100.000');
    $service->voidReceipt($receipt, 'Eroare operator');
    expect(stockOf($f['articles'][0]->id, $f['gestiune']->id))->toBe('100.000');
});

it('refuses to void a fiscal-printed receipt', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = openSessionFor($f['user'], $f['location']);
    $receipt = $service->createDraftReceipt($session);
    $service->addItem($receipt, $f['articles'][0], 1);
    $receipt->refresh();
    $service->completeReceipt($receipt, [['method' => 'cash', 'amount' => (float) $receipt->total]]);

    $receipt->fiscal_printed_at = now();
    $receipt->save();

    expect(fn () => $service->voidReceipt($receipt, 'X'))->toThrow(PosException::class);
});

it('refuses to add items to a completed receipt', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = openSessionFor($f['user'], $f['location']);
    $receipt = $service->createDraftReceipt($session);
    $service->addItem($receipt, $f['articles'][0], 1);
    $receipt->refresh();
    $service->completeReceipt($receipt, [['method' => 'cash', 'amount' => (float) $receipt->total]]);

    expect(fn () => $service->addItem($receipt, $f['articles'][1], 1))->toThrow(PosException::class);
});

it('handles a negative quantity item — returns increment stock on complete', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = openSessionFor($f['user'], $f['location']);
    $receipt = $service->createDraftReceipt($session);

    // Return of 2 units of article[0] @ 10.00 = -20.00
    $service->addItem($receipt, $f['articles'][0], -2);
    $receipt->refresh();

    expect((string) $receipt->total)->toBe('-20.00');

    expect(stockOf($f['articles'][0]->id, $f['gestiune']->id))->toBe('100.000');
    $service->completeReceipt($receipt, [['method' => 'cash', 'amount' => -20.00]]);

    // Stock goes UP by 2 since quantity was -2 and we decrement -(-2) = +2
    expect(stockOf($f['articles'][0]->id, $f['gestiune']->id))->toBe('102.000');
});

it('generates gapless per-location receipt numbers under sequential load', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = openSessionFor($f['user'], $f['location']);

    $numbers = [];
    for ($i = 0; $i < 10; $i++) {
        $numbers[] = $service->createDraftReceipt($session)->number;
    }

    expect($numbers)->toBe([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
});
