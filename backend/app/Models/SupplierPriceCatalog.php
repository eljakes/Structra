<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPriceCatalog extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'supplier_id', 'inventory_item_id', 'cost_code', 'description', 'unit',
        'unit_price', 'currency', 'lead_time_days', 'valid_from', 'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
