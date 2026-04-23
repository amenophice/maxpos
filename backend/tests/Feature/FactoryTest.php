<?php

use App\Models\Article;
use App\Models\Barcode;
use App\Models\Customer;
use App\Models\Gestiune;
use App\Models\Group;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Tenant;

it('creates a Tenant via factory', function () {
    expect(Tenant::factory()->create()->exists)->toBeTrue();
});

it('creates a Location via factory', function () {
    expect(Location::factory()->create()->exists)->toBeTrue();
});

it('creates a Gestiune via factory', function () {
    expect(Gestiune::factory()->create()->exists)->toBeTrue();
});

it('creates a Group via factory', function () {
    expect(Group::factory()->create()->exists)->toBeTrue();
});

it('creates an Article via factory', function () {
    expect(Article::factory()->create()->exists)->toBeTrue();
});

it('creates a Barcode via factory', function () {
    expect(Barcode::factory()->create()->exists)->toBeTrue();
});

it('creates a Customer via factory', function () {
    expect(Customer::factory()->create()->exists)->toBeTrue();
});

it('creates a StockLevel via factory', function () {
    expect(StockLevel::factory()->create()->exists)->toBeTrue();
});
