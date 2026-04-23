<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Barcode;
use App\Models\Customer;
use App\Models\Gestiune;
use App\Models\Group;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoShopSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['cui' => 'RO12345678'],
            [
                'name' => 'Magazin Demo',
                'operating_mode' => 'shop',
            ]
        );

        // Demo tenant owner, useful for manual login on http://backend.test/admin
        User::firstOrCreate(
            ['email' => 'owner@magazin-demo.ro'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Magazin Demo Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        )->syncRoles(['tenant-owner']);

        tenancy()->initialize($tenant);

        try {
            $location = Location::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Magazin Central Oradea'],
                [
                    'address' => 'Str. Republicii nr. 12',
                    'city' => 'Oradea',
                    'county' => 'Bihor',
                    'is_active' => true,
                ]
            );

            $gestiuni = collect([
                ['name' => 'Raion principal', 'type' => 'cantitativ-valoric'],
                ['name' => 'Depozit', 'type' => 'cantitativ-valoric'],
                ['name' => 'Casă', 'type' => 'global-valoric'],
            ])->map(fn ($g) => Gestiune::firstOrCreate(
                ['tenant_id' => $tenant->id, 'location_id' => $location->id, 'name' => $g['name']],
                ['type' => $g['type'], 'is_active' => true]
            ));

            $groupSpecs = [
                ['name' => 'Lactate', 'vat' => 9.00],
                ['name' => 'Panificație', 'vat' => 9.00],
                ['name' => 'Băuturi', 'vat' => 19.00],
                ['name' => 'Legume-Fructe', 'vat' => 9.00],
                ['name' => 'Diverse', 'vat' => 19.00],
            ];
            $groups = collect($groupSpecs)->mapWithKeys(function ($g, $idx) use ($tenant) {
                $group = Group::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $g['name']],
                    ['display_order' => ($idx + 1) * 10]
                );

                return [$g['name'] => ['group' => $group, 'vat' => $g['vat']]];
            });

            $articleSpecs = [
                ['Lactate', 'Lapte Zuzu 1L', 7.50, 'LAC-001'],
                ['Lactate', 'Iaurt Danone 150g', 3.20, 'LAC-002'],
                ['Lactate', 'Unt Meggle 200g', 12.90, 'LAC-003'],
                ['Lactate', 'Brânză telemea 250g', 11.40, 'LAC-004'],
                ['Lactate', 'Smântână 20% 400g', 8.30, 'LAC-005'],
                ['Lactate', 'Cașcaval afumat 300g', 18.90, 'LAC-006'],
                ['Panificație', 'Pâine albă 500g', 4.20, 'PAN-001'],
                ['Panificație', 'Pâine integrală 400g', 6.80, 'PAN-002'],
                ['Panificație', 'Chiflă simplă', 1.50, 'PAN-003'],
                ['Panificație', 'Covrig simplu', 2.20, 'PAN-004'],
                ['Panificație', 'Baghetă franțuzească', 5.90, 'PAN-005'],
                ['Băuturi', 'Coca-Cola 2L', 11.50, 'BAU-001'],
                ['Băuturi', 'Apă Dorna 2L', 4.50, 'BAU-002'],
                ['Băuturi', 'Bere Ursus 0.5L', 5.90, 'BAU-003'],
                ['Băuturi', 'Vin Cabernet 0.75L', 32.00, 'BAU-004'],
                ['Băuturi', 'Suc portocale Tedi 1L', 9.90, 'BAU-005'],
                ['Băuturi', 'Cafea Jacobs 250g', 28.50, 'BAU-006'],
                ['Legume-Fructe', 'Mere roșii 1kg', 6.90, 'LF-001'],
                ['Legume-Fructe', 'Banane 1kg', 7.80, 'LF-002'],
                ['Legume-Fructe', 'Roșii 1kg', 9.50, 'LF-003'],
                ['Legume-Fructe', 'Cartofi 1kg', 3.20, 'LF-004'],
                ['Legume-Fructe', 'Ceapă roșie 1kg', 4.50, 'LF-005'],
                ['Legume-Fructe', 'Salată verde buc', 5.00, 'LF-006'],
                ['Diverse', 'Hârtie igienică 10 role', 24.90, 'DIV-001'],
                ['Diverse', 'Detergent Ariel 2L', 38.50, 'DIV-002'],
                ['Diverse', 'Săpun lichid 500ml', 14.90, 'DIV-003'],
                ['Diverse', 'Pungă reutilizabilă', 1.00, 'DIV-004'],
                ['Diverse', 'Baterii AA 4buc', 16.50, 'DIV-005'],
                ['Diverse', 'Șervețele umede 72buc', 11.90, 'DIV-006'],
                ['Diverse', 'Cartelă reîncărcare Orange', 20.00, 'DIV-007'],
            ];

            $defaultGestiune = $gestiuni->firstWhere('name', 'Raion principal');

            foreach ($articleSpecs as $spec) {
                [$groupName, $name, $price, $sku] = $spec;
                $meta = $groups[$groupName];

                $article = Article::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'sku' => $sku],
                    [
                        'name' => $name,
                        'description' => null,
                        'group_id' => $meta['group']->id,
                        'default_gestiune_id' => $defaultGestiune->id,
                        'vat_rate' => $meta['vat'],
                        'price' => $price,
                        'unit' => 'buc',
                        'is_active' => true,
                    ]
                );

                // 1-2 barcodes per article
                $numBarcodes = random_int(1, 2);
                for ($i = 0; $i < $numBarcodes; $i++) {
                    Barcode::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'barcode' => self::ean13(sprintf('594%09d', crc32($sku.$i) % 1_000_000_000))],
                        ['article_id' => $article->id, 'type' => 'ean13']
                    );
                }

                foreach ($gestiuni as $g) {
                    StockLevel::firstOrCreate(
                        ['article_id' => $article->id, 'gestiune_id' => $g->id],
                        ['tenant_id' => $tenant->id, 'quantity' => random_int(5, 200)]
                    );
                }
            }

            // Customers: 6 persons + 4 companies
            for ($i = 0; $i < 6; $i++) {
                Customer::factory()->create([
                    'tenant_id' => $tenant->id,
                    'county' => 'Bihor',
                    'city' => 'Oradea',
                ]);
            }
            for ($i = 0; $i < 4; $i++) {
                Customer::factory()->company()->create([
                    'tenant_id' => $tenant->id,
                    'county' => 'Bihor',
                    'city' => 'Oradea',
                ]);
            }
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Build a valid EAN-13 from a 12-digit prefix by computing and appending the check digit.
     */
    public static function ean13(string $twelveDigits): string
    {
        $twelveDigits = substr(str_pad($twelveDigits, 12, '0', STR_PAD_LEFT), 0, 12);
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $twelveDigits[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        $check = (10 - ($sum % 10)) % 10;

        return $twelveDigits.$check;
    }
}
