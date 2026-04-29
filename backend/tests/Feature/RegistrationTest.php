<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('tenant-owner', 'web');
    Role::findOrCreate('super-admin', 'web');
});

it('registers a new tenant with status pending', function () {
    $r = $this->postJson('/api/v1/auth/register', [
        'company_name' => 'Test SRL',
        'cui' => '12345678',
        'email' => 'test@example.com',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ]);

    $r->assertCreated()
        ->assertJsonPath('data.message', 'Cererea ta a fost înregistrată. Vei fi contactat în maxim 24 de ore.');

    $tenant = Tenant::where('cui', 'RO12345678')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->status)->toBe('pending')
        ->and($tenant->name)->toBe('Test SRL')
        ->and($tenant->registered_at)->not->toBeNull();

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->tenant_id)->toBe($tenant->id)
        ->and($user->hasRole('tenant-owner'))->toBeTrue();
});

it('normalizes CUI with RO prefix', function () {
    $this->postJson('/api/v1/auth/register', [
        'company_name' => 'Firma RO',
        'cui' => 'RO87654321',
        'email' => 'ro@example.com',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ])->assertCreated();

    expect(Tenant::where('cui', 'RO87654321')->exists())->toBeTrue();
});

it('rejects duplicate CUI', function () {
    Tenant::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'name' => 'Existing',
        'cui' => 'RO11111111',
        'operating_mode' => 'shop',
        'status' => 'active',
    ]);

    $this->postJson('/api/v1/auth/register', [
        'company_name' => 'Duplicate',
        'cui' => '11111111',
        'email' => 'dup@example.com',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['cui']);
});

it('rejects duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/register', [
        'company_name' => 'Another',
        'cui' => '22222222',
        'email' => 'taken@example.com',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects invalid CUI format', function () {
    $this->postJson('/api/v1/auth/register', [
        'company_name' => 'Bad CUI',
        'cui' => 'INVALID',
        'email' => 'bad@example.com',
        'password' => 'Secret123',
        'password_confirmation' => 'Secret123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['cui']);
});

it('rejects password shorter than 8 characters', function () {
    $this->postJson('/api/v1/auth/register', [
        'company_name' => 'Short Pass',
        'cui' => '33333333',
        'email' => 'short@example.com',
        'password' => 'abc',
        'password_confirmation' => 'abc',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 403 on login with pending account', function () {
    $tenant = Tenant::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'name' => 'Pending Co',
        'cui' => 'RO44444444',
        'operating_mode' => 'shop',
        'status' => 'pending',
        'registered_at' => now(),
    ]);

    User::create([
        'name' => 'Pending User',
        'email' => 'pending@example.com',
        'password' => Hash::make('Secret123'),
        'tenant_id' => $tenant->id,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'pending@example.com',
        'password' => 'Secret123',
    ])->assertForbidden()
        ->assertJsonPath('data.message', 'Contul tău așteaptă aprobare. Vei fi contactat în 24h.');
});

it('returns 403 on login with rejected account', function () {
    $tenant = Tenant::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'name' => 'Rejected Co',
        'cui' => 'RO55555555',
        'operating_mode' => 'shop',
        'status' => 'rejected',
        'rejection_reason' => 'CUI invalid',
    ]);

    User::create([
        'name' => 'Rejected User',
        'email' => 'rejected@example.com',
        'password' => Hash::make('Secret123'),
        'tenant_id' => $tenant->id,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'rejected@example.com',
        'password' => 'Secret123',
    ])->assertForbidden()
        ->assertJsonPath('data.message', 'Cererea ta a fost respinsă: CUI invalid');
});

it('returns 403 on login with expired trial', function () {
    $tenant = Tenant::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'name' => 'Expired Co',
        'cui' => 'RO66666666',
        'operating_mode' => 'shop',
        'status' => 'trial',
        'trial_ends_at' => now()->subDay(),
    ]);

    User::create([
        'name' => 'Expired User',
        'email' => 'expired@example.com',
        'password' => Hash::make('Secret123'),
        'tenant_id' => $tenant->id,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'expired@example.com',
        'password' => 'Secret123',
    ])->assertForbidden()
        ->assertJsonPath('data.message', 'Perioada de trial a expirat. Contactează suportul pentru abonament.');
});

it('requires super-admin role for approve endpoint', function () {
    $tenant = Tenant::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'name' => 'To Approve',
        'cui' => 'RO77777777',
        'operating_mode' => 'shop',
        'status' => 'pending',
    ]);

    $owner = User::factory()->create(['tenant_id' => $tenant->id]);
    $owner->assignRole('tenant-owner');
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/admin/tenants/{$tenant->id}/approve")
        ->assertForbidden();
});

it('approves a tenant setting status to trial with 30-day expiry', function () {
    $tenant = Tenant::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'name' => 'Approve Me',
        'cui' => 'RO88888888',
        'operating_mode' => 'shop',
        'status' => 'pending',
    ]);

    $admin = User::factory()->create(['tenant_id' => null]);
    $admin->assignRole('super-admin');
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/tenants/{$tenant->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.message', 'Tenant aprobat.');

    $tenant->refresh();
    expect($tenant->status)->toBe('trial')
        ->and($tenant->trial_ends_at)->not->toBeNull()
        ->and($tenant->trial_ends_at->diffInDays(now()))->toBeBetween(29, 30);
});
