<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_profiles', 'manager_id')) {
                $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('current_project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('gender')->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('nationality')->nullable();
                $table->string('marital_status')->nullable();
                $table->string('national_id')->nullable();
                $table->string('tax_number')->nullable();
                $table->string('ssnit_number')->nullable();
                $table->decimal('allowances', 15, 2)->default(0);
                $table->decimal('bonuses', 15, 2)->default(0);
                $table->decimal('deductions', 15, 2)->default(0);
                $table->json('skills')->nullable();
                $table->json('licenses')->nullable();
                $table->text('medical_notes')->nullable();
                $table->string('photo_path')->nullable();
            }
        });

        Schema::create('workforce_job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vacancy_number', 48);
            $table->string('title');
            $table->string('department')->default('operations');
            $table->string('employment_type')->default('full_time');
            $table->unsignedSmallInteger('openings')->default(1);
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            $table->text('description')->nullable();
            $table->json('required_skills')->nullable();
            $table->date('opened_on')->nullable();
            $table->date('closes_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'vacancy_number']);
            $table->index(['company_id', 'status', 'department']);
        });

        Schema::create('workforce_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('candidate_number', 48);
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('trade')->nullable();
            $table->string('location')->nullable();
            $table->string('source')->default('direct');
            $table->string('status')->default('active');
            $table->unsignedTinyInteger('rating')->default(3);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'candidate_number']);
            $table->index(['company_id', 'status', 'trade']);
        });

        Schema::create('workforce_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_vacancy_id')->constrained('workforce_job_vacancies')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('workforce_candidates')->cascadeOnDelete();
            $table->foreignId('hired_employee_profile_id')->nullable()->constrained('employee_profiles')->nullOnDelete();
            $table->string('application_number', 48);
            $table->string('status')->default('applied');
            $table->date('applied_on')->nullable();
            $table->decimal('expected_salary', 15, 2)->default(0);
            $table->unsignedTinyInteger('screening_score')->default(0);
            $table->string('background_check_status')->default('pending');
            $table->string('offer_status')->default('not_sent');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'application_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('workforce_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('workforce_applications')->cascadeOnDelete();
            $table->string('interview_number', 48);
            $table->timestampTz('scheduled_at')->nullable();
            $table->string('stage')->default('technical');
            $table->json('interviewers')->nullable();
            $table->string('result')->default('scheduled');
            $table->unsignedTinyInteger('score')->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'interview_number']);
            $table->index(['company_id', 'result']);
        });

        Schema::create('workforce_onboarding_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->string('checklist_number', 48);
            $table->string('status')->default('open');
            $table->date('due_date')->nullable();
            $table->json('items');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'checklist_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('workforce_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shift_code', 48);
            $table->string('name');
            $table->string('shift_type')->default('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'shift_code']);
            $table->index(['company_id', 'shift_type', 'status']);
        });

        Schema::create('workforce_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('workforce_shifts')->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'employee_profile_id', 'status']);
        });

        Schema::create('workforce_timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('workforce_shifts')->nullOnDelete();
            $table->string('timesheet_number', 48);
            $table->date('work_date');
            $table->decimal('hours_worked', 6, 2)->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->decimal('cost_rate', 15, 2)->default(0);
            $table->decimal('cost_amount', 15, 2)->default(0);
            $table->string('status')->default('submitted');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'timesheet_number']);
            $table->index(['company_id', 'project_id', 'work_date']);
        });

        Schema::create('workforce_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('allocation_number', 48);
            $table->string('role')->default('worker');
            $table->unsignedTinyInteger('allocation_percent')->default(100);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'allocation_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('workforce_overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_number', 48);
            $table->date('work_date');
            $table->decimal('hours', 6, 2)->default(0);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'request_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('workforce_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->string('benefit_type');
            $table->string('provider')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->string('status')->default('active');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'benefit_type', 'status']);
        });

        Schema::create('workforce_performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_number', 48);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedTinyInteger('safety_score')->default(0);
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->unsignedTinyInteger('productivity_score')->default(0);
            $table->unsignedTinyInteger('teamwork_score')->default(0);
            $table->decimal('overall_score', 5, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('goals')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'review_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('workforce_training_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('course_code', 48);
            $table->string('title');
            $table->string('category')->default('safety');
            $table->string('provider')->nullable();
            $table->decimal('duration_hours', 6, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'course_code']);
            $table->index(['company_id', 'category', 'status']);
        });

        Schema::create('workforce_training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_course_id')->constrained('workforce_training_courses')->cascadeOnDelete();
            $table->string('status')->default('scheduled');
            $table->date('scheduled_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('certificate_number')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'status']);
        });

        Schema::create('workforce_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->string('certification_number', 48);
            $table->string('name');
            $table->string('issuing_authority')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status')->default('valid');
            $table->string('document_path')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'certification_number']);
            $table->index(['company_id', 'status', 'expires_on']);
        });

        Schema::create('workforce_ppe_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ppe_number', 48);
            $table->string('item_name');
            $table->string('size')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->date('issued_on')->nullable();
            $table->date('replacement_due_on')->nullable();
            $table->string('condition')->default('new');
            $table->string('status')->default('issued');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'ppe_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('workforce_contractors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contractor_number', 48);
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('trade')->nullable();
            $table->unsignedInteger('worker_count')->default(0);
            $table->date('contract_expires_on')->nullable();
            $table->date('insurance_expires_on')->nullable();
            $table->string('compliance_status')->default('pending');
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'contractor_number']);
            $table->index(['company_id', 'status', 'compliance_status']);
        });

        Schema::create('workforce_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_number', 48);
            $table->string('item_name');
            $table->string('category')->default('tool');
            $table->string('serial_number')->nullable();
            $table->date('assigned_on')->nullable();
            $table->date('return_due_on')->nullable();
            $table->date('returned_on')->nullable();
            $table->string('status')->default('assigned');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'asset_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('workforce_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('workforce_candidates')->nullOnDelete();
            $table->string('document_number', 48);
            $table->string('document_type')->default('contract');
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'document_type', 'status']);
        });

        Schema::create('workforce_exit_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->string('exit_number', 48);
            $table->string('exit_type')->default('resignation');
            $table->date('notice_date')->nullable();
            $table->date('exit_date')->nullable();
            $table->text('reason')->nullable();
            $table->json('clearance_items')->nullable();
            $table->string('clearance_status')->default('pending');
            $table->string('status')->default('open');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'exit_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('workforce_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('setting_key');
            $table->json('setting_value')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workforce_settings');
        Schema::dropIfExists('workforce_exit_records');
        Schema::dropIfExists('workforce_documents');
        Schema::dropIfExists('workforce_assets');
        Schema::dropIfExists('workforce_contractors');
        Schema::dropIfExists('workforce_ppe_issues');
        Schema::dropIfExists('workforce_certifications');
        Schema::dropIfExists('workforce_training_records');
        Schema::dropIfExists('workforce_training_courses');
        Schema::dropIfExists('workforce_performance_reviews');
        Schema::dropIfExists('workforce_benefits');
        Schema::dropIfExists('workforce_overtime_requests');
        Schema::dropIfExists('workforce_allocations');
        Schema::dropIfExists('workforce_timesheets');
        Schema::dropIfExists('workforce_shift_assignments');
        Schema::dropIfExists('workforce_shifts');
        Schema::dropIfExists('workforce_onboarding_checklists');
        Schema::dropIfExists('workforce_interviews');
        Schema::dropIfExists('workforce_applications');
        Schema::dropIfExists('workforce_candidates');
        Schema::dropIfExists('workforce_job_vacancies');

        Schema::table('employee_profiles', function (Blueprint $table) {
            foreach ([
                'manager_id', 'current_project_id', 'gender', 'date_of_birth',
                'nationality', 'marital_status', 'national_id', 'tax_number',
                'ssnit_number', 'allowances', 'bonuses', 'deductions', 'skills',
                'licenses', 'medical_notes', 'photo_path',
            ] as $column) {
                if (Schema::hasColumn('employee_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
