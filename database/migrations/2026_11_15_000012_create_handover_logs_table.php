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
        Schema::create('handover_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('custody_from_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('custody_to_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('handover_id')->constrained()->cascadeOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->text('note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handover_logs');
    }
};
