<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);

        $payload = DB::transaction(function () use ($data) {
            $company = Company::query()->create([
                'name' => $data['company_name'],
                'country' => strtoupper($data['country'] ?? 'GH'),
                'default_currency' => strtoupper($data['currency'] ?? 'GHS'),
            ]);

            $branch = Branch::query()->create([
                'company_id' => $company->id,
                'name' => $data['branch_name'] ?? 'Head Office',
                'code' => 'HQ',
                'country' => $company->country,
            ]);

            $ownerRole = Role::query()->create([
                'company_id' => $company->id,
                'name' => 'CEO',
                'slug' => 'owner',
                'permissions' => ['*'],
                'is_system' => true,
            ]);

            foreach ($this->defaultRoles() as $role) {
                Role::query()->create([
                    'company_id' => $company->id,
                    ...$role,
                ]);
            }

            $user = User::query()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'role_id' => $ownerRole->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'job_title' => 'Managing Director',
                'password' => $data['password'],
                'last_login_at' => now(),
            ]);

            return [$company, $branch, $user];
        });

        [, , $user] = $payload;

        return response()->json([
            'token' => $user->createToken('structra-web')->plainTextToken,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->with(['company', 'branch', 'role'])
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This user account is not active.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $user->createToken('structra-web')->plainTextToken,
            'user' => $this->userPayload($user->fresh(['company', 'branch', 'role'])),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($this->user($request)->load(['company.branches', 'branch', 'role'])),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing(['company.branches', 'branch', 'role']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->job_title,
            'status' => $user->status,
            'company' => $user->company,
            'branch' => $user->branch,
            'role' => $user->role,
            'permissions' => $user->accessPermissions(),
            'effective_permissions' => $user->accessPermissions(),
        ];
    }

    private function defaultRoles(): array
    {
        return [
            [
                'name' => 'Project Director',
                'slug' => 'project-director',
                'permissions' => ['projects.manage', 'procurement.approve', 'documents.manage', 'field.manage', 'attendance.manage', 'equipment.manage', 'quality.manage', 'safety.manage', 'portals.manage', 'intelligence.manage', 'bi.manage', 'automation.manage', 'reports.view'],
                'is_system' => true,
            ],
            [
                'name' => 'Procurement Manager',
                'slug' => 'procurement-manager',
                'permissions' => ['procurement.manage', 'inventory.manage', 'suppliers.manage', 'equipment.manage', 'documents.manage', 'reports.view'],
                'is_system' => true,
            ],
            [
                'name' => 'Site Engineer',
                'slug' => 'site-engineer',
                'permissions' => ['projects.manage', 'documents.manage', 'field.manage', 'attendance.manage', 'inventory.manage', 'equipment.manage', 'quality.manage', 'safety.manage', 'reports.view'],
                'is_system' => true,
            ],
            [
                'name' => 'Finance',
                'slug' => 'finance',
                'permissions' => ['finance.manage', 'payroll.manage', 'intelligence.manage', 'bi.manage', 'integrations.manage', 'localization.manage', 'reports.view', 'procurement.approve'],
                'is_system' => true,
            ],
            [
                'name' => 'HR',
                'slug' => 'hr',
                'permissions' => ['payroll.manage', 'reports.view'],
                'is_system' => true,
            ],
            [
                'name' => 'Architect',
                'slug' => 'architect',
                'permissions' => ['documents.manage', 'reports.view'],
                'is_system' => true,
            ],
            [
                'name' => 'Sales & Estimating',
                'slug' => 'sales-estimating',
                'permissions' => ['crm.manage', 'tenders.manage', 'estimating.manage', 'reports.view'],
                'is_system' => true,
            ],
            [
                'name' => 'QHSE Manager',
                'slug' => 'qhse-manager',
                'permissions' => ['quality.manage', 'safety.manage', 'field.manage', 'documents.manage', 'intelligence.manage', 'bi.manage', 'automation.manage', 'reports.view'],
                'is_system' => true,
            ],
        ];
    }
}
