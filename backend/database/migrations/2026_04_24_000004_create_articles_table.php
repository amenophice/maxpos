<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignUuid('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->foreignUuid('default_gestiune_id')->nullable()->constrained('gestiuni')->nullOnDelete();
            $table->decimal('vat_rate', 5, 2)->default(19.00);
            $table->decimal('price', 10, 2);
            $table->string('unit')->default('buc');
            $table->string('plu')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'group_id']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
