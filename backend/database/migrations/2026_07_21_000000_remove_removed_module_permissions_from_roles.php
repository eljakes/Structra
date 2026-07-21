<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $removedPermissions = ['intelligence.manage', 'integrations.manage', 'localization.manage'];

        DB::table('roles')
            ->select(['id', 'permissions'])
            ->orderBy('id')
            ->get()
            ->each(function (object $role) use ($removedPermissions): void {
                $permissions = json_decode($role->permissions ?? '[]', true);

                if (! is_array($permissions) || in_array('*', $permissions, true)) {
                    return;
                }

                $filtered = array_values(array_diff($permissions, $removedPermissions));

                if ($filtered === $permissions) {
                    return;
                }

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode($filtered),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        //
    }
};
