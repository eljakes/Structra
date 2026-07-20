<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'warehouse_id', 'inventory_item_id', 'quantity_on_hand', 'average_cost'];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:2',
            'average_cost' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
