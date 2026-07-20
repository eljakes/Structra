<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'sku', 'name', 'category', 'unit', 'reorder_level',
        'currency', 'average_cost', 'quantity_on_hand', 'status',
    ];

    protected function casts(): array
    {
        return [
            'reorder_level' => 'decimal:2',
            'average_cost' => 'decimal:2',
            'quantity_on_hand' => 'decimal:2',
        ];
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }
}
