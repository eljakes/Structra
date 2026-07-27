<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('automation_rules', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (! Schema::hasColumn('automation_rules', 'module')) {
                $table->string('module')->default('general')->after('description');
            }

            if (! Schema::hasColumn('automation_rules', 'status')) {
                $table->string('status')->default('draft')->after('module');
            }

            if (! Schema::hasColumn('automation_rules', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('status');
            }

            if (! Schema::hasColumn('automation_rules', 'workflow_definition')) {
                $table->json('workflow_definition')->nullable()->after('actions');
            }

            if (! Schema::hasColumn('automation_rules', 'schedule_config')) {
                $table->json('schedule_config')->nullable()->after('workflow_definition');
            }

            if (! Schema::hasColumn('automation_rules', 'approval_config')) {
                $table->json('approval_config')->nullable()->after('schedule_config');
            }

            if (! Schema::hasColumn('automation_rules', 'notification_config')) {
                $table->json('notification_config')->nullable()->after('approval_config');
            }

            if (! Schema::hasColumn('automation_rules', 'settings')) {
                $table->json('settings')->nullable()->after('notification_config');
            }

            if (! Schema::hasColumn('automation_rules', 'execution_mode')) {
                $table->string('execution_mode')->default('sync')->after('settings');
            }
        });

        Schema::table('automation_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('automation_runs', 'trigger_event')) {
                $table->string('trigger_event')->nullable()->after('status');
            }

            if (! Schema::hasColumn('automation_runs', 'duration_ms')) {
                $table->unsignedInteger('duration_ms')->default(0)->after('actions_executed');
            }

            if (! Schema::hasColumn('automation_runs', 'retry_count')) {
                $table->unsignedSmallInteger('retry_count')->default(0)->after('duration_ms');
            }

            if (! Schema::hasColumn('automation_runs', 'ip_address')) {
                $table->string('ip_address', 64)->nullable()->after('retry_count');
            }

            if (! Schema::hasColumn('automation_runs', 'context_payload')) {
                $table->json('context_payload')->nullable()->after('action_results');
            }
        });

        Schema::create('automation_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('module')->default('general');
            $table->string('category')->default('workflow');
            $table->text('description')->nullable();
            $table->json('workflow_definition');
            $table->json('conditions')->nullable();
            $table->json('actions')->nullable();
            $table->json('approval_config')->nullable();
            $table->json('schedule_config')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestampsTz();

            $table->index(['company_id', 'module']);
        });

        Schema::create('automation_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('changed_at');
            $table->timestampsTz();

            $table->unique(['automation_rule_id', 'version']);
            $table->index(['company_id', 'automation_rule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rule_versions');
        Schema::dropIfExists('automation_templates');

        Schema::table('automation_runs', function (Blueprint $table): void {
            foreach (['trigger_event', 'duration_ms', 'retry_count', 'ip_address', 'context_payload'] as $column) {
                if (Schema::hasColumn('automation_runs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('automation_rules', function (Blueprint $table): void {
            foreach (['description', 'module', 'status', 'version', 'workflow_definition', 'schedule_config', 'approval_config', 'notification_config', 'settings', 'execution_mode'] as $column) {
                if (Schema::hasColumn('automation_rules', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
