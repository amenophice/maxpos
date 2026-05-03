<?php

use App\Models\Tenant;
use Illuminate\Database\QueryException;

it('creates and persists a tenant with the required attributes', function () {
    $tenant = Tenant::create([
        'name' => 'Magazin Central',
        'cui' => 'RO12345678',
        'operating_mode' => 'shop',
    ]);

    expect($tenant->id)->toBeString()->not->toBeEmpty();

    $fresh = Tenant::find($tenant->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->name)->toBe('Magazin Central')
        ->and($fresh->cui)->toBe('RO12345678')
        ->and($fresh->operating_mode)->toBe('shop');
});

it('rejects an unknown operating_mode value at the database level', function () {
    Tenant::create([
        'name' => 'Invalid',
        'operating_mode' => 'warehouse',
    ]);
})->throws(QueryException::class);
