<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_chatbot_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 120)->nullable();
            $table->string('role_label', 80)->nullable()->index();
            $table->json('roles')->nullable();
            $table->unsignedSmallInteger('permissions_count')->default(0);
            $table->text('message');
            $table->string('topic', 80)->nullable()->index();
            $table->boolean('blocked')->default(false)->index();
            $table->string('block_reason', 255)->nullable();
            $table->text('response_excerpt')->nullable();
            $table->unsignedTinyInteger('actions_count')->default(0);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'blocked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_chatbot_audits');
    }
};
