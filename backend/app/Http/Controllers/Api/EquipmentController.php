<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\EquipmentAsset;
use App\Models\EquipmentAssignment;
use App\Models\FuelLog;
use App\Models\MaintenanceLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EquipmentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        return response()->json([
            'assets' => EquipmentAsset::query()
                ->forCompany($companyId)
                ->with(['branch:id,name,code', 'currentProject:id,code,name', 'assignments.project:id,code,name', 'maintenanceLogs', 'fuelLogs'])
                ->orderBy('equipment_number')
                ->get(),
            'assignments' => EquipmentAssignment::query()->forCompany($companyId)->with(['asset', 'project:id,code,name'])->latest('starts_at')->limit(100)->get(),
            'maintenance' => MaintenanceLog::query()->forCompany($companyId)->with('asset')->latest('service_date')->limit(100)->get(),
            'fuel_logs' => FuelLog::query()->forCompany($companyId)->with(['asset', 'project:id,code,name'])->latest('fuel_date')->limit(100)->get(),
            'summary' => [
                'available' => EquipmentAsset::query()->forCompany($companyId)->where('status', 'available')->count(),
                'assigned' => EquipmentAsset::query()->forCompany($companyId)->where('status', 'assigned')->count(),
                'maintenance' => EquipmentAsset::query()->forCompany($companyId)->where('status', 'maintenance')->count(),
                'fuel_cost' => (float) FuelLog::query()->forCompany($companyId)->sum('total_cost'),
            ],
        ]);
    }

    public function storeAsset(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'make' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'ownership_type' => ['nullable', Rule::in(['owned', 'leased', 'rented'])],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
            'next_service_due_on' => ['nullable', 'date'],
            'next_service_meter' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $branchId = $data['branch_id'] ?? $this->user($request)->branch_id;
        Branch::query()->forCompany($companyId)->whereKey($branchId)->firstOrFail();

        $asset = EquipmentAsset::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'equipment_number' => $this->nextNumber('EQ', EquipmentAsset::class, 'equipment_number', $companyId),
            'category' => $data['category'] ?? 'plant',
            'ownership_type' => $data['ownership_type'] ?? 'owned',
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            'status' => 'available',
            ...collect($data)->except(['branch_id', 'category', 'ownership_type', 'currency'])->all(),
        ]);

        return response()->json(['asset' => $asset->load('branch')], 201);
    }

    public function assignAsset(Request $request, EquipmentAsset $asset): JsonResponse
    {
        $this->assertTenant($request, $asset);

        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'meter_start' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_if(in_array($asset->status, ['maintenance', 'retired'], true), 422, 'Equipment is not available for assignment.');
        abort_if($asset->assignments()->where('status', 'active')->exists(), 422, 'Equipment already has an active assignment.');

        $project = Project::query()->forCompany($asset->company_id)->whereKey($data['project_id'])->firstOrFail();

        if (! empty($data['assigned_to'])) {
            User::query()->where('company_id', $asset->company_id)->whereKey($data['assigned_to'])->firstOrFail();
        }

        $assignment = DB::transaction(function () use ($request, $asset, $project, $data) {
            $assignment = EquipmentAssignment::query()->create([
                'company_id' => $asset->company_id,
                'equipment_asset_id' => $asset->id,
                'project_id' => $project->id,
                'assigned_to' => $data['assigned_to'] ?? null,
                'assignment_number' => $this->nextNumber('EQA', EquipmentAssignment::class, 'assignment_number', $asset->company_id),
                'status' => 'active',
                'starts_at' => $data['starts_at'] ?? now(),
                'ends_at' => $data['ends_at'] ?? null,
                'meter_start' => $data['meter_start'] ?? $asset->meter_reading,
                'notes' => $data['notes'] ?? null,
                'created_by' => $this->user($request)->id,
            ]);

            $asset->update([
                'status' => 'assigned',
                'current_project_id' => $project->id,
                'meter_reading' => $data['meter_start'] ?? $asset->meter_reading,
            ]);

            return $assignment;
        });

        return response()->json(['assignment' => $assignment->load(['asset', 'project']), 'asset' => $asset->fresh(['currentProject'])], 201);
    }

    public function releaseAssignment(Request $request, EquipmentAssignment $assignment): JsonResponse
    {
        $this->assertTenant($request, $assignment);
        abort_if($assignment->status !== 'active', 422, 'Only active assignments can be released.');

        $data = $request->validate([
            'ends_at' => ['nullable', 'date'],
            'meter_end' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($assignment, $data) {
            $assignment->update([
                'status' => 'completed',
                'ends_at' => $data['ends_at'] ?? now(),
                'meter_end' => $data['meter_end'] ?? null,
                'notes' => $data['notes'] ?? $assignment->notes,
            ]);

            $assignment->asset()->update([
                'status' => 'available',
                'current_project_id' => null,
                'meter_reading' => $data['meter_end'] ?? $assignment->asset->meter_reading,
            ]);
        });

        return response()->json(['assignment' => $assignment->fresh(['asset', 'project'])]);
    }

    public function storeMaintenance(Request $request, EquipmentAsset $asset): JsonResponse
    {
        $this->assertTenant($request, $asset);

        $data = $request->validate([
            'type' => ['nullable', Rule::in(['preventive', 'corrective', 'inspection', 'breakdown'])],
            'status' => ['nullable', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled'])],
            'service_date' => ['required', 'date'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'next_service_due_on' => ['nullable', 'date'],
        ]);

        $status = $data['status'] ?? 'scheduled';

        $maintenance = DB::transaction(function () use ($request, $asset, $data, $status) {
            $maintenance = MaintenanceLog::query()->create([
                'company_id' => $asset->company_id,
                'equipment_asset_id' => $asset->id,
                'maintenance_number' => $this->nextNumber('MTN', MaintenanceLog::class, 'maintenance_number', $asset->company_id),
                'type' => $data['type'] ?? 'preventive',
                'status' => $status,
                'service_date' => $data['service_date'],
                'completed_at' => $status === 'completed' ? now() : null,
                'meter_reading' => $data['meter_reading'] ?? $asset->meter_reading,
                'cost_amount' => $data['cost_amount'] ?? 0,
                'vendor' => $data['vendor'] ?? null,
                'description' => $data['description'] ?? null,
                'next_service_due_on' => $data['next_service_due_on'] ?? null,
                'performed_by' => $status === 'completed' ? $this->user($request)->id : null,
                'created_by' => $this->user($request)->id,
            ]);

            $asset->update([
                'status' => $status === 'completed' ? 'available' : 'maintenance',
                'meter_reading' => $data['meter_reading'] ?? $asset->meter_reading,
                'next_service_due_on' => $data['next_service_due_on'] ?? $asset->next_service_due_on,
            ]);

            return $maintenance;
        });

        return response()->json(['maintenance' => $maintenance->load('asset'), 'asset' => $asset->fresh()], 201);
    }

    public function storeFuelLog(Request $request, EquipmentAsset $asset): JsonResponse
    {
        $this->assertTenant($request, $asset);

        $data = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'fuel_date' => ['nullable', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:24'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($data['project_id'])) {
            Project::query()->forCompany($asset->company_id)->whereKey($data['project_id'])->firstOrFail();
        }

        $quantity = (float) $data['quantity'];
        $unitCost = (float) ($data['unit_cost'] ?? 0);

        $fuelLog = DB::transaction(function () use ($request, $asset, $data, $quantity, $unitCost) {
            $fuelLog = FuelLog::query()->create([
                'company_id' => $asset->company_id,
                'equipment_asset_id' => $asset->id,
                'project_id' => $data['project_id'] ?? $asset->current_project_id,
                'fuel_number' => $this->nextNumber('FUEL', FuelLog::class, 'fuel_number', $asset->company_id),
                'fuel_date' => $data['fuel_date'] ?? now()->toDateString(),
                'quantity' => $quantity,
                'unit' => $data['unit'] ?? 'litre',
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 2),
                'meter_reading' => $data['meter_reading'] ?? null,
                'recorded_by' => $this->user($request)->id,
                'notes' => $data['notes'] ?? null,
            ]);

            if (! empty($data['meter_reading'])) {
                $asset->update(['meter_reading' => max((float) $asset->meter_reading, (float) $data['meter_reading'])]);
            }

            return $fuelLog;
        });

        return response()->json(['fuel_log' => $fuelLog->load(['asset', 'project'])], 201);
    }

    private function assertTenant(Request $request, object $model): void
    {
        abort_if((int) $model->company_id !== $this->companyId($request), 404);
    }
}
