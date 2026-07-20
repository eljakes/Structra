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
            'code' => ['required', 'string', 'max:32'],
            'location' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['nullable', 'integer'],
        ]);

        Branch::query()->forCompany($companyId)->whereKey($data['branch_id'])->firstOrFail();

        $warehouse = Warehouse::query()->create([
            'company_id' => $companyId,
            ...$data,
            'code' => strtoupper($data['code']),
        ]);

        return response()->json(['warehouse' => $warehouse], 201);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'sku' => ['required', 'string', 'max:64', Rule::unique('inventory_items')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'unit' => ['nullable', 'string', 'max:24'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'average_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $item = InventoryItem::query()->create([
            'company_id' => $companyId,
            'sku' => strtoupper($data['sku']),
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            ...$data,
        ]);

        return response()->json(['item' => $item], 201);
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
            'currency' => strtoupper($data['currency'] ?? $supplier->currency),
            ...$data,
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
}
