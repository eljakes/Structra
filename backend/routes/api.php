<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetLineController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DrawingController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ProcurementController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTaskController;
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
        Route::post('organization/branches', [OrganizationController::class, 'storeBranch'])->middleware('permission:settings.manage');
        Route::post('organization/users', [OrganizationController::class, 'storeUser'])->middleware('permission:settings.manage');
        Route::post('organization/clients', [OrganizationController::class, 'storeClient'])->middleware('permission:projects.manage');
        Route::post('organization/suppliers', [OrganizationController::class, 'storeSupplier'])->middleware('permission:procurement.manage');

        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store'])->middleware('permission:projects.manage');
        Route::get('projects/timeline', [ProjectController::class, 'timeline']);
        Route::get('projects/{project}', [ProjectController::class, 'show']);
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware('permission:projects.manage');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('permission:projects.manage');

        Route::post('projects/{project}/tasks', [ProjectTaskController::class, 'store'])->middleware('permission:projects.manage');
        Route::patch('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'update'])->middleware('permission:projects.manage');
        Route::delete('projects/{project}/tasks/{task}', [ProjectTaskController::class, 'destroy'])->middleware('permission:projects.manage');

        Route::post('projects/{project}/budget-lines', [BudgetLineController::class, 'store'])->middleware('permission:projects.manage');
        Route::patch('projects/{project}/budget-lines/{budgetLine}', [BudgetLineController::class, 'update'])->middleware('permission:projects.manage');
        Route::delete('projects/{project}/budget-lines/{budgetLine}', [BudgetLineController::class, 'destroy'])->middleware('permission:projects.manage');

        Route::get('procurement/requisitions', [ProcurementController::class, 'requisitions']);
        Route::post('projects/{project}/requisitions', [ProcurementController::class, 'storeRequisition'])->middleware('permission:procurement.manage');
        Route::patch('procurement/requisitions/{requisition}', [ProcurementController::class, 'updateRequisition'])->middleware('permission:procurement.manage');
        Route::post('procurement/requisitions/{requisition}/submit', [ProcurementController::class, 'submitRequisition'])->middleware('permission:procurement.manage');
        Route::post('procurement/requisitions/{requisition}/review', [ProcurementController::class, 'reviewRequisition'])->middleware('permission:procurement.approve');
        Route::post('procurement/requisitions/{requisition}/convert-to-po', [ProcurementController::class, 'convertToPurchaseOrder'])->middleware('permission:procurement.manage');

        Route::get('procurement/purchase-orders', [ProcurementController::class, 'purchaseOrders']);
        Route::post('projects/{project}/purchase-orders', [ProcurementController::class, 'storePurchaseOrder'])->middleware('permission:procurement.manage');
        Route::post('procurement/purchase-orders/{purchaseOrder}/transition', [ProcurementController::class, 'transitionPurchaseOrder'])->middleware('permission:procurement.manage');

        Route::get('documents', [DocumentController::class, 'index']);
        Route::post('documents', [DocumentController::class, 'store'])->middleware('permission:documents.manage');
        Route::patch('documents/{document}', [DocumentController::class, 'update'])->middleware('permission:documents.manage');
        Route::get('documents/{document}/download', [DocumentController::class, 'download']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->middleware('permission:documents.manage');

        Route::get('drawings', [DrawingController::class, 'index']);
        Route::post('drawings', [DrawingController::class, 'store'])->middleware('permission:documents.manage');
        Route::post('drawings/{drawing}/revisions', [DrawingController::class, 'revise'])->middleware('permission:documents.manage');
        Route::post('drawings/{drawing}/transition', [DrawingController::class, 'transition'])->middleware('permission:documents.manage');
        Route::get('drawing-revisions/{revision}/download', [DrawingController::class, 'downloadRevision']);
    });
});
