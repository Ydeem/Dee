<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->unique();

            // Account preferences
            $table->string('language')->default('en');
            $table->string('timezone')->default('Africa/Accra');
            $table->string('date_format')->default('M d, Y');
            $table->string('currency')->default('GHS');
            $table->boolean('email_notifications')->default(true);
            $table->boolean('sms_notifications')->default(false);
            $table->boolean('desktop_notifications')->default(true);

            // Notification preferences
            $table->boolean('notify_leave_approved')->default(true);
            $table->boolean('notify_leave_rejected')->default(true);
            $table->boolean('notify_payslip_ready')->default(true);
            $table->boolean('notify_expense_approved')->default(true);
            $table->boolean('notify_task_assigned')->default(true);
            $table->boolean('notify_announcements')->default(true);

            // Privacy settings
            $table->boolean('show_email_to_colleagues')->default(false);
            $table->boolean('show_phone_to_colleagues')->default(false);
            $table->boolean('show_birthday_to_colleagues')->default(true);
            $table->boolean('show_online_status')->default(true);
            $table->boolean('allow_profile_search')->default(true);
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_method')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
