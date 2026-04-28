<?php

/*
 | Tenant isolation
 | ----------------
 | Tenant-scoped models use stancl/tenancy's `BelongsToTenant` trait, which
 | (a) auto-fills `tenant_id` on create when tenancy is initialized, and
 | (b) adds a global `TenantScope` that restricts queries to the active tenant.
 |
 | Tenancy is "initialized" via `tenancy()->initialize($tenant)`. In this app
 | that happens in two places:
 |   - Filament admin: the `InitializeTenancyForAuthenticatedUser` middleware
 |     (app/Http/Middleware) bootstraps tenancy from the logged-in user's
 |     `tenant_id`. Users with no `tenant_id` (super admin) see every tenant.
 |   - Tests: call `tenancy()->initialize($tenant)` explicitly.
 |
 | When tenancy is NOT initialized, no scope is applied — this matches the
 | single-database model where the central context (seeders, CLI) can reach
 | every tenant's rows.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Location extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public const DEFAULT_SCALE_PREFIXES = ['26', '27', '28', '29'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'scale_barcode_prefixes' => 'array',
        ];
    }

    /** Effective list of allowed scale-barcode prefixes (falls back to defaults). */
    public function effectiveScalePrefixes(): array
    {
        return $this->scale_barcode_prefixes ?: self::DEFAULT_SCALE_PREFIXES;
    }

    public function gestiuni(): HasMany
    {
        return $this->hasMany(Gestiune::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
