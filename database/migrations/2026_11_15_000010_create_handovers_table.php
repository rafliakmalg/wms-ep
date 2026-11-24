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
        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('created_by_id')->nullable();
            $table->foreignId('custody_id');
            $table->foreignId('updated_by_id')->nullable();
            $table->string('code')->unique();
            $table->text('note')->nullable();
            $table->string('status')->default('taken');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('handovers');
    }
};
