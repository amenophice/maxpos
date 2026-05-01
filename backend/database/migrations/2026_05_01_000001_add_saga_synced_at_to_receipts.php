<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * saga_synced_at already exists on receipts (added in create_receipts_table migration).
 * This migration is a no-op kept for audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Column already present in 2026_04_25_000003_create_receipts_table
    }

    public function down(): void
    {
        //
    }
};
