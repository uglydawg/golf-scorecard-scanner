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
        Schema::create('player_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scorecard_player_id')->constrained('scorecard_players')->onDelete('cascade');
            $table->integer('hole_number');
            $table->integer('score');
            $table->integer('par');
            $table->integer('handicap');
            $table->timestamps();
            
            $table->unique(['scorecard_player_id', 'hole_number']);
            $table->index(['scorecard_player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_scores');
    }
};