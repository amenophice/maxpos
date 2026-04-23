<?php

use App\Models\Article;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

afterEach(function () {
    tenancy()->end();
});

it('hides another tenant article from a user authenticated in tenant B via Filament', function () {
    $tenantA = Tenant::factory()->create(['name' => 'A Shop']);
    $tenantB = Tenant::factory()->create(['name' => 'B Shop']);

    tenancy()->initialize($tenantA);
    $articleA = Article::factory()->create([
        'tenant_id' => $tenantA->id,
        'sku' => 'ISOLATED-A',
        'name' => 'Doar pentru tenantul A',
    ]);
    tenancy()->end();

    $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
    $userB->assignRole('tenant-owner');

    $response = $this->actingAs($userB)->get('/admin/articles');

    $response->assertSuccessful();
    expect($response->getContent())->not->toContain('ISOLATED-A');
});

it('scopes Article queries to the active tenant at the Eloquent level', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);
    Article::factory()->create(['tenant_id' => $tenantA->id, 'sku' => 'ONLY-A']);
    tenancy()->end();

    tenancy()->initialize($tenantB);
    expect(Article::count())->toBe(0)
        ->and(Article::where('sku', 'ONLY-A')->exists())->toBeFalse();
    tenancy()->end();

    expect(Article::withoutTenancy()->where('sku', 'ONLY-A')->exists())->toBeTrue();
});
