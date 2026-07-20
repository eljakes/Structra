<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_requisitions', 'approval_stage')) {
                $table->string('approval_stage')->nullable()->after('status');
            }

            if (! Schema::hasColumn('purchase_requisitions', 'approval_workflow')) {
                $table->json('approval_workflow')->nullable()->after('approval_stage');
            }

            if (! Schema::hasColumn('purchase_requisitions', 'submitted_at')) {
                $table->timestampTz('submitted_at')->nullable()->after('reviewed_at');
            }
        });

        Schema::table('supplier_quotations', function (Blueprint $table): void {
            if (! Schema::hasColumn('supplier_quotations', 'warranty_included')) {
                $table->boolean('warranty_included')->default(false)->after('payment_terms');
            }

            if (! Schema::hasColumn('supplier_quotations', 'recommendation_score')) {
                $table->unsignedSmallInteger('recommendation_score')->default(0)->after('warranty_included');
            }
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            if (! Schema::hasColumn('goods_receipts', 'delivered_by')) {
                $table->string('delivered_by')->nullable()->after('delivery_note_number');
            }

            if (! Schema::hasColumn('goods_receipts', 'warehouse')) {
                $table->string('warehouse')->nullable()->after('delivered_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropColumn(['delivered_by', 'warehouse']);
        });

        Schema::table('supplier_quotations', function (Blueprint $table): void {
            $table->dropColumn(['warranty_included', 'recommendation_score']);
        });

        Schema::table('purchase_requisitions', function (Blueprint $table): void {
            $table->dropColumn(['approval_stage', 'approval_workflow', 'submitted_at']);
        });
    }
};
