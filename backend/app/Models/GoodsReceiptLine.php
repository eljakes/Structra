<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'goods_receipt_id', 'purchase_order_line_id', 'description',
        'ordered_quantity', 'received_quantity', 'accepted_quantity', 'rejected_quantity',
        'unit', 'condition', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'ordered_quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'accepted_quantity' => 'decimal:2',
            'rejected_quantity' => 'decimal:2',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
