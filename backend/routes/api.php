<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\BudgetLineController;
use App\Http\Controllers\Api\BusinessIntelligenceController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DrawingController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\FieldController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\IntelligenceController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LocalizationController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\PeopleController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\Api\ProcurementController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTaskController;
use App\Http\Controllers\Api\SalesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:reports.view');
        Route::get('reports', [DashboardController::class, 'reports'])->middleware('permission:reports.view');
        Route::get('audit-logs', [DashboardController::class, 'auditLogs'])->middleware('permission:reports.view');

        Route::get('organization', [OrganizationController::class, 'index']);
        Route::patch('organization/company', [OrganizationController::class, 'updateCompany'])->middleware('permission:settings.manage');
        Route::delete('organization/company', [OrganizationController::class, 'destroyCompany'])->middleware('permission:settings.manage');
        Route::post('organization/branches', [OrganizationController::class, 'storeBranch'])->middleware('permission:settings.manage');
        Route::post('organization/users', [OrganizationController::class, 'storeUser'])->middleware('permission:settings.manage');
        Route::patch('organization/users/{user}', [OrganizationController::class, 'updateUser'])->middleware('permission:settings.manage');
        Route::delete('organization/users/{user}', [OrganizationController::class, 'destroyUser'])->middleware('permission:settings.manage');
        Route::post('organization/clients', [OrganizationController::class, 'storeClient'])->middleware('permission:projects.manage');
        Route::patch('organization/clients/{client}', [OrganizationController::class, 'updateClient'])->middleware('permission:settings.manage');
        Route::delete('organization/clients/{client}', [OrganizationController::class, 'destroyClient'])->middleware('permission:settings.manage');
        Route::post('organization/suppliers', [OrganizationController::class, 'storeSupplier'])->middleware('permission:procurement.manage');
        Route::patch('organization/suppliers/{supplier}', [OrganizationController::class, 'updateSupplier'])->middleware('permission:settings.manage');
        Route::delete('organization/suppliers/{supplier}', [OrganizationController::class, 'destroySupplier'])->middleware('permission:settings.manage');

        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store'])->middleware('permission:projects.manage');
        Route::get('projects/timeline', [ProjectController::class, 'timeline']);
        Route::get('projects/{project}', [ProjectController::class, 'show']);
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware('permission:settings.manage');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('permission:settings.manage');

        Route::post('projects/{project}/tasks', [ProjectTaskController::class, 'store'])->middleware('permission:projects.manage');
        Route::patch('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'update'])->middleware('permission:projects.manage');
        Route::delete('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'destroy'])->middleware('permission:projects.manage');

        Route::post('projects/{project}/budget-lines', [BudgetLineController::class, 'store'])->middleware('permission:projects.manage');
        Route::patch('projects/{project}/budget-lines/{budgetLine}', [BudgetLineController::class, 'update'])->middleware('permission:projects.manage');
        Route::delete('projects/{project}/budget-lines/{budgetLine}', [BudgetLineController::class, 'destroy'])->middleware('permission:projects.manage');

        Route::get('procurement', [ProcurementController::class, 'index'])->middleware('permission:procurement.manage');
        Route::get('procurement/requisitions', [ProcurementController::class, 'requisitions']);
        Route::post('projects/{project}/requisitions', [ProcurementController::class, 'storeRequisition'])->middleware('permission:procurement.manage');
        Route::patch('procurement/requisitions/{requisition}', [ProcurementController::class, 'updateRequisition'])->middleware('permission:procurement.manage');
        Route::post('procurement/requisitions/{requisition}/submit', [ProcurementController::class, 'submitRequisition'])->middleware('permission:procurement.manage');
        Route::post('procurement/requisitions/{requisition}/review', [ProcurementController::class, 'reviewRequisition'])->middleware('permission:procurement.approve');
        Route::post('procurement/requisitions/{requisition}/convert-to-po', [ProcurementController::class, 'convertToPurchaseOrder'])->middleware('permission:procurement.manage');
        Route::post('procurement/requisitions/{requisition}/rfqs', [ProcurementController::class, 'storeRfq'])->middleware('permission:procurement.manage');
        Route::post('procurement/rfqs/{rfq}/quotations', [ProcurementController::class, 'storeSupplierQuotation'])->middleware('permission:procurement.manage');
        Route::post('procurement/quotations/{quotation}/accept', [ProcurementController::class, 'acceptQuotation'])->middleware('permission:procurement.approve');
        Route::post('procurement/quotations/{quotation}/purchase-order', [ProcurementController::class, 'createPurchaseOrderFromQuotation'])->middleware('permission:procurement.manage');

        Route::get('procurement/purchase-orders', [ProcurementController::class, 'purchaseOrders']);
        Route::post('projects/{project}/purchase-orders', [ProcurementController::class, 'storePurchaseOrder'])->middleware('permission:procurement.manage');
        Route::post('procurement/purchase-orders/{purchaseOrder}/transition', [ProcurementController::class, 'transitionPurchaseOrder'])->middleware('permission:procurement.manage');
        Route::post('procurement/purchase-orders/{purchaseOrder}/goods-receipts', [ProcurementController::class, 'storeGoodsReceipt'])->middleware('permission:procurement.manage');
        Route::post('procurement/purchase-orders/{purchaseOrder}/supplier-invoices', [ProcurementController::class, 'storeSupplierInvoice'])->middleware('permission:procurement.manage');
        Route::post('procurement/goods-receipts/{goodsReceipt}/quality-inspections', [ProcurementController::class, 'storeQualityInspection'])->middleware('permission:quality.manage');
        Route::post('procurement/supplier-invoices/{invoice}/approve', [ProcurementController::class, 'approveSupplierInvoice'])->middleware('permission:finance.manage');
        Route::post('procurement/supplier-invoices/{invoice}/payments', [ProcurementController::class, 'recordSupplierPayment'])->middleware('permission:finance.manage');
        Route::post('suppliers/{supplier}/contracts', [ProcurementController::class, 'storeSupplierContract'])->middleware('permission:procurement.manage');

        Route::get('documents', [DocumentController::class, 'index']);
        Route::post('documents', [DocumentController::class, 'store'])->middleware('permission:documents.manage');
        Route::patch('documents/{document}', [DocumentController::class, 'update'])->middleware('permission:documents.manage');
        Route::get('documents/{document}/download', [DocumentController::class, 'download']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->middleware('permission:documents.manage');

        Route::get('drawings', [DrawingController::class, 'index']);
        Route::post('drawings', [DrawingController::class, 'store'])->middleware('permission:documents.manage');
        Route::post('drawings/{drawing}/revisions', [DrawingController::class, 'revise'])->middleware('permission:documents.manage');
        Route::post('drawings/{drawing}/transition', [DrawingController::class, 'transition'])->middleware('permission:documents.manage');
        Route::post('drawings/{drawing}/markups', [DrawingController::class, 'storeMarkup'])->middleware('permission:documents.manage');
        Route::post('drawing-markups/{markup}/resolve', [DrawingController::class, 'resolveMarkup'])->middleware('permission:documents.manage');
        Route::post('drawings/{drawing}/reviews', [DrawingController::class, 'storeReview'])->middleware('permission:documents.manage');
        Route::get('drawing-revisions/{revision}/download', [DrawingController::class, 'downloadRevision']);

        Route::get('sales', [SalesController::class, 'index'])->middleware('permission:crm.manage|tenders.manage|estimating.manage');
        Route::post('sales/leads', [SalesController::class, 'storeLead'])->middleware('permission:crm.manage');
        Route::patch('sales/leads/{lead}', [SalesController::class, 'updateLead'])->middleware('permission:crm.manage');
        Route::post('sales/leads/{lead}/qualify', [SalesController::class, 'qualifyLead'])->middleware('permission:crm.manage');
        Route::post('sales/opportunities', [SalesController::class, 'storeOpportunity'])->middleware('permission:crm.manage');
        Route::post('sales/opportunities/{opportunity}/tenders', [SalesController::class, 'createTenderFromOpportunity'])->middleware('permission:tenders.manage');
        Route::patch('sales/tenders/{tender}', [SalesController::class, 'updateTender'])->middleware('permission:tenders.manage');
        Route::post('sales/tenders/{tender}/submit', [SalesController::class, 'submitTender'])->middleware('permission:tenders.manage');
        Route::post('sales/tenders/{tender}/win', [SalesController::class, 'winTender'])->middleware('permission:tenders.manage');
        Route::post('sales/tenders/{tender}/lose', [SalesController::class, 'loseTender'])->middleware('permission:tenders.manage');
        Route::post('sales/tenders/{tender}/rfis', [SalesController::class, 'storeTenderRfi'])->middleware('permission:tenders.manage');
        Route::post('sales/tender-rfis/{rfi}/respond', [SalesController::class, 'respondTenderRfi'])->middleware('permission:tenders.manage');
        Route::post('sales/tenders/{tender}/documents', [SalesController::class, 'uploadTenderDocument'])->middleware('permission:tenders.manage');
        Route::get('sales/tender-documents/{document}/download', [SalesController::class, 'downloadTenderDocument'])->middleware('permission:tenders.manage');
        Route::post('sales/pricing-items', [SalesController::class, 'storePricingItem'])->middleware('permission:estimating.manage');
        Route::post('sales/estimates', [SalesController::class, 'storeEstimate'])->middleware('permission:estimating.manage');
        Route::post('sales/estimates/{estimate}/lines', [SalesController::class, 'addEstimateLine'])->middleware('permission:estimating.manage');
        Route::post('sales/estimates/{estimate}/approve', [SalesController::class, 'approveEstimate'])->middleware('permission:estimating.manage');

        Route::get('inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.manage');
        Route::post('inventory/warehouses', [InventoryController::class, 'storeWarehouse'])->middleware('permission:inventory.manage');
        Route::post('inventory/items', [InventoryController::class, 'storeItem'])->middleware('permission:inventory.manage');
        Route::post('inventory/movements', [InventoryController::class, 'moveStock'])->middleware('permission:inventory.manage');
        Route::post('suppliers/{supplier}/prices', [InventoryController::class, 'storeSupplierPrice'])->middleware('permission:suppliers.manage');
        Route::post('suppliers/{supplier}/reviews', [InventoryController::class, 'storeSupplierReview'])->middleware('permission:suppliers.manage');

        Route::get('field', [FieldController::class, 'index'])->middleware('permission:field.manage');
        Route::post('projects/{project}/daily-reports', [FieldController::class, 'storeDailyReport'])->middleware('permission:field.manage');
        Route::post('field/daily-reports/{dailyReport}/transition', [FieldController::class, 'transitionDailyReport'])->middleware('permission:field.manage');
        Route::post('projects/{project}/field-issues', [FieldController::class, 'storeIssue'])->middleware('permission:field.manage');
        Route::patch('field/issues/{issue}', [FieldController::class, 'updateIssue'])->middleware('permission:field.manage');
        Route::get('field/issues/{issue}/photo', [FieldController::class, 'downloadIssuePhoto'])->middleware('permission:field.manage');
        Route::post('attendance/clock-in', [FieldController::class, 'clockIn'])->middleware('permission:attendance.manage');
        Route::post('attendance/{attendance}/clock-out', [FieldController::class, 'clockOut'])->middleware('permission:attendance.manage');

        Route::get('finance', [FinanceController::class, 'index'])->middleware('permission:finance.manage');
        Route::post('finance/invoices', [FinanceController::class, 'storeInvoice'])->middleware('permission:finance.manage');
        Route::post('finance/invoices/{invoice}/lines', [FinanceController::class, 'addInvoiceLine'])->middleware('permission:finance.manage');
        Route::post('finance/invoices/{invoice}/issue', [FinanceController::class, 'issueInvoice'])->middleware('permission:finance.manage');
        Route::post('finance/invoices/{invoice}/payments', [FinanceController::class, 'recordPayment'])->middleware('permission:finance.manage');
        Route::post('finance/expenses', [FinanceController::class, 'storeExpense'])->middleware('permission:finance.manage');
        Route::post('finance/expenses/{expense}/review', [FinanceController::class, 'reviewExpense'])->middleware('permission:finance.manage');
        Route::post('finance/journal-entries', [FinanceController::class, 'storeJournalEntry'])->middleware('permission:finance.manage');

        Route::get('people', [PeopleController::class, 'index'])->middleware('permission:payroll.manage');
        Route::post('people/employees', [PeopleController::class, 'storeEmployeeProfile'])->middleware('permission:payroll.manage');
        Route::post('people/leave-requests', [PeopleController::class, 'storeLeaveRequest'])->middleware('permission:payroll.manage');
        Route::post('people/leave-requests/{leaveRequest}/review', [PeopleController::class, 'reviewLeaveRequest'])->middleware('permission:payroll.manage');
        Route::post('people/payroll-runs', [PeopleController::class, 'storePayrollRun'])->middleware('permission:payroll.manage');
        Route::post('people/payroll-runs/{payrollRun}/approve', [PeopleController::class, 'approvePayrollRun'])->middleware('permission:payroll.manage');

        Route::get('equipment', [EquipmentController::class, 'index'])->middleware('permission:equipment.manage');
        Route::post('equipment/assets', [EquipmentController::class, 'storeAsset'])->middleware('permission:equipment.manage');
        Route::post('equipment/assets/{asset}/assign', [EquipmentController::class, 'assignAsset'])->middleware('permission:equipment.manage');
        Route::post('equipment/assignments/{assignment}/release', [EquipmentController::class, 'releaseAssignment'])->middleware('permission:equipment.manage');
        Route::post('equipment/assets/{asset}/maintenance', [EquipmentController::class, 'storeMaintenance'])->middleware('permission:equipment.manage');
        Route::post('equipment/assets/{asset}/fuel-logs', [EquipmentController::class, 'storeFuelLog'])->middleware('permission:equipment.manage');

        Route::get('compliance', [ComplianceController::class, 'index'])->middleware('permission:quality.manage|safety.manage');
        Route::post('projects/{project}/inspections', [ComplianceController::class, 'storeInspection'])->middleware('permission:quality.manage');
        Route::post('compliance/inspections/{inspection}/complete', [ComplianceController::class, 'completeInspection'])->middleware('permission:quality.manage');
        Route::post('projects/{project}/ncrs', [ComplianceController::class, 'storeNcr'])->middleware('permission:quality.manage');
        Route::post('compliance/ncrs/{ncr}/close', [ComplianceController::class, 'closeNcr'])->middleware('permission:quality.manage');
        Route::post('safety/incidents', [ComplianceController::class, 'storeIncident'])->middleware('permission:safety.manage');
        Route::post('safety/incidents/{incident}/close', [ComplianceController::class, 'closeIncident'])->middleware('permission:safety.manage');
        Route::post('safety/toolbox-talks', [ComplianceController::class, 'storeToolboxTalk'])->middleware('permission:safety.manage');
        Route::post('safety/observations', [ComplianceController::class, 'storeObservation'])->middleware('permission:safety.manage');
        Route::post('safety/observations/{observation}/close', [ComplianceController::class, 'closeObservation'])->middleware('permission:safety.manage');
        Route::post('safety/permits', [ComplianceController::class, 'storePermit'])->middleware('permission:safety.manage');
        Route::post('safety/permits/{permit}/transition', [ComplianceController::class, 'transitionPermit'])->middleware('permission:safety.manage');

        Route::get('portals', [PortalController::class, 'index'])->middleware('permission:portals.manage');
        Route::post('portals/users', [PortalController::class, 'storePortalUser'])->middleware('permission:portals.manage');
        Route::post('portals/users/{portalUser}/access', [PortalController::class, 'grantAccess'])->middleware('permission:portals.manage');
        Route::post('projects/{project}/client-approvals', [PortalController::class, 'storeClientApproval'])->middleware('permission:portals.manage');
        Route::post('portals/client-approvals/{approval}/review', [PortalController::class, 'reviewClientApproval'])->middleware('permission:portals.manage');
        Route::post('projects/{project}/consultant-submittals', [PortalController::class, 'storeConsultantSubmittal'])->middleware('permission:portals.manage');
        Route::post('portals/consultant-submittals/{submittal}/review', [PortalController::class, 'reviewConsultantSubmittal'])->middleware('permission:portals.manage');

        Route::get('intelligence', [IntelligenceController::class, 'index'])->middleware('permission:intelligence.manage');
        Route::post('intelligence/analyze', [IntelligenceController::class, 'analyze'])->middleware('permission:intelligence.manage');
        Route::post('intelligence/assistant', [IntelligenceController::class, 'ask'])->middleware('permission:intelligence.manage');
        Route::post('intelligence/insights/{insight}/resolve', [IntelligenceController::class, 'resolveInsight'])->middleware('permission:intelligence.manage');

        Route::get('bi', [BusinessIntelligenceController::class, 'index'])->middleware('permission:bi.manage');
        Route::post('bi/dashboards', [BusinessIntelligenceController::class, 'storeDashboard'])->middleware('permission:bi.manage');
        Route::post('bi/snapshots', [BusinessIntelligenceController::class, 'createSnapshot'])->middleware('permission:bi.manage');

        Route::get('automation', [AutomationController::class, 'index'])->middleware('permission:automation.manage');
        Route::post('automation/rules', [AutomationController::class, 'storeRule'])->middleware('permission:automation.manage');
        Route::patch('automation/rules/{rule}', [AutomationController::class, 'updateRule'])->middleware('permission:automation.manage');
        Route::post('automation/rules/{rule}/run', [AutomationController::class, 'runRule'])->middleware('permission:automation.manage');
        Route::post('automation/run-active', [AutomationController::class, 'runActive'])->middleware('permission:automation.manage');

        Route::get('integrations', [IntegrationController::class, 'index'])->middleware('permission:integrations.manage');
        Route::post('integrations/connectors', [IntegrationController::class, 'storeConnector'])->middleware('permission:integrations.manage');
        Route::post('integrations/connectors/{connector}/test', [IntegrationController::class, 'testConnector'])->middleware('permission:integrations.manage');
        Route::post('integrations/webhooks', [IntegrationController::class, 'storeWebhookSubscription'])->middleware('permission:integrations.manage');
        Route::post('integrations/webhooks/{subscription}/dispatch', [IntegrationController::class, 'dispatchWebhook'])->middleware('permission:integrations.manage');
        Route::get('ecosystem/openapi', [IntegrationController::class, 'openApi'])->middleware('permission:integrations.manage');
        Route::post('ecosystem/graphql', [IntegrationController::class, 'graphql'])->middleware('permission:integrations.manage');

        Route::get('localization', [LocalizationController::class, 'index'])->middleware('permission:localization.manage');
        Route::patch('localization/settings', [LocalizationController::class, 'updateSettings'])->middleware('permission:localization.manage');
        Route::post('localization/tax-rates', [LocalizationController::class, 'storeTaxRate'])->middleware('permission:localization.manage');
        Route::post('localization/exchange-rates', [LocalizationController::class, 'storeExchangeRate'])->middleware('permission:localization.manage');
        Route::post('localization/convert', [LocalizationController::class, 'convertCurrency'])->middleware('permission:localization.manage');
        Route::post('localization/calculate-tax', [LocalizationController::class, 'calculateTax'])->middleware('permission:localization.manage');
    });
});
