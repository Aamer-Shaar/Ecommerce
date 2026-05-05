<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_product_sales', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date')->index();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_quantity_sold')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['sale_date', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_product_sales');
    }
};
