<?php

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReceiptService;
use Database\Seeders\RolesSeeder;
use Laravel\Sanctum\Sanctum;

it('hides a receipt from tenant A when authenticated as a user from tenant B', function () {
    $this->seed(RolesSeeder::class);

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $locA = Location::factory()->create(['tenant_id' => $tenantA->id]);

    $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
    $userA->syncRoles(['cashier']);

    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $userB->syncRoles(['cashier']);

    tenancy()->initialize($tenantA);
    $service = app(ReceiptService::class);
    $session = $service->openCashSession($locA, $userA, 0);
    $receiptA = $service->createDraftReceipt($session);
    tenancy()->end();

    Sanctum::actingAs($userB);

    $this->getJson("/api/v1/receipts/{$receiptA->id}")->assertNotFound();
});
