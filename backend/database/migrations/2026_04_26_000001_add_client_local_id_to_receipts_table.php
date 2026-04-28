<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('client_local_id', 64)->nullable()->after('number');
            $table->unique(['tenant_id', 'client_local_id']);
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'client_local_id']);
            $table->dropColumn('client_local_id');
        });
    }
};
