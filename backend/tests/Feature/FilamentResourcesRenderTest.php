<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SuperAdminSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(SuperAdminSeeder::class);
});

afterEach(function () {
    tenancy()->end();
});

$slugs = [
    '/admin/locations',
    '/admin/gestiunes',
    '/admin/groups',
    '/admin/articles',
    '/admin/barcodes',
    '/admin/customers',
    '/admin/stock-levels',
];

foreach ($slugs as $slug) {
    it("renders Filament index at {$slug}", function () use ($slug) {
        $admin = User::where('email', 'admin@maxpos.ro')->firstOrFail();
        $this->actingAs($admin)->get($slug)->assertSuccessful();
    });
}
