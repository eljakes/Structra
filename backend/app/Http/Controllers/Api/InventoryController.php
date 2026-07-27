<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Project;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPerformanceReview;
use App\Models\SupplierPriceCatalog;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        return response()->json([
            'warehouses' => Warehouse::query()->forCompany($companyId)->with(['stocks.item'])->orderBy('code')->get(),
            'items' => InventoryItem::query()->forCompany($companyId)->with('stocks.warehouse')->orderBy('sku')->get(),
            'movements' => StockMovement::query()->forCompany($companyId)->with(['item', 'company'])->latest('moved_at')->limit(80)->get(),
            'supplier_prices' => SupplierPriceCatalog::query()->forCompany($companyId)->with('supplier')->latest()->get(),
            'supplier_reviews' => SupplierPerformanceReview::query()->forCompany($companyId)->with('supplier')->latest('reviewed_at')->get(),
            'reorder_alerts' => InventoryItem::query()
                ->forCompany($companyId)
                ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeWarehouse(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('warehouses')->where('company_id', $companyId)],
            'location' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['nullable', 'integer'],
        ]);

        Branch::query()->forCompany($companyId)->whereKey($data['branch_id'])->firstOrFail();

        $warehouse = Warehouse::query()->create([
            'company_id' => $companyId,
            ...$data,
            'code' => $this->suppliedCode($data['code'] ?? null)
                ?? $this->nextCompanyCode($this->codePrefix($data['name'], 'WAR'), Warehouse::class, 'code', $companyId),
        ]);

        return response()->json(['warehouse' => $warehouse], 201);
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): JsonResponse
    {
        $this->assertWarehouseTenant($request, $warehouse);
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['sometimes', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('warehouses')->where('company_id', $companyId)->ignore($warehouse->id)],
            'location' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['nullable', 'integer'],
        ]);

        if (array_key_exists('branch_id', $data)) {
            Branch::query()->forCompany($companyId)->whereKey($data['branch_id'])->firstOrFail();
        }

        if (array_key_exists('code', $data)) {
            $data['code'] = $this->suppliedCode($data['code'])
                ?? $this->nextCompanyCode($this->codePrefix($data['name'] ?? $warehouse->name, 'WAR'), Warehouse::class, 'code', $companyId);
        }

        $warehouse->update($data);

        return response()->json(['warehouse' => $warehouse->fresh(['stocks.item'])]);
    }

    public function destroyWarehouse(Request $request, Warehouse $warehouse): JsonResponse
    {
        $this->assertWarehouseTenant($request, $warehouse);

        $warehouse->delete();

        return response()->json(['message' => 'Warehouse archived.']);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('inventory_items')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'unit' => ['nullable', 'string', 'max:24'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'average_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $item = InventoryItem::query()->create([
            'company_id' => $companyId,
            'sku' => $this->suppliedCode($data['sku'] ?? null)
                ?? $this->nextCompanyCode($this->codePrefix($data['category'] ?? $data['name'], 'SKU'), InventoryItem::class, 'sku', $companyId),
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            ...collect($data)->except(['sku', 'currency'])->all(),
        ]);

        return response()->json(['item' => $item], 201);
    }

    public function updateItem(Request $request, InventoryItem $item): JsonResponse
    {
        $this->assertInventoryItemTenant($request, $item);
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('inventory_items')->where('company_id', $companyId)->ignore($item->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'unit' => ['nullable', 'string', 'max:24'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'average_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        if (! empty($data['branch_id'])) {
            Branch::query()->forCompany($companyId)->whereKey($data['branch_id'])->firstOrFail();
        }

        if (array_key_exists('sku', $data)) {
            $data['sku'] = $this->suppliedCode($data['sku'])
                ?? $this->nextCompanyCode($this->codePrefix($data['category'] ?? $item->category ?? $data['name'] ?? $item->name, 'SKU'), InventoryItem::class, 'sku', $companyId);
        }

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $item->update($data);

        return response()->json(['item' => $item->fresh('stocks.warehouse')]);
    }

    public function destroyItem(Request $request, InventoryItem $item): JsonResponse
    {
        $this->assertInventoryItemTenant($request, $item);

        $item->update(['status' => 'inactive']);
        $item->delete();

        return response()->json(['message' => 'Inventory item archived.']);
    }

    public function moveStock(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'warehouse_id' => ['required', 'integer'],
            'to_warehouse_id' => ['nullable', 'integer'],
            'inventory_item_id' => ['required', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'purchase_order_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(['receipt', 'issue', 'transfer', 'adjustment', 'return'])],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:4000'],
            'moved_at' => ['nullable', 'date'],
        ]);

        $warehouse = Warehouse::query()->forCompany($companyId)->whereKey($data['warehouse_id'])->firstOrFail();
        $item = InventoryItem::query()->forCompany($companyId)->whereKey($data['inventory_item_id'])->firstOrFail();

        $movement = DB::transaction(function () use ($request, $companyId, $data, $warehouse, $item) {
            $quantity = (float) $data['quantity'];
            $unitCost = (float) ($data['unit_cost'] ?? $item->average_cost);

            $stock = InventoryStock::query()->firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'inventory_item_id' => $item->id],
                ['company_id' => $companyId, 'quantity_on_hand' => 0, 'average_cost' => $unitCost],
            );

            $delta = match ($data['type']) {
                'receipt', 'return' => $quantity,
                'issue' => -$quantity,
                'adjustment' => $quantity,
                'transfer' => -$quantity,
            };

            abort_if((float) $stock->quantity_on_hand + $delta < 0, 422, 'Stock movement would create a negative balance.');

            if ($data['type'] === 'transfer') {
                $toWarehouse = Warehouse::query()->forCompany($companyId)->whereKey($data['to_warehouse_id'])->firstOrFail();
                $toStock = InventoryStock::query()->firstOrCreate(
                    ['warehouse_id' => $toWarehouse->id, 'inventory_item_id' => $item->id],
                    ['company_id' => $companyId, 'quantity_on_hand' => 0, 'average_cost' => $unitCost],
                );
                $toStock->forceFill([
                    'quantity_on_hand' => (float) $toStock->quantity_on_hand + $quantity,
                    'average_cost' => $unitCost,
                ])->save();
            }

            $newBalance = (float) $stock->quantity_on_hand + $delta;
            $stock->forceFill([
                'quantity_on_hand' => $newBalance,
                'average_cost' => $unitCost,
            ])->save();

            $movement = StockMovement::query()->create([
                'company_id' => $companyId,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'to_warehouse_id' => $data['to_warehouse_id'] ?? null,
                'inventory_item_id' => $item->id,
                'project_id' => $data['project_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'movement_number' => $this->nextNumber('STK', StockMovement::class, 'movement_number', $companyId),
                'type' => $data['type'],
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 2),
                'balance_after' => $newBalance,
                'reason' => $data['reason'] ?? null,
                'moved_at' => $data['moved_at'] ?? now(),
                'created_by' => $this->user($request)->id,
            ]);

            $this->syncItemQuantity($item);

            return $movement;
        });

        $item->refresh();
        if ($item->status === 'active' && (float) $item->quantity_on_hand <= (float) $item->reorder_level) {
            $this->publishAutomationEvent($request, 'stock_low', [
                'record_type' => 'inventory_item',
                'record_id' => $item->id,
            ]);
        }

        return response()->json(['movement' => $movement->load('item')], 201);
    }

    public function storeSupplierPrice(Request $request, Supplier $supplier): JsonResponse
    {
        $this->assertSupplierTenant($request, $supplier);

        $data = $request->validate([
            'inventory_item_id' => ['nullable', 'integer'],
            'cost_code' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:24'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
        ]);

        if (! empty($data['inventory_item_id'])) {
            InventoryItem::query()->forCompany($supplier->company_id)->whereKey($data['inventory_item_id'])->firstOrFail();
        }

        $price = SupplierPriceCatalog::query()->create([
            'company_id' => $supplier->company_id,
            'supplier_id' => $supplier->id,
            'cost_code' => $this->suppliedCode($data['cost_code'] ?? null)
                ?? $this->nextCompanyCode($this->codePrefix($data['description'], 'CST'), SupplierPriceCatalog::class, 'cost_code', $supplier->company_id),
            'currency' => strtoupper($data['currency'] ?? $supplier->currency),
            ...collect($data)->except(['cost_code', 'currency'])->all(),
        ]);

        return response()->json(['supplier_price' => $price], 201);
    }

    public function storeSupplierReview(Request $request, Supplier $supplier): JsonResponse
    {
        $this->assertSupplierTenant($request, $supplier);

        $data = $request->validate([
            'project_id' => ['nullable', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'quality_score' => ['nullable', 'integer', 'between:1,5'],
            'delivery_score' => ['nullable', 'integer', 'between:1,5'],
            'cost_score' => ['nullable', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        if (! empty($data['project_id'])) {
            Project::query()->forCompany($supplier->company_id)->whereKey($data['project_id'])->firstOrFail();
        }

        $review = SupplierPerformanceReview::query()->create([
            'company_id' => $supplier->company_id,
            'supplier_id' => $supplier->id,
            'reviewed_by' => $this->user($request)->id,
            'reviewed_at' => now(),
            'quality_score' => $data['quality_score'] ?? $data['rating'],
            'delivery_score' => $data['delivery_score'] ?? $data['rating'],
            'cost_score' => $data['cost_score'] ?? $data['rating'],
            ...$data,
        ]);

        $supplier->forceFill([
            'rating' => (int) round($supplier->performanceReviews()->avg('rating')),
        ])->save();

        return response()->json(['supplier_review' => $review, 'supplier' => $supplier->fresh()], 201);
    }

    private function syncItemQuantity(InventoryItem $item): void
    {
        $stocks = InventoryStock::query()->where('inventory_item_id', $item->id)->get();
        $quantity = $stocks->sum(fn (InventoryStock $stock) => (float) $stock->quantity_on_hand);
        $averageCost = $stocks->where('quantity_on_hand', '>', 0)->avg('average_cost') ?? $item->average_cost;

        $item->forceFill([
            'quantity_on_hand' => $quantity,
            'average_cost' => $averageCost,
        ])->save();
    }

    private function assertSupplierTenant(Request $request, Supplier $supplier): void
    {
        abort_if($supplier->company_id !== $this->companyId($request), 404);
    }

    private function assertWarehouseTenant(Request $request, Warehouse $warehouse): void
    {
        abort_if($warehouse->company_id !== $this->companyId($request), 404);
    }

    private function assertInventoryItemTenant(Request $request, InventoryItem $item): void
    {
        abort_if($item->company_id !== $this->companyId($request), 404);
    }
}
