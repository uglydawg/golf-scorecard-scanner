<?php

declare(strict_types=1);

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
        Schema::create('scorecard_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_scan_id')->constrained('scorecard_scans')->onDelete('cascade');
            $table->string('player_name');
            $table->integer('total_score')->nullable();
            $table->integer('front_nine_score')->nullable();
            $table->integer('back_nine_score')->nullable();
            $table->json('hole_scores')->nullable(); // Array of 18 scores
            $table->timestamps();
            
            $table->unique(['scorecard_scan_id', 'player_name']);
            $table->index(['scorecard_scan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scorecard_players');
    }
};