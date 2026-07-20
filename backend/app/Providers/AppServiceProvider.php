<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\BudgetLine;
use App\Models\Client;
use App\Models\Document;
use App\Models\Drawing;
use App\Models\DrawingRevision;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Observers\AuditableObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            Branch::class,
            Client::class,
            Project::class,
            ProjectTask::class,
            BudgetLine::class,
            Supplier::class,
            PurchaseRequisition::class,
            PurchaseOrder::class,
            Document::class,
            Drawing::class,
            DrawingRevision::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }
    }
}
