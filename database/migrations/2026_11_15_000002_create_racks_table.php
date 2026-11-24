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
        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('created_by_id')->nullable();
            $table->foreignId('updated_by_id')->nullable();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);

            $table->unique(['warehouse_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('racks');
    }
};
