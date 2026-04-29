<?php

use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CashSessionController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Middleware\InitializeTenancyForAuthenticatedUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $r) => ['data' => $r->user()])->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', InitializeTenancyForAuthenticatedUser::class])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::put('/auth/password', [AuthController::class, 'changePassword']);
        Route::get('/me', MeController::class);

        Route::get('/articles', [ArticleController::class, 'index']);
        Route::get('/articles/by-barcode/{barcode}', [ArticleController::class, 'byBarcode']);
        Route::get('/customers', [CustomerController::class, 'index']);

        Route::get('/pos/bootstrap', [PosController::class, 'bootstrap']);
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->middleware('can:pos.sell');

        Route::post('/cash-sessions/open', [CashSessionController::class, 'open'])
            ->middleware('can:pos.open-session');
        Route::post('/cash-sessions/{id}/close', [CashSessionController::class, 'close'])
            ->middleware('can:pos.close-session');
        Route::get('/cash-sessions/current', [CashSessionController::class, 'current']);

        Route::post('/receipts', [ReceiptController::class, 'store'])->middleware('can:pos.sell');
        Route::get('/receipts/{id}', [ReceiptController::class, 'show']);
        Route::post('/receipts/{id}/items', [ReceiptController::class, 'addItem'])->middleware('can:pos.sell');
        Route::patch('/receipts/{id}/items/{itemId}', [ReceiptController::class, 'updateItem'])->middleware('can:pos.sell');
        Route::delete('/receipts/{id}/items/{itemId}', [ReceiptController::class, 'removeItem'])->middleware('can:pos.sell');
        Route::post('/receipts/{id}/discount', [ReceiptController::class, 'applyDiscount'])->middleware('can:pos.discount');
        Route::post('/receipts/{id}/complete', [ReceiptController::class, 'complete'])->middleware('can:pos.sell');
        Route::post('/receipts/{id}/void', [ReceiptController::class, 'void'])->middleware('can:pos.void');
    });
});
