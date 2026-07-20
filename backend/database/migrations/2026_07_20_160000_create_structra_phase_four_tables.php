<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key', 120)->nullable();
            $table->string('category')->default('risk');
            $table->string('severity')->default('medium');
            $table->string('title');
            $table->text('narrative');
            $table->text('recommendation')->nullable();
            $table->json('signals')->nullable();
            $table->unsignedTinyInteger('confidence_score')->default(70);
            $table->string('status')->default('open');
            $table->string('source')->default('structra_ai');
            $table->timestampTz('detected_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'source_key']);
            $table->index(['company_id', 'category', 'severity', 'status']);
        });

        Schema::create('predictive_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('forecast_number', 48);
            $table->string('source_key', 120)->nullable();
            $table->string('forecast_type')->default('cost');
            $table->string('period_label')->nullable();
            $table->decimal('baseline_value', 15, 2)->default(0);
            $table->decimal('forecast_value', 15, 2)->default(0);
            $table->decimal('variance_value', 15, 2)->default(0);
            $table->unsignedTinyInteger('confidence_score')->default(70);
            $table->json('drivers')->nullable();
            $table->string('status')->default('current');
            $table->timestampTz('generated_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'forecast_number']);
            $table->unique(['company_id', 'source_key']);
            $table->index(['company_id', 'forecast_type', 'status']);
        });

        Schema::create('assistant_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('intent')->default('general');
            $table->text('question');
            $table->text('answer');
            $table->json('filters')->nullable();
            $table->json('data_sources')->nullable();
            $table->json('result_payload')->nullable();
            $table->unsignedTinyInteger('confidence_score')->default(75);
            $table->timestampTz('answered_at');
            $table->timestampsTz();

            $table->index(['company_id', 'intent']);
        });

        Schema::create('bi_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('audience')->default('executive');
            $table->string('refresh_interval')->default('daily');
            $table->json('filters')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('bi_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bi_dashboard_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('widget_type')->default('metric');
            $table->string('metric_key');
            $table->json('configuration')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
        });

        Schema::create('metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('snapshot_number', 48);
            $table->string('period_label');
            $table->date('snapshot_date');
            $table->json('metrics');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['company_id', 'snapshot_number']);
            $table->index(['company_id', 'snapshot_date']);
        });

        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('rule_type')->default('project_overrun');
            $table->string('trigger_event')->default('manual');
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->string('severity')->default('medium');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'rule_type', 'is_active']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->string('run_number', 48);
            $table->string('status')->default('completed');
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('actions_executed')->default(0);
            $table->json('matched_records')->nullable();
            $table->json('action_results')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['company_id', 'run_number']);
        });

        Schema::create('integration_connectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('name');
            $table->string('category')->default('accounting');
            $table->string('status')->default('configured');
            $table->json('settings')->nullable();
            $table->text('encrypted_credentials')->nullable();
            $table->timestampTz('last_tested_at')->nullable();
            $table->timestampTz('connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'provider', 'name']);
            $table->index(['company_id', 'category', 'status']);
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('event_type');
            $table->string('target_url');
            $table->string('secret');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_dispatched_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'event_type', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload');
            $table->string('signature');
            $table->string('status')->default('queued');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'event_type', 'status']);
        });

        Schema::create('localization_countries', function (Blueprint $table) {
            $table->id();
            $table->string('iso2', 2)->unique();
            $table->string('name');
            $table->string('currency', 3);
            $table->string('timezone')->default('UTC');
            $table->string('tax_label')->default('VAT');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('company_localization_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('base_country', 2)->default('GH');
            $table->string('base_currency', 3)->default('GHS');
            $table->json('enabled_countries')->nullable();
            $table->json('enabled_currencies')->nullable();
            $table->string('tax_rounding_mode')->default('line');
            $table->string('date_format')->default('Y-m-d');
            $table->timestampsTz();

            $table->unique('company_id');
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('country', 2);
            $table->string('name');
            $table->string('tax_type')->default('vat');
            $table->decimal('rate_percent', 6, 3)->default(0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();

            $table->index(['company_id', 'country', 'tax_type']);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->date('rate_date');
            $table->string('source')->default('manual');
            $table->timestampsTz();

            $table->unique(['company_id', 'base_currency', 'quote_currency', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('company_localization_settings');
        Schema::dropIfExists('localization_countries');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('integration_connectors');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('metric_snapshots');
        Schema::dropIfExists('bi_widgets');
        Schema::dropIfExists('bi_dashboards');
        Schema::dropIfExists('assistant_queries');
        Schema::dropIfExists('predictive_forecasts');
        Schema::dropIfExists('ai_insights');
    }
};
