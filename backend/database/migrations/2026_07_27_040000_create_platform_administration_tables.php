<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            foreach ([
                'tenant_key' => fn () => $table->string('tenant_key', 80)->nullable()->unique()->after('id'),
                'industry' => fn () => $table->string('industry')->nullable()->after('tax_id'),
                'city' => fn () => $table->string('city')->nullable()->after('country'),
                'address' => fn () => $table->text('address')->nullable()->after('city'),
                'phone' => fn () => $table->string('phone')->nullable()->after('address'),
                'email' => fn () => $table->string('email')->nullable()->after('phone'),
                'website' => fn () => $table->string('website')->nullable()->after('email'),
                'language' => fn () => $table->string('language', 12)->default('en')->after('base_timezone'),
                'date_format' => fn () => $table->string('date_format', 32)->default('Y-m-d')->after('language'),
                'fiscal_year_start' => fn () => $table->string('fiscal_year_start', 5)->nullable()->after('date_format'),
                'trial_ends_at' => fn () => $table->timestampTz('trial_ends_at')->nullable()->after('status'),
                'storage_limit_mb' => fn () => $table->unsignedInteger('storage_limit_mb')->nullable()->after('trial_ends_at'),
                'employee_limit' => fn () => $table->unsignedInteger('employee_limit')->nullable()->after('storage_limit_mb'),
                'project_limit' => fn () => $table->unsignedInteger('project_limit')->nullable()->after('employee_limit'),
                'branch_limit' => fn () => $table->unsignedInteger('branch_limit')->nullable()->after('project_limit'),
                'provisioned_at' => fn () => $table->timestampTz('provisioned_at')->nullable()->after('branch_limit'),
            ] as $column => $definition) {
                if (! Schema::hasColumn('companies', $column)) {
                    $definition();
                }
            }
        });

        Schema::create('platform_subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('currency', 3)->default('GHS');
            $table->decimal('monthly_price', 15, 2)->default(0);
            $table->decimal('yearly_price', 15, 2)->default(0);
            $table->unsignedInteger('maximum_users')->nullable();
            $table->unsignedInteger('maximum_projects')->nullable();
            $table->unsignedInteger('maximum_storage_mb')->nullable();
            $table->unsignedInteger('portal_users')->nullable();
            $table->unsignedInteger('automation_limit')->nullable();
            $table->unsignedInteger('ai_credits')->nullable();
            $table->string('support_level')->default('standard');
            $table->boolean('api_access')->default(false);
            $table->boolean('custom_branding')->default(false);
            $table->boolean('sso_available')->default(false);
            $table->json('modules')->nullable();
            $table->json('features')->nullable();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('company_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subscription_number', 48);
            $table->string('status')->default('trial');
            $table->string('billing_interval')->default('monthly');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->unsignedInteger('seats')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('current_period_starts_at')->nullable();
            $table->timestampTz('current_period_ends_at')->nullable();
            $table->timestampTz('renewal_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'subscription_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('platform_feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('name');
            $table->string('module')->default('platform');
            $table->string('category')->default('module');
            $table->text('description')->nullable();
            $table->boolean('default_enabled')->default(false);
            $table->string('rollout_status')->default('active');
            $table->unsignedTinyInteger('rollout_percentage')->default(100);
            $table->string('pricing_tier')->nullable();
            $table->boolean('requires_subscription')->default(true);
            $table->json('configuration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['module', 'category', 'rollout_status']);
        });

        Schema::create('company_feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_feature_flag_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('limit_value')->nullable();
            $table->json('configuration')->nullable();
            $table->timestampTz('enabled_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['company_id', 'platform_feature_flag_id']);
            $table->index(['company_id', 'is_enabled']);
        });

        Schema::create('company_branding_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('logo_path')->nullable();
            $table->string('dark_logo_path')->nullable();
            $table->string('light_logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('login_background_path')->nullable();
            $table->string('dashboard_background_path')->nullable();
            $table->string('primary_color', 20)->default('#2364d8');
            $table->string('secondary_color', 20)->default('#188a5a');
            $table->string('accent_color', 20)->default('#b66a05');
            $table->string('sidebar_color', 20)->default('#102033');
            $table->string('button_color', 20)->default('#2364d8');
            $table->string('typography')->default('Inter');
            $table->json('email_templates')->nullable();
            $table->json('pdf_templates')->nullable();
            $table->json('invoice_template')->nullable();
            $table->json('quotation_template')->nullable();
            $table->json('letterhead')->nullable();
            $table->json('report_header')->nullable();
            $table->string('watermark_path')->nullable();
            $table->text('login_welcome_message')->nullable();
            $table->string('company_motto')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique('company_id');
        });

        Schema::create('platform_billing_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('record_number', 48);
            $table->string('record_type')->default('invoice');
            $table->string('status')->default('draft');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'record_number']);
            $table->index(['company_id', 'record_type', 'status']);
        });

        Schema::create('platform_support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ticket_number', 48)->unique();
            $table->string('title');
            $table->string('category')->default('support');
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestampTz('sla_due_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'status', 'priority']);
        });

        Schema::create('platform_deployments', function (Blueprint $table): void {
            $table->id();
            $table->string('deployment_number', 48)->unique();
            $table->string('title');
            $table->string('release_version')->nullable();
            $table->string('target_scope')->default('all_customers');
            $table->json('target_filter')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('deployed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['status', 'target_scope']);
        });

        Schema::create('platform_security_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['company_id', 'event_type', 'severity', 'status']);
        });

        Schema::create('platform_backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('backup_number', 48)->unique();
            $table->string('backup_type')->default('tenant');
            $table->string('status')->default('queued');
            $table->string('storage_path')->nullable();
            $table->decimal('size_mb', 12, 2)->default(0);
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'status', 'backup_type']);
        });

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key')->unique();
            $table->json('setting_value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('platform_backups');
        Schema::dropIfExists('platform_security_events');
        Schema::dropIfExists('platform_deployments');
        Schema::dropIfExists('platform_support_tickets');
        Schema::dropIfExists('platform_billing_records');
        Schema::dropIfExists('company_branding_profiles');
        Schema::dropIfExists('company_feature_flags');
        Schema::dropIfExists('platform_feature_flags');
        Schema::dropIfExists('company_subscriptions');
        Schema::dropIfExists('platform_subscription_plans');

        Schema::table('companies', function (Blueprint $table): void {
            foreach ([
                'tenant_key',
                'industry',
                'city',
                'address',
                'phone',
                'email',
                'website',
                'language',
                'date_format',
                'fiscal_year_start',
                'trial_ends_at',
                'storage_limit_mb',
                'employee_limit',
                'project_limit',
                'branch_limit',
                'provisioned_at',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
