<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('handover_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('handover_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('rack_from_id')->constrained('racks')->restrictOnDelete();
            $table->foreignId('rack_to_id')->constrained('racks')->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);

            $table->unique(['handover_id', 'product_id', 'rack_from_id', 'rack_to_id'], 'handover_items_unique');
        });

        DB::statement('ALTER TABLE handover_items ADD CONSTRAINT handover_items_check CHECK (quantity > 0 AND rack_from_id <> rack_to_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handover_items');
    }
};
