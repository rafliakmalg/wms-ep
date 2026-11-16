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
        Schema::create('adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('rack_id')->constrained()->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->string('reason');

            $table->unique(['adjustment_id', 'product_id', 'rack_id']);
        });

        DB::statement('ALTER TABLE adjustment_items ADD CONSTRAINT adjustment_items_quantity_check CHECK (quantity <> 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjustment_items');
    }
};
