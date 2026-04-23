<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SuperAdminSeeder;

it('redirects unauthenticated visitors from /admin to the login page', function () {
    $response = $this->get('/admin');

    $response->assertRedirect('/admin/login');
});

it('lets a seeded super admin authenticate and reach the admin panel', function () {
    $this->seed(RolesSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    $admin = User::where('email', 'admin@maxpos.ro')->firstOrFail();

    expect($admin->hasRole('super-admin'))->toBeTrue();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful();
});
