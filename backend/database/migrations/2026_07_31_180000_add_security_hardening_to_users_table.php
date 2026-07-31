<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'failed_login_attempts')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedSmallInteger('failed_login_attempts')->default(0)->after('last_login_at');
            });
        }

        if (! Schema::hasColumn('users', 'locked_until')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestampTz('locked_until')->nullable()->after('failed_login_attempts');
            });
        }

        if (! Schema::hasColumn('users', 'last_login_ip')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('last_login_ip', 64)->nullable()->after('locked_until');
            });
        }

        if (! Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestampTz('password_changed_at')->nullable()->after('last_login_ip');
            });
        }

        if (! Schema::hasColumn('users', 'mfa_secret')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->text('mfa_secret')->nullable()->after('password_changed_at');
            });
        }

        if (! Schema::hasColumn('users', 'mfa_enabled_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestampTz('mfa_enabled_at')->nullable()->after('mfa_secret');
            });
        }

        if (! Schema::hasColumn('users', 'mfa_recovery_codes')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('mfa_recovery_codes')->nullable()->after('mfa_enabled_at');
            });
        }

        if (! Schema::hasColumn('users', 'mfa_last_used_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestampTz('mfa_last_used_at')->nullable()->after('mfa_recovery_codes');
            });
        }
    }

    public function down(): void
    {
        $columns = [
            'mfa_last_used_at',
            'mfa_recovery_codes',
            'mfa_enabled_at',
            'mfa_secret',
            'password_changed_at',
            'last_login_ip',
            'locked_until',
            'failed_login_attempts',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
