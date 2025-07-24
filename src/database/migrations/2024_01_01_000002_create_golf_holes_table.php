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
        Schema::create('golf_holes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('golf_course_id')->constrained('golf_courses')->onDelete('cascade');
            $table->integer('hole_number');
            $table->integer('par');
            $table->integer('handicap');
            $table->integer('distance_yards')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['golf_course_id', 'hole_number']);
            $table->index(['golf_course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('golf_holes');
    }
};
