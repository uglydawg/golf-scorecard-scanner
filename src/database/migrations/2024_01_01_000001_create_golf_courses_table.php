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
        Schema::create('golf_courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tee_name');
            $table->json('par_values'); // Array of 18 par values
            $table->json('handicap_values'); // Array of 18 handicap values
            $table->integer('slope')->nullable();
            $table->decimal('rating', 4, 1)->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->timestamps();

            $table->unique(['name', 'tee_name']);
            $table->index(['name', 'is_verified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('golf_courses');
    }
};
