<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_number_counters', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('location_id');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->primary(['tenant_id', 'location_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_number_counters');
    }
};
