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
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('scorecard_scan_id')->nullable()->constrained()->onDelete('set null');
            $table->date('played_at');
            $table->integer('total_score');
            $table->integer('front_nine_score');
            $table->integer('back_nine_score');
            $table->string('weather')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'played_at']);
            $table->index(['course_id', 'played_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
