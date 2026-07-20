<?php

namespace App\Http\Controllers\Api;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionLine;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProcurementController extends ApiController
{
    public function requisitions(Request $request): JsonResponse
    {
        $items = PurchaseRequisition::query()
            ->forCompany($this->companyId($request))
            ->with(['project:id,code,name,currency', 'lines'])
            ->when($request->query('project_id'), fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return response()->json($items);
    }

    public function storeRequisition(Request $request, int $project): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'required_by' => ['nullable', 'date'],
            'justification' => ['nullable', 'string', 'max:4000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.supplier_id' => ['nullable', 'integer'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.cost_code' => ['nullable', 'string', 'max:40'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit' => ['nullable', 'string', 'max:24'],
            'lines.*.estimated_unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $requisition = DB::transaction(function () use ($request, $projectModel, $data) {
            $requisition = PurchaseRequisition::query()->create([
                'company_id' => $projectModel->company_id,
                'branch_id' => $projectModel->branch_id,
                'project_id' => $projectModel->id,
                'requisition_number' => $this->nextNumber('REQ', PurchaseRequisition::class, 'requisition_number', $projectModel->company_id),
                'title' => $data['title'],
                'priority' => $data['priority'] ?? 'normal',
                'required_by' => $data['required_by'] ?? null,
                'justification' => $data['justification'] ?? null,
                'requested_by' => $this->user($request)->id,
            ]);

            foreach ($data['lines'] as $line) {
                if (! empty($line['supplier_id'])) {
                    Supplier::query()->forCompany($projectModel->company_id)->whereKey($line['supplier_id'])->firstOrFail();
                }

                $quantity = (float) $line['quantity'];
                $unitCost = (float) $line['estimated_unit_cost'];

                PurchaseRequisitionLine::query()->create([
                    'company_id' => $projectModel->company_id,
                    'purchase_requisition_id' => $requisition->id,
                    'supplier_id' => $line['supplier_id'] ?? null,
                    'description' => $line['description'],
                    'cost_code' => $line['cost_code'] ?? null,
                    'quantity' => $quantity,
                    'unit' => $line['unit'] ?? 'each',
                    'estimated_unit_cost' => $unitCost,
                    'estimated_total' => round($quantity * $unitCost, 2),
                ]);
            }

            $requisition->forceFill([
                'total_estimated' => $requisition->lines()->sum('estimated_total'),
            ])->save();

            return $requisition;
        });

        return response()->json(['requisition' => $requisition->load(['project', 'lines'])], 201);
    }

    public function updateRequisition(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->assertRequisitionTenant($request, $requisition);
        abort_if(! in_array($requisition->status, ['draft', 'rejected'], true), 422, 'Only draft or rejected requisitions can be edited.');

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'required_by' => ['nullable', 'date'],
            'justification' => ['nullable', 'string', 'max:4000'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.supplier_id' => ['nullable', 'integer'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:255'],
            'lines.*.cost_code' => ['nullable', 'string', 'max:40'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0.01'],
            'lines.*.unit' => ['nullable', 'string', 'max:24'],
            'lines.*.estimated_unit_cost' => ['required_with:lines', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($requisition, $data): void {
            $requisition->update(collect($data)->except('lines')->all());

            if (array_key_exists('lines', $data)) {
                $requisition->lines()->delete();

                foreach ($data['lines'] as $line) {
                    $quantity = (float) $line['quantity'];
                    $unitCost = (float) $line['estimated_unit_cost'];

                    PurchaseRequisitionLine::query()->create([
                        'company_id' => $requisition->company_id,
                        'purchase_requisition_id' => $requisition->id,
                        'supplier_id' => $line['supplier_id'] ?? null,
                        'description' => $line['description'],
                        'cost_code' => $line['cost_code'] ?? null,
                        'quantity' => $quantity,
                        'unit' => $line['unit'] ?? 'each',
                        'estimated_unit_cost' => $unitCost,
                        'estimated_total' => round($quantity * $unitCost, 2),
                    ]);
                }

                $requisition->forceFill([
                    'total_estimated' => $requisition->lines()->sum('estimated_total'),
                ])->save();
            }
        });

        return response()->json(['requisition' => $requisition->fresh(['project', 'lines'])]);
    }

    public function submitRequisition(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->assertRequisitionTenant($request, $requisition);
        abort_if(! in_array($requisition->status, ['draft', 'rejected'], true), 422, 'Only draft or rejected requisitions can be submitted.');
        abort_if($requisition->lines()->count() === 0, 422, 'A requisition needs at least one line before submission.');

        $requisition->update(['status' => 'submitted']);

        return response()->json(['requisition' => $requisition->fresh(['project', 'lines'])]);
    }

    public function reviewRequisition(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->assertRequisitionTenant($request, $requisition);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        abort_if($requisition->status !== 'submitted', 422, 'Only submitted requisitions can be reviewed.');

        $requisition->update([
            'status' => $data['decision'],
            'reviewed_by' => $this->user($request)->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['requisition' => $requisition->fresh(['project', 'lines'])]);
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        $items = PurchaseOrder::query()
            ->forCompany($this->companyId($request))
            ->with(['project:id,code,name,currency', 'supplier', 'lines'])
            ->when($request->query('project_id'), fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate((int) $request->query('per_page', 25));

        return response()->json($items);
    }

    public function storePurchaseOrder(Request $request, int $project): JsonResponse
    {
        $projectModel = $this->projectForTenant($request, $project);

        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'purchase_requisition_id' => ['nullable', 'integer'],
            'issue_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'terms' => ['nullable', 'string', 'max:4000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.cost_code' => ['nullable', 'string', 'max:40'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit' => ['nullable', 'string', 'max:24'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $supplier = Supplier::query()->forCompany($projectModel->company_id)->whereKey($data['supplier_id'])->firstOrFail();

        if (! empty($data['purchase_requisition_id'])) {
            PurchaseRequisition::query()
                ->forCompany($projectModel->company_id)
                ->where('project_id', $projectModel->id)
                ->where('status', 'approved')
                ->whereKey($data['purchase_requisition_id'])
                ->firstOrFail();
        }

        $purchaseOrder = DB::transaction(function () use ($request, $projectModel, $supplier, $data) {
            $purchaseOrder = PurchaseOrder::query()->create([
                'company_id' => $projectModel->company_id,
                'branch_id' => $projectModel->branch_id,
                'project_id' => $projectModel->id,
                'supplier_id' => $supplier->id,
                'purchase_requisition_id' => $data['purchase_requisition_id'] ?? null,
                'po_number' => $this->nextNumber('PO', PurchaseOrder::class, 'po_number', $projectModel->company_id),
                'currency' => $projectModel->currency,
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'terms' => $data['terms'] ?? null,
                'created_by' => $this->user($request)->id,
            ]);

            foreach ($data['lines'] as $line) {
                $this->createPurchaseOrderLine($purchaseOrder, $line);
            }

            $this->syncPurchaseOrderTotals($purchaseOrder);

            return $purchaseOrder;
        });

        $this->syncProjectCosts($projectModel);

        return response()->json(['purchase_order' => $purchaseOrder->load(['supplier', 'project', 'lines'])], 201);
    }

    public function convertToPurchaseOrder(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->assertRequisitionTenant($request, $requisition);
        abort_if($requisition->status !== 'approved', 422, 'Only approved requisitions can be converted to purchase orders.');

        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'terms' => ['nullable', 'string', 'max:4000'],
        ]);

        $supplier = Supplier::query()->forCompany($requisition->company_id)->whereKey($data['supplier_id'])->firstOrFail();
        $project = $requisition->project;

        $purchaseOrder = DB::transaction(function () use ($request, $requisition, $project, $supplier, $data) {
            $purchaseOrder = PurchaseOrder::query()->create([
                'company_id' => $project->company_id,
                'branch_id' => $project->branch_id,
                'project_id' => $project->id,
                'supplier_id' => $supplier->id,
                'purchase_requisition_id' => $requisition->id,
                'po_number' => $this->nextNumber('PO', PurchaseOrder::class, 'po_number', $project->company_id),
                'currency' => $project->currency,
                'issue_date' => now()->toDateString(),
                'tax_amount' => $data['tax_amount'] ?? 0,
                'terms' => $data['terms'] ?? null,
                'created_by' => $this->user($request)->id,
            ]);

            foreach ($requisition->lines as $line) {
                $this->createPurchaseOrderLine($purchaseOrder, [
                    'description' => $line->description,
                    'cost_code' => $line->cost_code,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'unit_cost' => $line->estimated_unit_cost,
                ]);
            }

            $this->syncPurchaseOrderTotals($purchaseOrder);
            $requisition->update(['status' => 'converted']);

            return $purchaseOrder;
        });

        $this->syncProjectCosts($project);

        return response()->json(['purchase_order' => $purchaseOrder->load(['supplier', 'project', 'lines'])], 201);
    }

    public function transitionPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->assertPurchaseOrderTenant($request, $purchaseOrder);

        $data = $request->validate([
            'status' => ['required', Rule::in(['issued', 'approved', 'delivered', 'closed', 'cancelled'])],
        ]);

        $allowed = [
            'draft' => ['issued', 'cancelled'],
            'issued' => ['approved', 'cancelled'],
            'approved' => ['delivered', 'cancelled'],
            'delivered' => ['closed'],
            'closed' => [],
            'cancelled' => [],
        ];

        abort_if(! in_array($data['status'], $allowed[$purchaseOrder->status] ?? [], true), 422, 'Invalid purchase order status transition.');

        $updates = ['status' => $data['status']];

        if ($data['status'] === 'approved') {
            $updates['approved_by'] = $this->user($request)->id;
            $updates['approved_at'] = now();
        }

        if ($data['status'] === 'delivered') {
            $updates['delivery_status'] = 'delivered';
        }

        $purchaseOrder->update($updates);
        $this->syncProjectCosts($purchaseOrder->project);

        return response()->json(['purchase_order' => $purchaseOrder->fresh(['supplier', 'project', 'lines'])]);
    }

    private function createPurchaseOrderLine(PurchaseOrder $purchaseOrder, array $line): PurchaseOrderLine
    {
        $quantity = (float) $line['quantity'];
        $unitCost = (float) $line['unit_cost'];

        return PurchaseOrderLine::query()->create([
            'company_id' => $purchaseOrder->company_id,
            'purchase_order_id' => $purchaseOrder->id,
            'description' => $line['description'],
            'cost_code' => $line['cost_code'] ?? null,
            'quantity' => $quantity,
            'unit' => $line['unit'] ?? 'each',
            'unit_cost' => $unitCost,
            'line_total' => round($quantity * $unitCost, 2),
        ]);
    }

    private function assertRequisitionTenant(Request $request, PurchaseRequisition $requisition): void
    {
        abort_if($requisition->company_id !== $this->companyId($request), 404);
    }

    private function assertPurchaseOrderTenant(Request $request, PurchaseOrder $purchaseOrder): void
    {
        abort_if($purchaseOrder->company_id !== $this->companyId($request), 404);
    }
}
