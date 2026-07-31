<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('reply_to_email')->nullable();
            $table->string('minimum_email_severity')->default('medium');
            $table->string('digest_frequency')->default('immediate');
            $table->json('default_channels')->nullable();
            $table->json('module_preferences')->nullable();
            $table->json('retry_policy')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique('company_id');
        });

        Schema::create('notification_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('automation_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notification_number', 48);
            $table->string('source_key', 160)->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('module')->default('general');
            $table->string('event_type')->default('system_alert');
            $table->string('title');
            $table->text('message');
            $table->string('severity')->default('medium');
            $table->string('status')->default('unread');
            $table->json('channels')->nullable();
            $table->json('delivery_status')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('email_subject')->nullable();
            $table->timestampTz('email_sent_at')->nullable();
            $table->text('email_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'notification_number']);
            $table->unique(['company_id', 'source_key']);
            $table->index(['company_id', 'module', 'severity', 'status']);
            $table->index(['company_id', 'source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
        Schema::dropIfExists('notification_settings');
    }
};
