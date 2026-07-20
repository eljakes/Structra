<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class OrganizationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $company = $this->user($request)->company()
            ->with(['branches', 'roles', 'users.role', 'users.branch'])
            ->firstOrFail();

        return response()->json([
            'company' => $company,
            'clients' => Client::query()->forCompany($companyId)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->forCompany($companyId)->orderBy('name')->get(),
        ]);
    }

    public function updateCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'tax_id' => ['nullable', 'string', 'max:120'],
            'default_currency' => ['sometimes', 'string', 'size:3'],
            'country' => ['sometimes', 'string', 'size:2'],
            'base_timezone' => ['sometimes', 'string', 'max:80'],
        ]);

        $company = $this->user($request)->company;
        $company->update($data);

        return response()->json(['company' => $company->fresh(['branches', 'roles'])]);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:24'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ]);

        $branch = Branch::query()->create([
            'company_id' => $this->companyId($request),
            ...$data,
            'code' => strtoupper($data['code']),
            'country' => strtoupper($data['country'] ?? $this->user($request)->company->country),
        ]);

        return response()->json(['branch' => $branch], 201);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
            'branch_id' => ['required', 'integer'],
            'role_id' => ['required', 'integer'],
            'phone' => ['nullable', 'string', 'max:60'],
            'job_title' => ['nullable', 'string', 'max:120'],
        ]);

        Branch::query()->forCompany($companyId)->whereKey($data['branch_id'])->firstOrFail();
        Role::query()->forCompany($companyId)->whereKey($data['role_id'])->firstOrFail();

        $user = User::query()->create([
            'company_id' => $companyId,
            ...$data,
        ]);

        return response()->json(['user' => $user->load(['branch', 'role'])], 201);
    }

    public function storeClient(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:2000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $client = Client::query()->create([
            'company_id' => $this->companyId($request),
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            ...$data,
        ]);

        return response()->json(['client' => $client], 201);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:2000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $supplier = Supplier::query()->create([
            'company_id' => $this->companyId($request),
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            ...$data,
        ]);

        return response()->json(['supplier' => $supplier], 201);
    }
}
