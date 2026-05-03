<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('saga_cod', 16)->nullable()->after('name');
        });

        Schema::table('gestiuni', function (Blueprint $table) {
            $table->string('saga_cod', 4)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('saga_cod');
        });

        Schema::table('gestiuni', function (Blueprint $table) {
            $table->dropColumn('saga_cod');
        });
    }
};
