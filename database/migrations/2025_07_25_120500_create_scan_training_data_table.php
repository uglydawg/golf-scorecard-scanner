<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_training_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_scan_id')->constrained('scorecard_scans')->onDelete('cascade');
            $table->string('original_image_path');
            $table->string('processed_image_path')->nullable();

            // Raw OCR results
            $table->json('raw_ocr_response'); // Complete OCR provider response
            $table->json('extracted_data'); // Our parsed/structured data
            $table->decimal('confidence_score', 5, 4)->nullable();

            // Ground truth data for training
            $table->json('verified_data')->nullable(); // Human-verified correct data
            $table->json('corrections')->nullable(); // Specific field corrections
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_training_candidate')->default(true);

            // Analysis metadata
            $table->string('ocr_provider', 50);
            $table->boolean('used_enhanced_prompt')->default(false);
            $table->json('processing_metadata')->nullable(); // Image preprocessing details
            $table->json('error_analysis')->nullable(); // Field-level accuracy analysis

            // Quality metrics
            $table->integer('data_completeness_score')->nullable(); // 0-100
            $table->json('field_confidence_scores')->nullable(); // Per-field confidence
            $table->json('validation_errors')->nullable(); // Golf-specific validation issues

            // Performance tracking
            $table->integer('processing_time_ms')->nullable();
            $table->string('model_version', 50)->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['is_training_candidate', 'is_verified']);
            $table->index(['ocr_provider', 'used_enhanced_prompt']);
            $table->index(['confidence_score', 'data_completeness_score']);
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_training_data');
    }
};
