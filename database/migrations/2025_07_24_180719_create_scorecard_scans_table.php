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
        Schema::create('scorecard_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('original_image_path');
            $table->string('processed_image_path')->nullable();
            $table->json('raw_ocr_data')->nullable();
            $table->json('parsed_data')->nullable();
            $table->json('confidence_scores')->nullable(); // Field-level confidence scores
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->string('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorecard_scans');
    }
};
