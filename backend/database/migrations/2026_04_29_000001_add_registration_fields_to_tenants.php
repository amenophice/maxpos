<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['pending', 'trial', 'active', 'suspended', 'rejected'])
                ->default('pending')
                ->after('subscription_status');
            $table->timestamp('registered_at')->nullable()->after('trial_ends_at');
            $table->text('rejection_reason')->nullable()->after('registered_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['status', 'registered_at', 'rejection_reason']);
        });
    }
};
