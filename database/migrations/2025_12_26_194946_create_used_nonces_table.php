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
        Schema::create('used_nonces', function (Blueprint $table) {
            $table->string('nonce', 255)->primary();
            $table->timestamp('used_at')->useCurrent();
            $table->timestamp('expires_at');
            
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('used_nonces');
    }
};
