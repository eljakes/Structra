<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $table->string('account_code', 40);
            $table->string('account_name');
            $table->string('account_type');
            $table->string('normal_balance')->default('debit');
            $table->boolean('is_control_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'account_code']);
            $table->index(['company_id', 'account_type', 'is_active']);
        });

        Schema::create('finance_cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('type')->default('project');
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type', 'status']);
        });

        Schema::create('finance_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_name');
            $table->string('bank_name');
            $table->string('account_number')->nullable();
            $table->string('currency', 3)->default('GHS');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'status', 'is_default']);
        });

        Schema::create('finance_bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_bank_account_id')->constrained('finance_bank_accounts')->cascadeOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 15, 2)->default(0);
            $table->decimal('system_balance', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'finance_bank_account_id', 'statement_date']);
        });

        Schema::create('finance_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('finance_cost_centers')->nullOnDelete();
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('entry_date');
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->decimal('running_balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['company_id', 'finance_account_id', 'entry_date']);
            $table->index(['company_id', 'source_type', 'source_id']);
        });

        Schema::create('finance_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('credit_note_number', 48);
            $table->date('issue_date');
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->text('reason')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'credit_note_number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('finance_retentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('retention_number', 48);
            $table->string('party_type')->default('client');
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->decimal('retention_percent', 6, 2)->default(0);
            $table->decimal('retention_amount', 15, 2)->default(0);
            $table->decimal('released_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2)->default(0);
            $table->string('status')->default('held');
            $table->date('due_date')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'retention_number']);
            $table->index(['company_id', 'party_type', 'status']);
        });

        Schema::create('finance_progress_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('milestone_number', 48);
            $table->string('milestone_name');
            $table->decimal('progress_percent', 6, 2)->default(0);
            $table->decimal('billable_amount', 15, 2)->default(0);
            $table->decimal('retention_percent', 6, 2)->default(0);
            $table->string('status')->default('draft');
            $table->date('due_date')->nullable();
            $table->timestampTz('certified_at')->nullable();
            $table->timestampTz('invoiced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'milestone_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('finance_fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_number', 48);
            $table->string('name');
            $table->string('category')->default('equipment');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 15, 2)->default(0);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->string('depreciation_method')->default('straight_line');
            $table->unsignedSmallInteger('useful_life_months')->default(60);
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->string('status')->default('active');
            $table->date('disposal_date')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'asset_number']);
            $table->index(['company_id', 'category', 'status']);
        });

        Schema::create('finance_tax_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('tax_name');
            $table->string('tax_type');
            $table->decimal('rate', 7, 3)->default(0);
            $table->string('applies_to')->default('sales');
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['company_id', 'tax_type', 'is_active']);
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'finance_bank_account_id')) {
                $table->unsignedBigInteger('finance_bank_account_id')->nullable()->after('client_id');
                $table->index('finance_bank_account_id');
            }
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_payments', 'finance_bank_account_id')) {
                $table->unsignedBigInteger('finance_bank_account_id')->nullable()->after('supplier_invoice_id');
                $table->index('finance_bank_account_id');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'retention_percent')) {
                $table->decimal('retention_percent', 6, 2)->default(0)->after('tax_amount');
                $table->decimal('retention_amount', 15, 2)->default(0)->after('retention_percent');
                $table->decimal('progress_percent', 6, 2)->default(0)->after('retention_amount');
                $table->string('billing_stage')->nullable()->after('progress_percent');
                $table->decimal('credit_note_amount', 15, 2)->default(0)->after('amount_paid');
            }
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_invoices', 'retention_percent')) {
                $table->decimal('retention_percent', 6, 2)->default(0)->after('tax_amount');
                $table->decimal('retention_amount', 15, 2)->default(0)->after('retention_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            foreach (['retention_percent', 'retention_amount'] as $column) {
                if (Schema::hasColumn('supplier_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            foreach (['retention_percent', 'retention_amount', 'progress_percent', 'billing_stage', 'credit_note_amount'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_payments', 'finance_bank_account_id')) {
                $table->dropColumn('finance_bank_account_id');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'finance_bank_account_id')) {
                $table->dropColumn('finance_bank_account_id');
            }
        });

        Schema::dropIfExists('finance_tax_rules');
        Schema::dropIfExists('finance_fixed_assets');
        Schema::dropIfExists('finance_progress_billings');
        Schema::dropIfExists('finance_retentions');
        Schema::dropIfExists('finance_credit_notes');
        Schema::dropIfExists('finance_ledger_entries');
        Schema::dropIfExists('finance_bank_reconciliations');
        Schema::dropIfExists('finance_bank_accounts');
        Schema::dropIfExists('finance_cost_centers');
        Schema::dropIfExists('finance_accounts');
    }
};
