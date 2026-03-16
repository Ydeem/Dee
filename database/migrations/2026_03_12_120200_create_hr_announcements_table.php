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
        Schema::create('hr_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('general');
            $table->string('audience')->default('all');
            $table->json('target_departments')->nullable();
            $table->json('target_employees')->nullable();
            $table->string('status')->default('draft');
            $table->string('priority')->default('normal');
            $table->boolean('send_email')->default(false);
            $table->boolean('send_notification')->default(true);
            $table->json('attachments')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_announcements');
    }
};
