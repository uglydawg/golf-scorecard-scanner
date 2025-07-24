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
        Schema::create('unverified_courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tee_name');
            $table->json('par_values'); // Array of 18 par values
            $table->json('handicap_values'); // Array of 18 handicap values
            $table->integer('slope')->nullable();
            $table->decimal('rating', 4, 1)->nullable();
            $table->string('location')->nullable();
            $table->integer('submission_count')->default(1);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            
            $table->unique(['name', 'tee_name']);
            $table->index(['status', 'submission_count']);
            $table->index('submission_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unverified_courses');
    }
};
