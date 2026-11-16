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
        Schema::create('movements', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('adjustment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('handover_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('rack_from_id')->nullable()->constrained('racks')->restrictOnDelete();
            $table->foreignId('rack_to_id')->nullable()->constrained('racks')->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->decimal('quantity', 15, 3);

            $table->index(['product_id', 'created_at']);
        });

        DB::statement('ALTER TABLE movements ADD CONSTRAINT movements_check CHECK (quantity > 0 AND (rack_from_id IS NOT NULL OR rack_to_id IS NOT NULL) AND (rack_from_id IS NULL OR rack_to_id IS NULL OR rack_from_id <> rack_to_id) AND (adjustment_id IS NULL OR handover_id IS NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movements');
    }
};
