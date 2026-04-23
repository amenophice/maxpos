<?php

use App\Exceptions\PosException;
use App\Services\ReceiptService;

it('opens a cash session and rejects a second concurrent open session', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);

    $session = $service->openCashSession($f['location'], $f['user'], 500.00);

    expect($session->status)->toBe('open')
        ->and((string) $session->initial_cash)->toBe('500.00');

    expect(fn () => $service->openCashSession($f['location'], $f['user'], 100.00))
        ->toThrow(PosException::class);
});

it('closes a session and computes expected_cash from cash payments only', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = $service->openCashSession($f['location'], $f['user'], 200.00);

    $receipt = $service->createDraftReceipt($session);
    $service->addItem($receipt, $f['articles'][0], 1); // 10.00
    $service->addItem($receipt, $f['articles'][1], 2); // 11.00
    $receipt->refresh();

    $service->completeReceipt($receipt, [
        ['method' => 'cash', 'amount' => 15.00, 'reference' => null],
        ['method' => 'card', 'amount' => 6.00, 'reference' => null],
    ]);

    $closed = $service->closeCashSession($session, 215.00);

    // expected = 200 + 15 cash = 215.00; card portion ignored
    expect((string) $closed->expected_cash)->toBe('215.00')
        ->and($closed->status)->toBe('closed');
});

it('refuses to close a session that has draft receipts', function () {
    $f = posFixture();
    $service = app(ReceiptService::class);
    $session = $service->openCashSession($f['location'], $f['user'], 0);
    $service->createDraftReceipt($session);

    expect(fn () => $service->closeCashSession($session, 0))->toThrow(PosException::class);
});
