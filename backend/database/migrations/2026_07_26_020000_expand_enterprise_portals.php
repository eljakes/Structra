<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_accesses', function (Blueprint $table): void {
            if (! Schema::hasColumn('portal_accesses', 'access_scope')) {
                $table->string('access_scope')->default('project')->after('access_level');
            }

            if (! Schema::hasColumn('portal_accesses', 'features')) {
                $table->json('features')->nullable()->after('disciplines');
            }
        });

        Schema::create('portal_work_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('drawing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('portal_type', 40);
            $table->string('item_type', 80);
            $table->string('item_number', 48);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('submitted');
            $table->string('priority')->default('medium');
            $table->date('due_date')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('response')->nullable();
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['company_id', 'item_number']);
            $table->index(['company_id', 'portal_type', 'status']);
            $table->index(['company_id', 'project_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_work_items');

        Schema::table('portal_accesses', function (Blueprint $table): void {
            foreach (['access_scope', 'features'] as $column) {
                if (Schema::hasColumn('portal_accesses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
