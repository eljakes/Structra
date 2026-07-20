<?php

namespace App\Providers;

use App\Models\AiInsight;
use App\Models\AssistantQuery;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\BiDashboard;
use App\Models\BiWidget;
use App\Models\Branch;
use App\Models\BudgetLine;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\CompanyLocalizationSetting;
use App\Models\ConsultantSubmittal;
use App\Models\Document;
use App\Models\Drawing;
use App\Models\DrawingMarkup;
use App\Models\DrawingReview;
use App\Models\DrawingRevision;
use App\Models\EmployeeProfile;
use App\Models\EquipmentAsset;
use App\Models\EquipmentAssignment;
use App\Models\Estimate;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\FieldDailyReport;
use App\Models\FieldIssue;
use App\Models\FuelLog;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\IntegrationConnector;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\MaintenanceLog;
use App\Models\MetricSnapshot;
use App\Models\NonConformanceReport;
use App\Models\Opportunity;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PortalAccess;
use App\Models\PortalUser;
use App\Models\PredictiveForecast;
use App\Models\PricingItem;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\SafetyIncident;
use App\Models\SafetyObservation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPerformanceReview;
use App\Models\SupplierPriceCatalog;
use App\Models\TaxRate;
use App\Models\Tender;
use App\Models\ToolboxTalk;
use App\Models\Warehouse;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Models\WorkPermit;
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
            Lead::class,
            Opportunity::class,
            Tender::class,
            PricingItem::class,
            Estimate::class,
            Warehouse::class,
            InventoryItem::class,
            StockMovement::class,
            SupplierPriceCatalog::class,
            SupplierPerformanceReview::class,
            FieldDailyReport::class,
            FieldIssue::class,
            DrawingMarkup::class,
            DrawingReview::class,
            Invoice::class,
            InvoiceLine::class,
            Payment::class,
            Expense::class,
            JournalEntry::class,
            JournalLine::class,
            EmployeeProfile::class,
            LeaveRequest::class,
            PayrollRun::class,
            Payslip::class,
            EquipmentAsset::class,
            EquipmentAssignment::class,
            MaintenanceLog::class,
            FuelLog::class,
            Inspection::class,
            InspectionItem::class,
            NonConformanceReport::class,
            SafetyIncident::class,
            ToolboxTalk::class,
            SafetyObservation::class,
            WorkPermit::class,
            PortalUser::class,
            PortalAccess::class,
            ClientApproval::class,
            ConsultantSubmittal::class,
            AiInsight::class,
            PredictiveForecast::class,
            AssistantQuery::class,
            BiDashboard::class,
            BiWidget::class,
            MetricSnapshot::class,
            AutomationRule::class,
            AutomationRun::class,
            IntegrationConnector::class,
            WebhookSubscription::class,
            WebhookDelivery::class,
            CompanyLocalizationSetting::class,
            TaxRate::class,
            ExchangeRate::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }
    }
}
