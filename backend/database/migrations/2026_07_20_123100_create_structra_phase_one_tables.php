<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_number')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('default_currency', 3)->default('GHS');
            $table->string('country', 2)->default('GH');
            $table->string('base_timezone')->default('Africa/Accra');
            $table->string('status')->default('active');
            $table->json('settings')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 24);
            $table->string('city')->nullable();
            $table->string('country', 2)->default('GH');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->timestampsTz();

            $table->unique(['company_id', 'slug']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->string('status')->default('active');
            $table->timestampTz('last_login_at')->nullable();

            $table->index(['company_id', 'status']);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('corporate');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('planning');
            $table->string('health_status')->default('on_track');
            $table->string('risk_level')->default('medium');
            $table->text('site_address')->nullable();
            $table->string('country', 2)->default('GH');
            $table->string('currency', 3)->default('GHS');
            $table->decimal('contract_value', 15, 2)->default(0);
            $table->decimal('budget_total', 15, 2)->default(0);
            $table->decimal('committed_total', 15, 2)->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->decimal('forecast_to_complete', 15, 2)->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->date('start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');
            $table->string('priority')->default('normal');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->json('dependencies')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('cost_code', 40);
            $table->string('description');
            $table->string('category')->default('materials');
            $table->decimal('budget_amount', 15, 2)->default(0);
            $table->decimal('committed_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->decimal('forecast_amount', 15, 2)->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['project_id', 'cost_code']);
            $table->index(['company_id', 'category']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->unsignedTinyInteger('rating')->default(3);
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'status']);
        });

        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('requisition_number', 48);
            $table->string('title');
            $table->string('status')->default('draft');
            $table->string('priority')->default('normal');
            $table->date('required_by')->nullable();
            $table->decimal('total_estimated', 15, 2)->default(0);
            $table->text('justification')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'requisition_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('purchase_requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('cost_code', 40)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 24)->default('each');
            $table->decimal('estimated_unit_cost', 15, 2)->default(0);
            $table->decimal('estimated_total', 15, 2)->default(0);
            $table->timestampsTz();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('po_number', 48);
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('GHS');
            $table->date('issue_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('delivery_status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'po_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('cost_code', 40)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 24)->default('each');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestampsTz();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number', 48);
            $table->string('title');
            $table->string('document_type')->default('general');
            $table->string('repository_scope')->default('branch');
            $table->string('folder')->default('/');
            $table->string('status')->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->json('tags')->nullable();
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'document_number']);
            $table->index(['company_id', 'branch_id', 'project_id', 'document_type']);
        });

        Schema::create('drawings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('drawing_number', 80);
            $table->string('title');
            $table->string('discipline')->default('architectural');
            $table->string('status')->default('draft');
            $table->string('current_revision')->default('P01');
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->json('linked_records')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'project_id', 'drawing_number']);
            $table->index(['company_id', 'branch_id', 'discipline', 'status']);
        });

        Schema::create('drawing_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drawing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revision_code', 24);
            $table->string('status')->default('issued_for_review');
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['drawing_id', 'revision_code']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('drawing_revisions');
        Schema::dropIfExists('drawings');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_requisition_lines');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('clients');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'company_id',
                'branch_id',
                'role_id',
                'phone',
                'job_title',
                'status',
                'last_login_at',
            ]);
        });

        Schema::dropIfExists('roles');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
