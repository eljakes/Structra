<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table): void {
            $table->string('department')->nullable();
            $table->string('delivery_location')->nullable();
            $table->text('purpose')->nullable();
            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->json('attachments')->nullable();
        });

        Schema::table('purchase_requisition_lines', function (Blueprint $table): void {
            $table->string('item_name')->nullable();
            $table->decimal('tax_rate', 8, 3)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
        });

        Schema::create('procurement_rfqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rfq_number', 48);
            $table->string('title');
            $table->string('status')->default('draft');
            $table->date('issue_date')->nullable();
            $table->date('closing_date')->nullable();
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'rfq_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('procurement_rfq_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procurement_rfq_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('sent');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['procurement_rfq_id', 'supplier_id']);
        });

        Schema::create('supplier_quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procurement_rfq_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('quotation_number', 48);
            $table->string('supplier_reference')->nullable();
            $table->string('status')->default('submitted');
            $table->date('quote_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->string('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'quotation_number']);
            $table->index(['company_id', 'supplier_id', 'status']);
        });

        Schema::create('supplier_quotation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_quotation_id')->constrained()->cascadeOnDelete();
            $table->string('item_name')->nullable();
            $table->string('description');
            $table->string('cost_code', 40)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('unit', 24)->default('each');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 8, 3)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->timestampsTz();
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignId('supplier_quotation_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('grn_number', 48);
            $table->string('status')->default('received');
            $table->date('received_date')->nullable();
            $table->string('delivery_note_number')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'grn_number']);
            $table->index(['company_id', 'purchase_order_id', 'status']);
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('ordered_quantity', 12, 2)->default(0);
            $table->decimal('received_quantity', 12, 2)->default(0);
            $table->decimal('accepted_quantity', 12, 2)->default(0);
            $table->decimal('rejected_quantity', 12, 2)->default(0);
            $table->string('unit', 24)->default('each');
            $table->string('condition')->default('pending');
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('procurement_quality_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('inspection_number', 48);
            $table->string('status')->default('pending');
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('inspected_at')->nullable();
            $table->text('result_summary')->nullable();
            $table->text('corrective_action')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'inspection_number']);
        });

        Schema::create('supplier_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 48);
            $table->string('supplier_reference')->nullable();
            $table->string('status')->default('submitted');
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'invoice_number']);
            $table->index(['company_id', 'supplier_id', 'status']);
        });

        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('payment_number', 48);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('method')->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['company_id', 'payment_number']);
        });

        Schema::create('supplier_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_number', 48);
            $table->string('title');
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->decimal('contract_value', 15, 2)->default(0);
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'contract_number']);
            $table->index(['company_id', 'supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_contracts');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('procurement_quality_inspections');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_quotation_id');
        });

        Schema::dropIfExists('supplier_quotation_lines');
        Schema::dropIfExists('supplier_quotations');
        Schema::dropIfExists('procurement_rfq_suppliers');
        Schema::dropIfExists('procurement_rfqs');

        Schema::table('purchase_requisition_lines', function (Blueprint $table): void {
            $table->dropColumn(['item_name', 'tax_rate', 'tax_amount', 'discount_amount', 'line_total']);
        });

        Schema::table('purchase_requisitions', function (Blueprint $table): void {
            $table->dropColumn(['department', 'delivery_location', 'purpose', 'subtotal_amount', 'tax_amount', 'discount_amount', 'grand_total', 'attachments']);
        });
    }
};
