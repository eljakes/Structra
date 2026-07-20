<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_conformance_reports', function (Blueprint $table) {
            $table->string('department')->nullable()->after('description');
            $table->string('category')->nullable()->after('department');
            $table->string('location')->nullable()->after('category');
            $table->string('contractor')->nullable()->after('location');
            $table->string('subcontractor')->nullable()->after('contractor');
            $table->json('reference_documents')->nullable()->after('subcontractor');
            $table->json('evidence')->nullable()->after('reference_documents');
            $table->text('preventive_action')->nullable()->after('corrective_action');
            $table->text('verification_notes')->nullable()->after('preventive_action');
            $table->timestampTz('verified_at')->nullable()->after('closed_at');
            $table->timestampTz('reopened_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('non_conformance_reports', function (Blueprint $table) {
            $table->dropColumn([
                'department',
                'category',
                'location',
                'contractor',
                'subcontractor',
                'reference_documents',
                'evidence',
                'preventive_action',
                'verification_notes',
                'verified_at',
                'reopened_at',
            ]);
        });
    }
};
