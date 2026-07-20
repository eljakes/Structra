<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'currency',
        'rating',
        'lead_time_days',
        'status',
    ];

    public function priceCatalogs(): HasMany
    {
        return $this->hasMany(SupplierPriceCatalog::class);
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(SupplierPerformanceReview::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(SupplierQuotation::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(SupplierContract::class);
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }
}
