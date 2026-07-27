<?php

namespace App\Providers;

use App\Models\AiInsight;
use App\Models\AssistantQuery;
use App\Models\AutomationRule;
use App\Models\AutomationRuleVersion;
use App\Models\AutomationRun;
use App\Models\AutomationTemplate;
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
use App\Models\FinanceAccount;
use App\Models\FinanceBankAccount;
use App\Models\FinanceBankReconciliation;
use App\Models\FinanceCostCenter;
use App\Models\FinanceCreditNote;
use App\Models\FinanceFixedAsset;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceProgressBilling;
use App\Models\FinanceRetention;
use App\Models\FinanceTaxRule;
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
use App\Models\PortalWorkItem;
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
use App\Models\WorkforceAllocation;
use App\Models\WorkforceApplication;
use App\Models\WorkforceAsset;
use App\Models\WorkforceBenefit;
use App\Models\WorkforceCandidate;
use App\Models\WorkforceCertification;
use App\Models\WorkforceContractor;
use App\Models\WorkforceDocument;
use App\Models\WorkforceExitRecord;
use App\Models\WorkforceInterview;
use App\Models\WorkforceJobVacancy;
use App\Models\WorkforceOnboardingChecklist;
use App\Models\WorkforceOvertimeRequest;
use App\Models\WorkforcePerformanceReview;
use App\Models\WorkforcePpeIssue;
use App\Models\WorkforceSetting;
use App\Models\WorkforceShift;
use App\Models\WorkforceShiftAssignment;
use App\Models\WorkforceTimesheet;
use App\Models\WorkforceTrainingCourse;
use App\Models\WorkforceTrainingRecord;
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
            FinanceAccount::class,
            FinanceBankAccount::class,
            FinanceBankReconciliation::class,
            FinanceLedgerEntry::class,
            FinanceCreditNote::class,
            FinanceRetention::class,
            FinanceProgressBilling::class,
            FinanceCostCenter::class,
            FinanceFixedAsset::class,
            FinanceTaxRule::class,
            EmployeeProfile::class,
            LeaveRequest::class,
            PayrollRun::class,
            Payslip::class,
            WorkforceJobVacancy::class,
            WorkforceCandidate::class,
            WorkforceApplication::class,
            WorkforceInterview::class,
            WorkforceOnboardingChecklist::class,
            WorkforceShift::class,
            WorkforceShiftAssignment::class,
            WorkforceTimesheet::class,
            WorkforceAllocation::class,
            WorkforceOvertimeRequest::class,
            WorkforceBenefit::class,
            WorkforcePerformanceReview::class,
            WorkforceTrainingCourse::class,
            WorkforceTrainingRecord::class,
            WorkforceCertification::class,
            WorkforcePpeIssue::class,
            WorkforceContractor::class,
            WorkforceAsset::class,
            WorkforceDocument::class,
            WorkforceExitRecord::class,
            WorkforceSetting::class,
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
            PortalWorkItem::class,
            ClientApproval::class,
            ConsultantSubmittal::class,
            AiInsight::class,
            PredictiveForecast::class,
            AssistantQuery::class,
            BiDashboard::class,
            BiWidget::class,
            MetricSnapshot::class,
            AutomationRule::class,
            AutomationRuleVersion::class,
            AutomationRun::class,
            AutomationTemplate::class,
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
