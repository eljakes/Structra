<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('lead_number', 48);
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->default('direct');
            $table->string('stage')->default('new');
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->timestampTz('next_follow_up_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'lead_number']);
            $table->index(['company_id', 'branch_id', 'stage']);
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('opportunity_number', 48);
            $table->string('name');
            $table->string('stage')->default('qualified');
            $table->text('scope')->nullable();
            $table->unsignedTinyInteger('probability')->default(30);
            $table->decimal('estimated_value', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->date('expected_close_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'opportunity_number']);
            $table->index(['company_id', 'branch_id', 'stage']);
        });

        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tender_number', 48);
            $table->string('title');
            $table->string('status')->default('draft');
            $table->timestampTz('deadline_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('won_at')->nullable();
            $table->text('lost_reason')->nullable();
            $table->decimal('value', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->json('checklist')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'tender_number']);
            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('tender_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('document_type')->default('tender');
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('tender_rfis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('question');
            $table->text('response')->nullable();
            $table->string('status')->default('open');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('pricing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_code', 40)->nullable();
            $table->string('description');
            $table->string('category')->default('materials');
            $table->string('unit', 24)->default('each');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->string('source')->default('internal');
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'category', 'active']);
        });

        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tender_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('estimate_number', 48);
            $table->string('title');
            $table->string('status')->default('draft');
            $table->string('scenario_name')->default('Base');
            $table->string('currency', 3)->default('GHS');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('overhead_percent', 6, 2)->default(0);
            $table->decimal('profit_percent', 6, 2)->default(0);
            $table->decimal('tax_percent', 6, 2)->default(0);
            $table->decimal('overhead_amount', 15, 2)->default(0);
            $table->decimal('profit_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'estimate_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('estimate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('estimate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_code', 40)->nullable();
            $table->string('description');
            $table->string('category')->default('materials');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 24)->default('each');
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('markup_percent', 6, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestampsTz();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->text('location')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64);
            $table->string('name');
            $table->string('category')->default('materials');
            $table->string('unit', 24)->default('each');
            $table->decimal('reorder_level', 12, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->decimal('average_cost', 15, 2)->default(0);
            $table->decimal('quantity_on_hand', 12, 2)->default(0);
            $table->string('status')->default('active');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'category', 'status']);
        });

        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 12, 2)->default(0);
            $table->decimal('average_cost', 15, 2)->default(0);
            $table->timestampsTz();

            $table->unique(['warehouse_id', 'inventory_item_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_number', 48);
            $table->string('type');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->text('reason')->nullable();
            $table->timestampTz('moved_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['company_id', 'movement_number']);
            $table->index(['company_id', 'inventory_item_id', 'type']);
        });

        Schema::create('supplier_price_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cost_code', 40)->nullable();
            $table->string('description');
            $table->string('unit', 24)->default('each');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'supplier_id']);
        });

        Schema::create('supplier_performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating')->default(3);
            $table->unsignedTinyInteger('quality_score')->default(3);
            $table->unsignedTinyInteger('delivery_score')->default(3);
            $table->unsignedTinyInteger('cost_score')->default(3);
            $table->text('notes')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('field_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('report_number', 48);
            $table->date('report_date');
            $table->string('weather')->nullable();
            $table->string('shift')->default('day');
            $table->unsignedSmallInteger('labour_count')->default(0);
            $table->text('equipment_notes')->nullable();
            $table->text('progress_notes')->nullable();
            $table->text('safety_notes')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'report_number']);
            $table->unique(['project_id', 'report_date', 'shift']);
        });

        Schema::create('field_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_daily_report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('blocker');
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->string('photo_path')->nullable();
            $table->date('due_date')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('clock_in_at');
            $table->timestampTz('clock_out_at')->nullable();
            $table->decimal('clock_in_latitude', 10, 7)->nullable();
            $table->decimal('clock_in_longitude', 10, 7)->nullable();
            $table->decimal('clock_out_latitude', 10, 7)->nullable();
            $table->decimal('clock_out_longitude', 10, 7)->nullable();
            $table->string('face_in_path')->nullable();
            $table->string('face_out_path')->nullable();
            $table->string('status')->default('open');
            $table->unsignedInteger('total_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'user_id', 'status']);
        });

        Schema::create('drawing_markups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drawing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drawing_revision_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('markup_type')->default('comment');
            $table->decimal('x', 8, 4)->default(0);
            $table->decimal('y', 8, 4)->default(0);
            $table->decimal('width', 8, 4)->nullable();
            $table->decimal('height', 8, 4)->nullable();
            $table->text('comment');
            $table->string('status')->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('drawing_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drawing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drawing_revision_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision');
            $table->text('comments')->nullable();
            $table->timestampTz('reviewed_at');
            $table->timestampsTz();

            $table->index(['company_id', 'drawing_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawing_reviews');
        Schema::dropIfExists('drawing_markups');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('field_issues');
        Schema::dropIfExists('field_daily_reports');
        Schema::dropIfExists('supplier_performance_reviews');
        Schema::dropIfExists('supplier_price_catalogs');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('estimate_lines');
        Schema::dropIfExists('estimates');
        Schema::dropIfExists('pricing_items');
        Schema::dropIfExists('tender_rfis');
        Schema::dropIfExists('tender_documents');
        Schema::dropIfExists('tenders');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('leads');
    }
};
