<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceBankReconciliation extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'finance_bank_account_id', 'statement_date', 'statement_balance',
        'system_balance', 'difference', 'status', 'reconciled_by', 'reconciled_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_balance' => 'decimal:2',
            'system_balance' => 'decimal:2',
            'difference' => 'decimal:2',
            'reconciled_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceBankAccount::class, 'finance_bank_account_id');
    }
}
