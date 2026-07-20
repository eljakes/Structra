<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'branch_id', 'warehouse_id', 'to_warehouse_id', 'inventory_item_id',
        'project_id', 'purchase_order_id', 'movement_number', 'type', 'quantity', 'unit_cost',
        'total_cost', 'balance_after', 'reason', 'moved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'moved_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
