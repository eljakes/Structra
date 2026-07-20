<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 48);
            $table->string('title');
            $table->string('status')->default('draft');
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->text('notes')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'invoice_number']);
            $table->index(['company_id', 'status', 'payment_status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('cost_code', 40)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 24)->default('each');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('line_subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestampsTz();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_number', 48);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('GHS');
            $table->string('method')->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->timestampTz('received_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'payment_number']);
            $table->index(['company_id', 'invoice_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('expense_number', 48);
            $table->string('category')->default('site_cost');
            $table->string('description');
            $table->string('cost_code', 40)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->string('status')->default('draft');
            $table->date('incurred_on')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'expense_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_number', 48);
            $table->date('entry_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'entry_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_code', 40);
            $table->string('account_name');
            $table->string('description')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestampsTz();
        });

        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_number', 48);
            $table->string('employment_type')->default('full_time');
            $table->string('department')->default('operations');
            $table->string('position')->nullable();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('hourly_rate', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->date('hire_date')->nullable();
            $table->string('status')->default('active');
            $table->string('emergency_contact')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'employee_number']);
            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type')->default('annual');
            $table->string('status')->default('pending');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('days', 6, 2)->default(1);
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'status']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('run_number', 48);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('GHS');
            $table->decimal('gross_pay', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'run_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('gross_pay', 15, 2)->default(0);
            $table->decimal('overtime_pay', 15, 2)->default(0);
            $table->decimal('allowances', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->timestampTz('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['payroll_run_id', 'employee_profile_id']);
        });

        Schema::create('equipment_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('equipment_number', 48);
            $table->string('name');
            $table->string('category')->default('plant');
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('ownership_type')->default('owned');
            $table->string('status')->default('available');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 15, 2)->default(0);
            $table->decimal('hourly_rate', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->decimal('meter_reading', 12, 2)->default(0);
            $table->date('next_service_due_on')->nullable();
            $table->decimal('next_service_meter', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'equipment_number']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('equipment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignment_number', 48);
            $table->string('status')->default('active');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->decimal('meter_start', 12, 2)->nullable();
            $table->decimal('meter_end', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['company_id', 'assignment_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_asset_id')->constrained()->cascadeOnDelete();
            $table->string('maintenance_number', 48);
            $table->string('type')->default('preventive');
            $table->string('status')->default('scheduled');
            $table->date('service_date');
            $table->timestampTz('completed_at')->nullable();
            $table->decimal('meter_reading', 12, 2)->nullable();
            $table->decimal('cost_amount', 15, 2)->default(0);
            $table->string('vendor')->nullable();
            $table->text('description')->nullable();
            $table->date('next_service_due_on')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'maintenance_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('fuel_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fuel_number', 48);
            $table->date('fuel_date');
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 24)->default('litre');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('meter_reading', 12, 2)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'fuel_number']);
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('inspection_number', 48);
            $table->string('type')->default('quality');
            $table->string('area')->nullable();
            $table->string('status')->default('scheduled');
            $table->date('scheduled_on')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'inspection_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->string('checklist_item');
            $table->string('requirement')->nullable();
            $table->string('result')->default('pending');
            $table->string('severity')->default('medium');
            $table->text('notes')->nullable();
            $table->timestampTz('corrected_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('non_conformance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ncr_number', 48);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'ncr_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('incident_number', 48);
            $table->string('incident_type')->default('near_miss');
            $table->string('severity')->default('medium');
            $table->string('status')->default('reported');
            $table->timestampTz('occurred_at');
            $table->string('location')->nullable();
            $table->string('injured_person')->nullable();
            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'incident_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('toolbox_talks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('talk_number', 48);
            $table->string('topic');
            $table->date('talk_date');
            $table->foreignId('presenter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('attendee_count')->default(0);
            $table->text('summary')->nullable();
            $table->json('hazards_discussed')->nullable();
            $table->string('status')->default('completed');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'talk_number']);
        });

        Schema::create('safety_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('observation_number', 48);
            $table->string('observation_type')->default('unsafe');
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->string('location')->nullable();
            $table->text('description');
            $table->text('corrective_action')->nullable();
            $table->timestampTz('observed_at');
            $table->foreignId('observed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'observation_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('work_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('permit_number', 48);
            $table->string('permit_type')->default('hot_work');
            $table->string('status')->default('draft');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->string('location')->nullable();
            $table->text('hazards')->nullable();
            $table->text('controls')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'permit_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('portal_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_type')->default('client');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('organization')->nullable();
            $table->string('status')->default('invited');
            $table->timestampTz('last_login_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'email']);
            $table->index(['company_id', 'user_type', 'status']);
        });

        Schema::create('portal_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('access_level')->default('view');
            $table->json('disciplines')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['portal_user_id', 'project_id']);
            $table->index(['company_id', 'access_level']);
        });

        Schema::create('client_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drawing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('approval_number', 48);
            $table->string('title');
            $table->string('status')->default('submitted');
            $table->date('due_date')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'approval_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('consultant_submittals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drawing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('submittal_number', 48);
            $table->string('title');
            $table->string('discipline')->default('architectural');
            $table->string('status')->default('submitted');
            $table->date('due_date')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'submittal_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_submittals');
        Schema::dropIfExists('client_approvals');
        Schema::dropIfExists('portal_accesses');
        Schema::dropIfExists('portal_users');
        Schema::dropIfExists('work_permits');
        Schema::dropIfExists('safety_observations');
        Schema::dropIfExists('toolbox_talks');
        Schema::dropIfExists('safety_incidents');
        Schema::dropIfExists('non_conformance_reports');
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('fuel_logs');
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('equipment_assignments');
        Schema::dropIfExists('equipment_assets');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('employee_profiles');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
