<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interviews')) {
            return;
        }

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('interviewer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('type')->nullable();
            $table->string('location_or_link')->nullable();
            $table->text('notes')->nullable();
            $table->string('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};

