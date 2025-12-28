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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_id');
            $table->foreign('job_id')->references('id')->on('classification_jobs')->onDelete('cascade');
            $table->string('issue_key', 20)->unique();
            $table->string('summary', 200);
            $table->text('description');
            $table->string('reporter', 255);
            $table->string('category', 50)->nullable();
            $table->string('sentiment', 20)->nullable();
            $table->string('priority', 20)->nullable();
            $table->string('impact', 20)->nullable();
            $table->string('urgency', 20)->nullable();
            $table->timestamp('sla_due_date')->nullable();
            $table->text('reasoning')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            
            $table->index('job_id');
            $table->index('category');
            $table->index('priority');
            $table->index(['category', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
