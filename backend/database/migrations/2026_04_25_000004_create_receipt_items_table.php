<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->foreignUuid('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignUuid('gestiune_id')->constrained('gestiuni')->restrictOnDelete();
            $table->string('article_name_snapshot');
            $table->string('sku_snapshot');
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('line_subtotal', 12, 2);
            $table->decimal('line_vat', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->index('receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
    }
};
