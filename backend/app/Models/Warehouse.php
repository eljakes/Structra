<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = ['company_id', 'branch_id', 'manager_id', 'name', 'code', 'location'];

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }
}
