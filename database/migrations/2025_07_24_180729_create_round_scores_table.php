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
        Schema::create('round_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->onDelete('cascade');
            $table->string('player_name');
            $table->integer('hole_number');
            $table->integer('score');
            $table->integer('par');
            $table->integer('handicap');
            $table->timestamps();
            
            $table->unique(['round_id', 'player_name', 'hole_number']);
            $table->index(['round_id', 'player_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_scores');
    }
};
