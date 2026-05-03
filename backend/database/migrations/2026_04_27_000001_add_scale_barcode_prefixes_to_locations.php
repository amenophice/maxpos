<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Two-digit EAN-13 prefixes that mark a scale-printed barcode.
            // JSON array of strings; null = use defaults ["26","27","28","29"].
            $table->json('scale_barcode_prefixes')->nullable()->after('saga_agent_token');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('scale_barcode_prefixes');
        });
    }
};
