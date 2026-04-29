<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $digits = preg_replace('/^RO/i', '', $validated['cui']);
        $normalizedCui = 'RO'.$digits;

        $tenant = Tenant::create([
            'id' => Str::uuid()->toString(),
            'name' => $validated['company_name'],
            'cui' => $normalizedCui,
            'status' => 'pending',
            'registered_at' => now(),
            'operating_mode' => 'shop',
        ]);

        $user = User::create([
            'name' => $validated['company_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'tenant_id' => $tenant->id,
        ]);

        $user->assignRole('tenant-owner');

        Log::info("New registration: {$validated['company_name']} ({$normalizedCui}) - {$validated['email']}");

        return response()->json([
            'data' => ['message' => 'Cererea ta a fost înregistrată. Vei fi contactat în maxim 24 de ore.'],
            'meta' => [],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Datele de autentificare sunt incorecte.'],
            ]);
        }

        if ($user->tenant_id) {
            $tenant = $user->tenant;

            if ($tenant) {
                $status = $tenant->status;

                if ($status === 'pending') {
                    return response()->json([
                        'data' => ['message' => 'Contul tău așteaptă aprobare. Vei fi contactat în 24h.'],
                        'meta' => [],
                    ], 403);
                }

                if ($status === 'rejected') {
                    return response()->json([
                        'data' => ['message' => 'Cererea ta a fost respinsă: '.($tenant->rejection_reason ?? '')],
                        'meta' => [],
                    ], 403);
                }

                if ($status === 'suspended') {
                    return response()->json([
                        'data' => ['message' => 'Contul tău a fost suspendat. Contactează suportul.'],
                        'meta' => [],
                    ], 403);
                }

                if ($status === 'trial' && $tenant->trial_ends_at && $tenant->trial_ends_at->isPast()) {
                    return response()->json([
                        'data' => ['message' => 'Perioada de trial a expirat. Contactează suportul pentru abonament.'],
                        'meta' => [],
                    ], 403);
                }
            }
        }

        $device = $credentials['device_name'] ?? 'pos';
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
            ],
            'meta' => [],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Parola curentă este incorectă.'],
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json([
            'data' => ['message' => 'Parola a fost schimbată cu succes.'],
            'meta' => [],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['message' => 'Delogat cu succes.'], 'meta' => []]);
    }
}
