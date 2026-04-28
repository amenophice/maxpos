<?php

use App\Models\Location;
use Laravel\Sanctum\Sanctum;

it('persists scale_barcode_prefixes as JSON and exposes the casted array', function () {
    $f = posFixture();

    $location = Location::find($f['location']->id);
    $location->scale_barcode_prefixes = ['26', '27', '99'];
    $location->save();

    $reloaded = Location::find($location->id);
    expect($reloaded->scale_barcode_prefixes)->toBe(['26', '27', '99'])
        ->and($reloaded->effectiveScalePrefixes())->toBe(['26', '27', '99']);
});

it('falls back to defaults when scale_barcode_prefixes is null', function () {
    $f = posFixture();
    $location = Location::find($f['location']->id);
    expect($location->scale_barcode_prefixes)->toBeNull()
        ->and($location->effectiveScalePrefixes())->toBe(Location::DEFAULT_SCALE_PREFIXES);
});

it('returns the union of configured prefixes in /pos/bootstrap meta', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);

    Location::find($f['location']->id)->update([
        'scale_barcode_prefixes' => ['28', '29'],
    ]);

    $r = $this->getJson('/api/v1/pos/bootstrap')->assertOk();

    expect($r->json('meta.scale_barcode_prefixes'))->toBe(['28', '29']);
});

it('falls back to defaults in /pos/bootstrap when no location configures prefixes', function () {
    $f = posFixture();
    Sanctum::actingAs($f['user']);

    $r = $this->getJson('/api/v1/pos/bootstrap')->assertOk();

    expect($r->json('meta.scale_barcode_prefixes'))
        ->toBe(Location::DEFAULT_SCALE_PREFIXES);
});
