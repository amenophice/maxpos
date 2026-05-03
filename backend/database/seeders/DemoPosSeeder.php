<?php

namespace Database\Seeders;

use App\Models\CashSession;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Database\Seeder;

class DemoPosSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('cui', 'RO12345678')->first();
        if (! $tenant) {
            return;
        }

        $user = User::where('email', 'owner@magazin-demo.ro')->first();
        if (! $user) {
            return;
        }
        if (! $user->hasPermissionTo('pos.open-session')) {
            $user->syncRoles(array_merge($user->roles->pluck('name')->all(), ['cashier']));
        }

        $location = Location::where('tenant_id', $tenant->id)->first();
        if (! $location) {
            return;
        }

        tenancy()->initialize($tenant);
        try {
            $already = $tenant->id ? CashSession::where('user_id', $user->id)
                ->where('location_id', $location->id)
                ->where('status', 'open')
                ->exists() : false;

            if (! $already) {
                app(ReceiptService::class)->openCashSession(
                    $location,
                    $user,
                    100.00,
                    'Sesiune demo creată automat de DemoPosSeeder.'
                );
            }
        } finally {
            tenancy()->end();
        }
    }
}
