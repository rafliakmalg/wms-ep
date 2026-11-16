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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('rack_id')->constrained()->restrictOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('quantity_incoming', 15, 3)->default(0);

            $table->unique(['product_id', 'rack_id']);
        });

        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_quantity_check CHECK (quantity >= 0 AND quantity_incoming >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
