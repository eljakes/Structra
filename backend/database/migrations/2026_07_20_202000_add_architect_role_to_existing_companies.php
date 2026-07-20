<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = json_encode(['documents.manage', 'reports.view']);

        DB::table('companies')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $company) use ($now, $permissions): void {
                $exists = DB::table('roles')
                    ->where('company_id', $company->id)
                    ->where('slug', 'architect')
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('roles')->insert([
                    'company_id' => $company->id,
                    'name' => 'Architect',
                    'slug' => 'architect',
                    'permissions' => $permissions,
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('slug', 'architect')
            ->where('is_system', true)
            ->delete();
    }
};
