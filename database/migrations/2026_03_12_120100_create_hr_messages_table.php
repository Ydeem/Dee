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
        Schema::create('hr_messages', function (Blueprint $table) {
            $table->id();
            $table->string('thread_id')->index();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');
            $table->index(['recipient_type', 'recipient_id']);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('type')->default('internal');
            $table->string('status')->default('sent');
            $table->timestamp('read_at')->nullable();
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_messages');
    }
};
