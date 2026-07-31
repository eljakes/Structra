<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $permissions = array_filter(explode('|', $permission));

        $token = $user?->currentAccessToken();
        $isImpersonationToken = $token instanceof PersonalAccessToken && $token->can('impersonation');
        $requiresPlatformAccess = collect($permissions)->contains(
            fn (string $required): bool => str_starts_with($required, 'platform.'),
        );

        if ($isImpersonationToken && $requiresPlatformAccess) {
            abort(403, 'Impersonation sessions cannot access Structra Cloud Console administration.');
        }

        $matchedPermissions = collect($permissions)
            ->filter(fn (string $required): bool => $user?->hasPermission($required))
            ->values();

        if (! $user || $matchedPermissions->isEmpty()) {
            abort(403, 'You do not have permission to perform this action.');
        }

        $allowedByEnabledModule = $matchedPermissions->contains(
            fn (string $permission): bool => $this->moduleIsEnabledForPermission((int) $user->company_id, $permission),
        );

        if (! $allowedByEnabledModule) {
            abort(403, 'This module is not enabled for your company subscription.');
        }

        return $next($request);
    }

    private function moduleIsEnabledForPermission(int $companyId, string $permission): bool
    {
        if ($companyId <= 0 || str_starts_with($permission, 'platform.')) {
            return true;
        }

        $flagKey = $this->moduleFlagForPermission($permission);

        if (! $flagKey) {
            return true;
        }

        $setting = DB::table('company_feature_flags')
            ->join('platform_feature_flags', 'platform_feature_flags.id', '=', 'company_feature_flags.platform_feature_flag_id')
            ->where('company_feature_flags.company_id', $companyId)
            ->where('platform_feature_flags.key', $flagKey)
            ->select('company_feature_flags.is_enabled')
            ->first();

        return ! $setting || (bool) $setting->is_enabled;
    }

    private function moduleFlagForPermission(string $permission): ?string
    {
        return match ($permission) {
            'crm.manage' => 'module.crm',
            'tenders.manage' => 'module.tendering',
            'estimating.manage' => 'module.estimating',
            'projects.manage' => 'module.projects',
            'procurement.manage', 'procurement.approve', 'suppliers.manage' => 'module.procurement',
            'inventory.manage' => 'module.inventory',
            'field.manage', 'attendance.manage' => 'module.field',
            'finance.manage' => 'module.finance',
            'payroll.manage' => 'module.hr',
            'equipment.manage' => 'module.equipment',
            'quality.manage', 'safety.manage' => 'module.qa_hse',
            'portals.manage' => 'module.portals',
            'documents.manage' => 'module.documents',
            'reports.view' => 'module.reports',
            'bi.manage' => 'module.bi',
            'automation.manage' => 'module.automation',
            default => null,
        };
    }
}
