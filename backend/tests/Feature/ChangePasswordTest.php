<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

it('changes password with correct current password', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPass123')]);
    Sanctum::actingAs($user);

    $r = $this->putJson('/api/v1/auth/password', [
        'current_password' => 'OldPass123',
        'password' => 'NewPass456',
        'password_confirmation' => 'NewPass456',
    ]);

    $r->assertOk()
        ->assertJsonPath('data.message', 'Parola a fost schimbată cu succes.');

    $user->refresh();
    expect(Hash::check('NewPass456', $user->password))->toBeTrue();
});

it('rejects wrong current password', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPass123')]);
    Sanctum::actingAs($user);

    $r = $this->putJson('/api/v1/auth/password', [
        'current_password' => 'WrongPassword',
        'password' => 'NewPass456',
        'password_confirmation' => 'NewPass456',
    ]);

    $r->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);
});

it('rejects password shorter than 8 characters', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPass123')]);
    Sanctum::actingAs($user);

    $r = $this->putJson('/api/v1/auth/password', [
        'current_password' => 'OldPass123',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $r->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('rejects password confirmation mismatch', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPass123')]);
    Sanctum::actingAs($user);

    $r = $this->putJson('/api/v1/auth/password', [
        'current_password' => 'OldPass123',
        'password' => 'NewPass456',
        'password_confirmation' => 'Different789',
    ]);

    $r->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('returns 401 for unauthenticated request', function () {
    $this->putJson('/api/v1/auth/password', [
        'current_password' => 'OldPass123',
        'password' => 'NewPass456',
        'password_confirmation' => 'NewPass456',
    ])->assertUnauthorized();
});

it('revokes other tokens after password change', function () {
    $user = User::factory()->create(['password' => Hash::make('OldPass123')]);

    $user->createToken('device-a');
    $currentToken = $user->createToken('device-b')->plainTextToken;

    expect($user->tokens()->count())->toBe(2);

    $this->withHeader('Authorization', "Bearer {$currentToken}")
        ->putJson('/api/v1/auth/password', [
            'current_password' => 'OldPass123',
            'password' => 'NewPass456',
            'password_confirmation' => 'NewPass456',
        ])->assertOk();

    expect($user->tokens()->count())->toBe(1);
});
