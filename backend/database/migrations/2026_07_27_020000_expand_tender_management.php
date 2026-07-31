<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->foreignId('tender_manager_id')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
            $table->foreignId('business_development_officer_id')->nullable()->after('tender_manager_id')->constrained('users')->nullOnDelete();
            $table->string('tender_type')->nullable()->after('title');
            $table->string('procurement_method')->nullable()->after('tender_type');
            $table->string('project_sector')->nullable()->after('procurement_method');
            $table->string('project_category')->nullable()->after('project_sector');
            $table->string('project_location')->nullable()->after('project_category');
            $table->decimal('tender_fee', 15, 2)->default(0)->after('value');
            $table->text('description')->nullable()->after('currency');
            $table->text('scope_summary')->nullable()->after('description');
            $table->string('funding_source')->nullable()->after('scope_summary');
            $table->string('tender_authority')->nullable()->after('funding_source');
            $table->string('priority')->default('medium')->after('tender_authority');
            $table->string('confidentiality_level')->default('internal')->after('priority');
            $table->string('bid_decision')->nullable()->after('confidentiality_level');
            $table->unsignedTinyInteger('bid_decision_score')->nullable()->after('bid_decision');
            $table->timestampTz('expected_award_at')->nullable()->after('won_at');
            $table->json('deadline_schedule')->nullable()->after('checklist');
            $table->json('settings')->nullable()->after('deadline_schedule');
        });

        Schema::table('tender_rfis', function (Blueprint $table) {
            $table->string('rfi_number', 48)->nullable()->after('tender_id');
            $table->string('category')->nullable()->after('rfi_number');
            $table->string('submitted_to')->nullable()->after('question');
            $table->timestampTz('submitted_at')->nullable()->after('submitted_to');
            $table->string('related_drawing')->nullable()->after('responded_at');
            $table->string('related_boq_item')->nullable()->after('related_drawing');
            $table->string('related_specification')->nullable()->after('related_boq_item');
            $table->text('internal_impact')->nullable()->after('related_specification');
            $table->decimal('cost_impact', 15, 2)->default(0)->after('internal_impact');
            $table->integer('schedule_impact_days')->default(0)->after('cost_impact');
            $table->json('supporting_documents')->nullable()->after('schedule_impact_days');
        });

        Schema::table('tender_documents', function (Blueprint $table) {
            $table->string('version')->default('1')->after('document_type');
            $table->string('status')->default('draft')->after('version');
            $table->boolean('is_mandatory')->default(false)->after('status');
            $table->boolean('is_confidential')->default(false)->after('is_mandatory');
            $table->date('expires_at')->nullable()->after('is_confidential');
            $table->text('comments')->nullable()->after('size_bytes');
        });

        Schema::create('tender_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('record_type', 80);
            $table->string('record_number', 64)->nullable();
            $table->string('title');
            $table->string('status')->default('pending');
            $table->string('priority')->default('medium');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->text('notes')->nullable();
            $table->json('payload')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'tender_id', 'record_type']);
            $table->index(['company_id', 'record_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_records');

        Schema::table('tender_documents', function (Blueprint $table) {
            $table->dropColumn(['version', 'status', 'is_mandatory', 'is_confidential', 'expires_at', 'comments']);
        });

        Schema::table('tender_rfis', function (Blueprint $table) {
            $table->dropColumn([
                'rfi_number',
                'category',
                'submitted_to',
                'submitted_at',
                'related_drawing',
                'related_boq_item',
                'related_specification',
                'internal_impact',
                'cost_impact',
                'schedule_impact_days',
                'supporting_documents',
            ]);
        });

        Schema::table('tenders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tender_manager_id');
            $table->dropConstrainedForeignId('business_development_officer_id');
            $table->dropColumn([
                'tender_type',
                'procurement_method',
                'project_sector',
                'project_category',
                'project_location',
                'tender_fee',
                'description',
                'scope_summary',
                'funding_source',
                'tender_authority',
                'priority',
                'confidentiality_level',
                'bid_decision',
                'bid_decision_score',
                'expected_award_at',
                'deadline_schedule',
                'settings',
            ]);
        });
    }
};
