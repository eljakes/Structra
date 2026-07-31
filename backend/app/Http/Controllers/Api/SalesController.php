<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\BudgetLine;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\EstimateLine;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\PricingItem;
use App\Models\Project;
use App\Models\Tender;
use App\Models\TenderDocument;
use App\Models\TenderRecord;
use App\Models\TenderRfi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SalesController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $tenders = Tender::query()
            ->forCompany($companyId)
            ->with(['client', 'opportunity', 'project', 'estimates.lines', 'rfis', 'documents', 'records.owner'])
            ->latest()
            ->get();
        $opportunities = Opportunity::query()->forCompany($companyId)->with(['client', 'lead', 'tenders'])->latest()->get();

        return response()->json([
            'leads' => Lead::query()->forCompany($companyId)->with(['client', 'opportunity'])->latest()->get(),
            'opportunities' => $opportunities,
            'tenders' => $this->decorateTenders($tenders),
            'estimates' => Estimate::query()->forCompany($companyId)->with(['tender', 'lines'])->latest()->get(),
            'pricing_items' => PricingItem::query()->forCompany($companyId)->where('active', true)->orderBy('category')->orderBy('description')->get(),
            'tendering' => $this->tenderingPayload($tenders, $opportunities),
        ]);
    }

    public function storeLead(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'source' => ['nullable', 'string', 'max:80'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'next_follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $branchId = $data['branch_id'] ?? $this->user($request)->branch_id;
        Branch::query()->forCompany($companyId)->whereKey($branchId)->firstOrFail();

        $lead = Lead::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'lead_number' => $this->nextNumber('LEAD', Lead::class, 'lead_number', $companyId),
            'stage' => 'new',
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            'created_by' => $this->user($request)->id,
            'updated_by' => $this->user($request)->id,
            ...$data,
        ]);

        return response()->json(['lead' => $lead], 201);
    }

    public function updateLead(Request $request, Lead $lead): JsonResponse
    {
        $this->assertTenant($request, $lead);

        $data = $request->validate([
            'company_name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'source' => ['sometimes', 'string', 'max:80'],
            'stage' => ['sometimes', Rule::in(['new', 'qualified', 'site_visit', 'quotation', 'tender', 'won', 'lost'])],
            'estimated_value' => ['sometimes', 'numeric', 'min:0'],
            'next_follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $lead->update([...$data, 'updated_by' => $this->user($request)->id]);

        return response()->json(['lead' => $lead->fresh(['client', 'opportunity'])]);
    }

    public function destroyLead(Request $request, Lead $lead): JsonResponse
    {
        $this->assertTenant($request, $lead);

        $lead->delete();

        return response()->json(['message' => 'Lead archived.']);
    }

    public function qualifyLead(Request $request, Lead $lead): JsonResponse
    {
        $this->assertTenant($request, $lead);

        abort_if($lead->opportunity()->exists(), 422, 'Lead already has an opportunity.');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:4000'],
            'expected_close_date' => ['nullable', 'date'],
        ]);

        $opportunity = DB::transaction(function () use ($request, $lead, $data) {
            $client = $lead->client;

            if (! $client) {
                $client = Client::query()->create([
                    'company_id' => $lead->company_id,
                    'branch_id' => $lead->branch_id,
                    'name' => $lead->company_name,
                    'contact_name' => $lead->contact_name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'currency' => $lead->currency,
                ]);
                $lead->update(['client_id' => $client->id]);
            }

            $opportunity = Opportunity::query()->create([
                'company_id' => $lead->company_id,
                'branch_id' => $lead->branch_id,
                'client_id' => $client->id,
                'lead_id' => $lead->id,
                'assigned_to' => $lead->assigned_to,
                'opportunity_number' => $this->nextNumber('OPP', Opportunity::class, 'opportunity_number', $lead->company_id),
                'name' => $data['name'] ?? $lead->company_name.' opportunity',
                'stage' => 'qualified',
                'scope' => $data['scope'] ?? $lead->notes,
                'probability' => 35,
                'estimated_value' => $lead->estimated_value,
                'currency' => $lead->currency,
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'created_by' => $this->user($request)->id,
                'updated_by' => $this->user($request)->id,
            ]);

            $lead->update(['stage' => 'qualified']);

            return $opportunity;
        });

        return response()->json(['opportunity' => $opportunity->load(['client', 'lead'])], 201);
    }

    public function storeOpportunity(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string', 'max:4000'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
        ]);

        $branchId = $data['branch_id'] ?? $this->user($request)->branch_id;
        $clientId = $data['client_id'] ?? null;

        if ($clientId) {
            Client::query()->forCompany($companyId)->whereKey($clientId)->firstOrFail();
        } elseif (! empty($data['client_name'])) {
            $client = Client::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $data['client_name'],
                'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            ]);
            $clientId = $client->id;
        }

        $opportunity = Opportunity::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'client_id' => $clientId,
            'opportunity_number' => $this->nextNumber('OPP', Opportunity::class, 'opportunity_number', $companyId),
            'stage' => 'qualified',
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            'created_by' => $this->user($request)->id,
            'updated_by' => $this->user($request)->id,
            ...collect($data)->except(['client_name'])->all(),
        ]);

        return response()->json(['opportunity' => $opportunity->load('client')], 201);
    }

    public function storeTender(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'tender_manager_id' => ['nullable', 'integer'],
            'business_development_officer_id' => ['nullable', 'integer'],
            'tender_type' => ['nullable', 'string', 'max:80'],
            'procurement_method' => ['nullable', 'string', 'max:80'],
            'project_sector' => ['nullable', 'string', 'max:80'],
            'project_category' => ['nullable', 'string', 'max:80'],
            'project_location' => ['nullable', 'string', 'max:255'],
            'deadline_at' => ['nullable', 'date'],
            'expected_award_at' => ['nullable', 'date'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'tender_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:4000'],
            'scope_summary' => ['nullable', 'string', 'max:4000'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'tender_authority' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:40'],
            'confidentiality_level' => ['nullable', 'string', 'max:40'],
            'bid_decision' => ['nullable', 'string', 'max:80'],
            'bid_decision_score' => ['nullable', 'integer', 'between:0,100'],
            'checklist' => ['nullable', 'array'],
            'deadline_schedule' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ]);

        $branchId = $data['branch_id'] ?? $this->user($request)->branch_id;
        Branch::query()->forCompany($companyId)->whereKey($branchId)->firstOrFail();

        $clientId = $data['client_id'] ?? null;

        if ($clientId) {
            Client::query()->forCompany($companyId)->whereKey($clientId)->firstOrFail();
        } elseif (! empty($data['client_name'])) {
            $client = Client::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'name' => $data['client_name'],
                'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            ]);
            $clientId = $client->id;
        }

        $tender = Tender::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'client_id' => $clientId,
            'tender_manager_id' => $this->companyUserId($data['tender_manager_id'] ?? null, $companyId),
            'business_development_officer_id' => $this->companyUserId($data['business_development_officer_id'] ?? null, $companyId),
            'tender_number' => $this->nextNumber('TND', Tender::class, 'tender_number', $companyId),
            'title' => $data['title'],
            'tender_type' => $data['tender_type'] ?? null,
            'procurement_method' => $data['procurement_method'] ?? null,
            'project_sector' => $data['project_sector'] ?? null,
            'project_category' => $data['project_category'] ?? null,
            'project_location' => $data['project_location'] ?? null,
            'status' => 'draft',
            'deadline_at' => $data['deadline_at'] ?? null,
            'expected_award_at' => $data['expected_award_at'] ?? null,
            'value' => $data['value'] ?? 0,
            'tender_fee' => $data['tender_fee'] ?? 0,
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            'description' => $data['description'] ?? null,
            'scope_summary' => $data['scope_summary'] ?? null,
            'funding_source' => $data['funding_source'] ?? null,
            'tender_authority' => $data['tender_authority'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'confidentiality_level' => $data['confidentiality_level'] ?? 'internal',
            'bid_decision' => $data['bid_decision'] ?? null,
            'bid_decision_score' => $data['bid_decision_score'] ?? null,
            'checklist' => $data['checklist'] ?? $this->defaultTenderChecklist(),
            'deadline_schedule' => $data['deadline_schedule'] ?? null,
            'settings' => $data['settings'] ?? null,
            'created_by' => $this->user($request)->id,
            'updated_by' => $this->user($request)->id,
        ]);

        $this->recordTenderActivity($tender, 'Tender created directly', 'created');

        return response()->json(['tender' => $this->decorateTender($tender->load(['client', 'opportunity', 'estimates.lines', 'rfis', 'documents', 'records.owner']))], 201);
    }

    public function createTenderFromOpportunity(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->assertTenant($request, $opportunity);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'deadline_at' => ['nullable', 'date'],
            'expected_award_at' => ['nullable', 'date'],
            'tender_type' => ['nullable', 'string', 'max:80'],
            'procurement_method' => ['nullable', 'string', 'max:80'],
            'project_sector' => ['nullable', 'string', 'max:80'],
            'project_category' => ['nullable', 'string', 'max:80'],
            'project_location' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:40'],
            'confidentiality_level' => ['nullable', 'string', 'max:40'],
            'checklist' => ['nullable', 'array'],
            'deadline_schedule' => ['nullable', 'array'],
        ]);

        $tender = Tender::query()->create([
            'company_id' => $opportunity->company_id,
            'branch_id' => $opportunity->branch_id,
            'client_id' => $opportunity->client_id,
            'opportunity_id' => $opportunity->id,
            'tender_number' => $this->nextNumber('TND', Tender::class, 'tender_number', $opportunity->company_id),
            'title' => $data['title'] ?? $opportunity->name,
            'tender_type' => $data['tender_type'] ?? null,
            'procurement_method' => $data['procurement_method'] ?? null,
            'project_sector' => $data['project_sector'] ?? null,
            'project_category' => $data['project_category'] ?? null,
            'project_location' => $data['project_location'] ?? null,
            'status' => 'draft',
            'deadline_at' => $data['deadline_at'] ?? null,
            'expected_award_at' => $data['expected_award_at'] ?? $opportunity->expected_close_date,
            'value' => $opportunity->estimated_value,
            'currency' => $opportunity->currency,
            'scope_summary' => $opportunity->scope,
            'priority' => $data['priority'] ?? 'medium',
            'confidentiality_level' => $data['confidentiality_level'] ?? 'internal',
            'checklist' => $data['checklist'] ?? $this->defaultTenderChecklist(),
            'deadline_schedule' => $data['deadline_schedule'] ?? null,
            'created_by' => $this->user($request)->id,
            'updated_by' => $this->user($request)->id,
        ]);

        $opportunity->update(['stage' => 'tender']);
        $this->recordTenderActivity($tender, 'Tender created from opportunity '.$opportunity->opportunity_number, 'created');

        return response()->json(['tender' => $this->decorateTender($tender->load(['client', 'opportunity', 'estimates.lines', 'rfis', 'documents', 'records.owner']))], 201);
    }

    public function updateTender(Request $request, Tender $tender): JsonResponse
    {
        $this->assertTenant($request, $tender);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'tender_manager_id' => ['nullable', 'integer'],
            'business_development_officer_id' => ['nullable', 'integer'],
            'tender_type' => ['nullable', 'string', 'max:80'],
            'procurement_method' => ['nullable', 'string', 'max:80'],
            'project_sector' => ['nullable', 'string', 'max:80'],
            'project_category' => ['nullable', 'string', 'max:80'],
            'project_location' => ['nullable', 'string', 'max:255'],
            'deadline_at' => ['nullable', 'date'],
            'expected_award_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in($this->tenderStatuses())],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'tender_fee' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:4000'],
            'scope_summary' => ['nullable', 'string', 'max:4000'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'tender_authority' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:40'],
            'confidentiality_level' => ['nullable', 'string', 'max:40'],
            'bid_decision' => ['nullable', 'string', 'max:80'],
            'bid_decision_score' => ['nullable', 'integer', 'between:0,100'],
            'checklist' => ['nullable', 'array'],
            'deadline_schedule' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'lost_reason' => ['nullable', 'string', 'max:4000'],
        ]);

        if (array_key_exists('tender_manager_id', $data)) {
            $data['tender_manager_id'] = $this->companyUserId($data['tender_manager_id'], $tender->company_id);
        }

        if (array_key_exists('business_development_officer_id', $data)) {
            $data['business_development_officer_id'] = $this->companyUserId($data['business_development_officer_id'], $tender->company_id);
        }

        $tender->update([...$data, 'updated_by' => $this->user($request)->id]);

        if (array_key_exists('status', $data)) {
            $this->syncOpportunityStageForTender($tender->fresh('opportunity'));
            $this->recordTenderActivity($tender->fresh(), 'Tender status changed to '.str_replace('_', ' ', $data['status']), 'status_change');
        } else {
            $this->recordTenderActivity($tender->fresh(), 'Tender details updated', 'updated');
        }

        return response()->json(['tender' => $this->decorateTender($tender->fresh(['client', 'opportunity', 'estimates.lines', 'rfis', 'documents', 'records.owner']))]);
    }

    public function submitTender(Request $request, Tender $tender): JsonResponse
    {
        $this->assertTenant($request, $tender);
        abort_if(! in_array($tender->status, ['draft', 'pending', 'ready_for_submission'], true), 422, 'Only draft, pending, or ready-for-submission tenders can be submitted.');

        $tender->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'updated_by' => $this->user($request)->id,
        ]);

        $this->syncOpportunityStageForTender($tender->fresh('opportunity'));
        $this->recordTenderActivity($tender->fresh(), 'Tender submitted', 'submitted');

        return response()->json(['tender' => $this->decorateTender($tender->fresh(['client', 'opportunity', 'estimates.lines', 'rfis', 'documents', 'records.owner']))]);
    }

    public function winTender(Request $request, Tender $tender): JsonResponse
    {
        $this->assertTenant($request, $tender);

        $data = $request->validate([
            'estimate_id' => ['nullable', 'integer'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'target_end_date' => ['nullable', 'date'],
        ]);

        $project = DB::transaction(function () use ($request, $tender, $data) {
            $estimate = null;

            if (! empty($data['estimate_id'])) {
                $estimate = Estimate::query()
                    ->forCompany($tender->company_id)
                    ->where('tender_id', $tender->id)
                    ->whereKey($data['estimate_id'])
                    ->firstOrFail();
            } else {
                $estimate = $tender->estimates()->latest()->first();
            }

            $project = Project::query()->create([
                'company_id' => $tender->company_id,
                'branch_id' => $tender->branch_id ?? $this->user($request)->branch_id,
                'client_id' => $tender->client_id,
                'code' => $this->nextNumber('PRJ', Project::class, 'code', $tender->company_id),
                'name' => $data['project_name'] ?? $tender->title,
                'description' => 'Created from tender '.$tender->tender_number,
                'status' => 'planning',
                'health_status' => 'on_track',
                'risk_level' => 'medium',
                'currency' => $tender->currency,
                'contract_value' => $estimate?->total_amount ?? $tender->value,
                'start_date' => $data['start_date'] ?? null,
                'target_end_date' => $data['target_end_date'] ?? null,
                'created_by' => $this->user($request)->id,
                'updated_by' => $this->user($request)->id,
            ]);

            if ($estimate) {
                foreach ($estimate->lines as $line) {
                    BudgetLine::query()->create([
                        'company_id' => $project->company_id,
                        'branch_id' => $project->branch_id,
                        'project_id' => $project->id,
                        'cost_code' => $line->cost_code ?: 'EST-'.$line->id,
                        'description' => $line->description,
                        'category' => $line->category,
                        'budget_amount' => $line->line_total,
                        'forecast_amount' => $line->line_total,
                    ]);
                }

                $estimate->update(['status' => 'converted', 'project_id' => $project->id]);
                $this->syncProjectCosts($project);
            }

            $tender->update([
                'status' => 'won',
                'won_at' => now(),
                'project_id' => $project->id,
                'updated_by' => $this->user($request)->id,
            ]);

            $tender->opportunity?->update(['stage' => 'won']);
            $this->recordTenderActivity($tender->fresh(), 'Award converted to project '.$project->code, 'project_created');

            return $project;
        });

        return response()->json(['project' => $project->load(['client', 'budgetLines'])], 201);
    }

    public function loseTender(Request $request, Tender $tender): JsonResponse
    {
        $this->assertTenant($request, $tender);

        $data = $request->validate(['lost_reason' => ['required', 'string', 'max:4000']]);

        $tender->update([
            'status' => 'lost',
            'lost_reason' => $data['lost_reason'],
            'updated_by' => $this->user($request)->id,
        ]);

        $tender->opportunity?->update(['stage' => 'lost']);
        $this->recordTenderActivity($tender->fresh(), 'Tender lost: '.$data['lost_reason'], 'lost');

        return response()->json(['tender' => $this->decorateTender($tender->fresh(['client', 'opportunity', 'estimates.lines', 'rfis', 'documents', 'records.owner']))]);
    }

    public function storePricingItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'cost_code' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'unit' => ['nullable', 'string', 'max:24'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'source' => ['nullable', 'string', 'max:80'],
        ]);

        $item = PricingItem::query()->create([
            'company_id' => $this->companyId($request),
            'cost_code' => $this->suppliedCode($data['cost_code'] ?? null)
                ?? $this->nextCompanyCode($this->codePrefix($data['category'] ?? $data['description'], 'CST'), PricingItem::class, 'cost_code', $this->companyId($request)),
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            ...collect($data)->except(['cost_code', 'currency'])->all(),
        ]);

        return response()->json(['pricing_item' => $item], 201);
    }

    public function storeEstimate(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'tender_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'scenario_name' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'size:3'],
            'overhead_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'profit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valid_until' => ['nullable', 'date'],
            'lines' => ['nullable', 'array'],
            'lines.*.pricing_item_id' => ['nullable', 'integer'],
            'lines.*.cost_code' => ['nullable', 'string', 'max:40'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:255'],
            'lines.*.category' => ['nullable', 'string', 'max:80'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0.01'],
            'lines.*.unit' => ['nullable', 'string', 'max:24'],
            'lines.*.unit_cost' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.markup_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $estimate = DB::transaction(function () use ($request, $companyId, $data) {
            $tender = null;

            if (! empty($data['tender_id'])) {
                $tender = Tender::query()->forCompany($companyId)->whereKey($data['tender_id'])->firstOrFail();
            }

            $estimate = Estimate::query()->create([
                'company_id' => $companyId,
                'branch_id' => $tender?->branch_id ?? $this->user($request)->branch_id,
                'tender_id' => $tender?->id,
                'client_id' => $data['client_id'] ?? $tender?->client_id,
                'estimate_number' => $this->nextNumber('EST', Estimate::class, 'estimate_number', $companyId),
                'title' => $data['title'],
                'scenario_name' => $data['scenario_name'] ?? 'Base',
                'currency' => strtoupper($data['currency'] ?? $tender?->currency ?? $this->user($request)->company->default_currency),
                'overhead_percent' => $data['overhead_percent'] ?? 0,
                'profit_percent' => $data['profit_percent'] ?? 0,
                'tax_percent' => $data['tax_percent'] ?? 0,
                'valid_until' => $data['valid_until'] ?? null,
                'prepared_by' => $this->user($request)->id,
            ]);

            foreach ($data['lines'] ?? [] as $line) {
                $this->createEstimateLine($estimate, $line);
            }

            $this->syncEstimateTotals($estimate);

            return $estimate;
        });

        return response()->json(['estimate' => $estimate->load(['tender', 'lines'])], 201);
    }

    public function addEstimateLine(Request $request, Estimate $estimate): JsonResponse
    {
        $this->assertTenant($request, $estimate);
        abort_if(! in_array($estimate->status, ['draft', 'approved'], true), 422, 'Converted estimates cannot be edited.');

        $data = $request->validate([
            'pricing_item_id' => ['nullable', 'integer'],
            'cost_code' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:24'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'markup_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $line = $this->createEstimateLine($estimate, $data);
        $this->syncEstimateTotals($estimate);

        return response()->json(['line' => $line, 'estimate' => $estimate->fresh('lines')], 201);
    }

    public function approveEstimate(Request $request, Estimate $estimate): JsonResponse
    {
        $this->assertTenant($request, $estimate);
        abort_if($estimate->lines()->count() === 0, 422, 'Estimate requires at least one line before approval.');

        $estimate->update([
            'status' => 'approved',
            'approved_by' => $this->user($request)->id,
            'approved_at' => now(),
        ]);

        if ($estimate->tender) {
            $estimate->tender->update(['value' => $estimate->total_amount]);
        }

        return response()->json(['estimate' => $estimate->fresh(['tender', 'lines'])]);
    }

    public function storeTenderRfi(Request $request, Tender $tender): JsonResponse
    {
        $this->assertTenant($request, $tender);

        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:80'],
            'question' => ['required', 'string', 'max:4000'],
            'submitted_to' => ['nullable', 'string', 'max:255'],
            'submitted_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'related_drawing' => ['nullable', 'string', 'max:255'],
            'related_boq_item' => ['nullable', 'string', 'max:255'],
            'related_specification' => ['nullable', 'string', 'max:255'],
            'internal_impact' => ['nullable', 'string', 'max:4000'],
            'cost_impact' => ['nullable', 'numeric'],
            'schedule_impact_days' => ['nullable', 'integer'],
            'supporting_documents' => ['nullable', 'array'],
        ]);

        $rfi = TenderRfi::query()->create([
            'company_id' => $tender->company_id,
            'tender_id' => $tender->id,
            'rfi_number' => $this->nextNumber('RFI', TenderRfi::class, 'rfi_number', $tender->company_id),
            'asked_by' => $this->user($request)->id,
            'status' => $data['submitted_at'] ?? null ? 'submitted' : 'draft',
            ...$data,
        ]);

        $this->recordTenderActivity($tender, 'RFI created: '.$rfi->rfi_number, 'rfi_created');

        return response()->json(['rfi' => $rfi], 201);
    }

    public function respondTenderRfi(Request $request, TenderRfi $rfi): JsonResponse
    {
        $this->assertTenant($request, $rfi);

        $data = $request->validate(['response' => ['required', 'string', 'max:4000']]);

        $rfi->update([
            'response' => $data['response'],
            'status' => 'answered',
            'responded_by' => $this->user($request)->id,
            'responded_at' => now(),
        ]);

        $this->recordTenderActivity($rfi->tender, 'RFI answered: '.$rfi->rfi_number, 'rfi_answered');

        return response()->json(['rfi' => $rfi->fresh()]);
    }

    public function uploadTenderDocument(Request $request, Tender $tender): JsonResponse
    {
        $this->assertTenant($request, $tender);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'version' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:80'],
            'is_mandatory' => ['nullable', 'boolean'],
            'is_confidential' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
            'comments' => ['nullable', 'string', 'max:4000'],
            'file' => ['nullable', 'file', 'max:51200'],
        ]);

        $filePayload = [];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store("navkwabuild/companies/{$tender->company_id}/tenders/{$tender->id}", 'local');
            $filePayload = [
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ];
        }

        $document = TenderDocument::query()->create([
            'company_id' => $tender->company_id,
            'tender_id' => $tender->id,
            'uploaded_by' => $this->user($request)->id,
            'title' => $data['title'],
            'document_type' => $data['document_type'] ?? 'tender',
            'version' => $data['version'] ?? '1',
            'status' => $data['status'] ?? 'draft',
            'is_mandatory' => $data['is_mandatory'] ?? false,
            'is_confidential' => $data['is_confidential'] ?? false,
            'expires_at' => $data['expires_at'] ?? null,
            'comments' => $data['comments'] ?? null,
            ...$filePayload,
        ]);

        $this->recordTenderActivity($tender, 'Document uploaded: '.$document->title, 'document_uploaded');

        return response()->json(['document' => $document], 201);
    }

    public function storeTenderRecord(Request $request, Tender $tender): JsonResponse
    {
        $this->assertTenant($request, $tender);

        $data = $this->validateTenderRecord($request, $tender->company_id);
        $recordType = $data['record_type'];

        $record = TenderRecord::query()->create([
            'company_id' => $tender->company_id,
            'tender_id' => $tender->id,
            'record_number' => $this->nextNumber($this->recordPrefix($recordType), TenderRecord::class, 'record_number', $tender->company_id),
            'currency' => $data['currency'] ?? $tender->currency,
            'created_by' => $this->user($request)->id,
            'updated_by' => $this->user($request)->id,
            ...collect($data)->except(['currency'])->all(),
        ]);

        $this->recordTenderActivity($tender, 'Tender record created: '.$record->title, $recordType.'_created');

        return response()->json(['record' => $record->load('owner')], 201);
    }

    public function updateTenderRecord(Request $request, TenderRecord $record): JsonResponse
    {
        $this->assertTenant($request, $record);

        $data = $this->validateTenderRecord($request, $record->company_id, partial: true);

        if (($data['status'] ?? null) === 'completed' && empty($data['completed_at']) && ! $record->completed_at) {
            $data['completed_at'] = now();
        }

        $record->update([...$data, 'updated_by' => $this->user($request)->id]);
        $this->recordTenderActivity($record->tender, 'Tender record updated: '.$record->fresh()->title, $record->record_type.'_updated');

        return response()->json(['record' => $record->fresh('owner')]);
    }

    public function downloadTenderDocument(Request $request, TenderDocument $document)
    {
        $this->assertTenant($request, $document);

        abort_if(! $document->file_path || ! Storage::disk('local')->exists($document->file_path), 404, 'Tender document file was not found.');

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }

    private function createEstimateLine(Estimate $estimate, array $line): EstimateLine
    {
        $pricingItem = null;

        if (! empty($line['pricing_item_id'])) {
            $pricingItem = PricingItem::query()
                ->forCompany($estimate->company_id)
                ->whereKey($line['pricing_item_id'])
                ->firstOrFail();
        }

        $quantity = (float) $line['quantity'];
        $unitCost = (float) ($line['unit_cost'] ?? $pricingItem?->unit_cost ?? 0);
        $markup = (float) ($line['markup_percent'] ?? 0);
        $lineTotal = round($quantity * $unitCost * (1 + ($markup / 100)), 2);
        $description = $line['description'] ?? $pricingItem?->description;
        $category = $line['category'] ?? $pricingItem?->category ?? 'materials';

        return EstimateLine::query()->create([
            'company_id' => $estimate->company_id,
            'estimate_id' => $estimate->id,
            'pricing_item_id' => $pricingItem?->id,
            'cost_code' => $this->suppliedCode($line['cost_code'] ?? null)
                ?? $pricingItem?->cost_code
                ?? $this->nextCompanyCode($this->codePrefix($category ?? $description, 'CST'), EstimateLine::class, 'cost_code', $estimate->company_id),
            'description' => $description,
            'category' => $category,
            'quantity' => $quantity,
            'unit' => $line['unit'] ?? $pricingItem?->unit ?? 'each',
            'unit_cost' => $unitCost,
            'markup_percent' => $markup,
            'line_total' => $lineTotal,
        ]);
    }

    private function syncEstimateTotals(Estimate $estimate): void
    {
        $subtotal = (float) $estimate->lines()->sum('line_total');
        $overhead = round($subtotal * ((float) $estimate->overhead_percent / 100), 2);
        $profit = round(($subtotal + $overhead) * ((float) $estimate->profit_percent / 100), 2);
        $tax = round(($subtotal + $overhead + $profit) * ((float) $estimate->tax_percent / 100), 2);

        $estimate->forceFill([
            'subtotal' => $subtotal,
            'overhead_amount' => $overhead,
            'profit_amount' => $profit,
            'tax_amount' => $tax,
            'total_amount' => $subtotal + $overhead + $profit + $tax,
        ])->save();
    }

    private function tenderStatuses(): array
    {
        return [
            'draft',
            'under_review',
            'bid_decision_pending',
            'no_bid',
            'bid_approved',
            'planning',
            'estimating',
            'proposal_preparation',
            'internal_review',
            'awaiting_approval',
            'ready_for_submission',
            'pending',
            'submitted',
            'under_evaluation',
            'clarification_requested',
            'negotiation',
            'preferred_bidder',
            'won',
            'awarded',
            'lost',
            'cancelled',
            'withdrawn',
            'archived',
        ];
    }

    private function defaultTenderChecklist(): array
    {
        return [
            'Tender documents downloaded',
            'Tender fee paid',
            'Site visit attended',
            'BOQ imported',
            'Scope reviewed',
            'Supplier quotations received',
            'Cost estimate completed',
            'Technical proposal completed',
            'Commercial proposal approved',
            'Bid bond confirmed',
            'Submission package finalized',
        ];
    }

    private function syncOpportunityStageForTender(Tender $tender): void
    {
        $stage = match ($tender->status) {
            'draft', 'under_review', 'bid_decision_pending' => 'tender',
            'bid_approved', 'planning', 'estimating', 'proposal_preparation', 'internal_review', 'awaiting_approval', 'ready_for_submission', 'pending' => 'bid_preparation',
            'submitted' => 'tender_submitted',
            'under_evaluation', 'clarification_requested', 'preferred_bidder' => 'evaluation',
            'negotiation' => 'negotiation',
            'won', 'awarded' => 'won',
            'lost', 'no_bid', 'cancelled', 'withdrawn', 'archived' => 'lost',
            default => null,
        };

        if ($stage && $tender->opportunity) {
            $tender->opportunity->update(['stage' => $stage]);
        }
    }

    private function validateTenderRecord(Request $request, int $companyId, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'record_type' => [$required, 'string', Rule::in(array_keys($this->tenderRecordTypes()))],
            'owner_id' => ['nullable', 'integer'],
            'title' => [$required, 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_keys($this->recordStatuses()))],
            'priority' => ['nullable', 'string', Rule::in(array_keys($this->priorities()))],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'payload' => ['nullable', 'array'],
        ]);

        if (array_key_exists('owner_id', $data)) {
            $data['owner_id'] = $this->companyUserId($data['owner_id'], $companyId);
        }

        if (array_key_exists('currency', $data) && $data['currency']) {
            $data['currency'] = strtoupper($data['currency']);
        }

        return $data;
    }

    private function companyUserId(null|int|string $userId, int $companyId): ?int
    {
        if (! $userId) {
            return null;
        }

        return User::query()->where('company_id', $companyId)->whereKey($userId)->firstOrFail()->id;
    }

    private function recordPrefix(string $recordType): string
    {
        return [
            'team_member' => 'TMR',
            'site_visit' => 'SV',
            'addendum' => 'ADD',
            'boq_item' => 'BOQ',
            'supplier_quote' => 'SQT',
            'technical_proposal' => 'TP',
            'commercial_proposal' => 'CP',
            'bid_security' => 'SEC',
            'internal_approval' => 'APR',
            'submission' => 'SUB',
            'evaluation' => 'EVL',
            'outcome' => 'OUT',
            'audit_event' => 'AUD',
            'activity_log' => 'ACT',
        ][$recordType] ?? 'TR';
    }

    private function recordTenderActivity(Tender $tender, string $title, string $eventType, array $payload = []): void
    {
        TenderRecord::query()->create([
            'company_id' => $tender->company_id,
            'tender_id' => $tender->id,
            'record_type' => 'activity_log',
            'record_number' => $this->nextNumber('ACT', TenderRecord::class, 'record_number', $tender->company_id),
            'title' => $title,
            'status' => 'completed',
            'priority' => 'low',
            'completed_at' => now(),
            'currency' => $tender->currency,
            'payload' => ['event_type' => $eventType, ...$payload],
            'created_by' => request()->user()?->id,
            'updated_by' => request()->user()?->id,
        ]);
    }

    private function decorateTenders($tenders)
    {
        return $tenders->map(fn (Tender $tender) => $this->decorateTender($tender))->values();
    }

    private function decorateTender(Tender $tender): Tender
    {
        $tender->setAttribute('completion_percent', $this->tenderCompletion($tender));
        $tender->setAttribute('days_left', $this->daysLeft($tender));
        $tender->setAttribute('weighted_value', round(((float) $tender->value * $this->tenderProbability($tender->status)) / 100, 2));
        $tender->setAttribute('probability', $this->tenderProbability($tender->status));
        $tender->setAttribute('source_label', $tender->opportunity ? 'CRM Opportunity' : 'Direct Tender');
        $tender->setAttribute('checklist_summary', $this->tenderChecklistSummary($tender));

        return $tender;
    }

    private function tenderingPayload($tenders, $opportunities): array
    {
        $decoratedTenders = $this->decorateTenders($tenders);

        return [
            'catalog' => $this->tenderCatalog(),
            'summary' => $this->tenderSummary($decoratedTenders),
            'summary_cards' => $this->tenderSummaryCards($decoratedTenders),
            'alerts' => $this->tenderAlerts($decoratedTenders),
            'analytics' => $this->tenderAnalytics($decoratedTenders),
            'reports' => $this->tenderReports($decoratedTenders),
            'bid_opportunities' => $opportunities
                ->filter(fn (Opportunity $opportunity) => $opportunity->tenders->isEmpty() && ! in_array($opportunity->stage, ['won', 'lost'], true))
                ->values(),
        ];
    }

    private function tenderCatalog(): array
    {
        return [
            'statuses' => $this->labelMap($this->tenderStatuses()),
            'record_types' => $this->tenderRecordTypes(),
            'record_statuses' => $this->recordStatuses(),
            'priorities' => $this->priorities(),
            'tender_types' => [
                'open_tender' => 'Open tender',
                'restricted_tender' => 'Restricted tender',
                'selective_tender' => 'Selective tender',
                'request_for_quotation' => 'Request for quotation',
                'request_for_proposal' => 'Request for proposal',
                'expression_of_interest' => 'Expression of interest',
                'prequalification' => 'Prequalification',
                'negotiated_tender' => 'Negotiated tender',
                'framework_agreement' => 'Framework agreement',
                'design_and_build' => 'Design-and-build tender',
                'public_private_partnership' => 'Public-private partnership',
                'private_invitation' => 'Private invitation',
            ],
            'procurement_methods' => [
                'competitive' => 'Competitive',
                'single_stage' => 'Single stage',
                'two_stage' => 'Two stage',
                'negotiated' => 'Negotiated',
                'framework' => 'Framework',
            ],
            'project_sectors' => [
                'education' => 'Education',
                'healthcare' => 'Healthcare',
                'residential' => 'Residential',
                'commercial' => 'Commercial',
                'industrial' => 'Industrial',
                'government' => 'Government',
                'ngo' => 'NGO',
                'infrastructure' => 'Infrastructure',
            ],
            'project_categories' => [
                'building' => 'Building',
                'civil_works' => 'Civil works',
                'renovation' => 'Renovation',
                'maintenance' => 'Maintenance',
                'design_build' => 'Design and build',
            ],
            'confidentiality_levels' => [
                'public' => 'Public',
                'internal' => 'Internal',
                'confidential' => 'Confidential',
                'commercial_restricted' => 'Commercial restricted',
            ],
            'bid_decisions' => [
                'bid_approved' => 'Bid approved',
                'no_bid' => 'Do not bid',
                'more_information_required' => 'More information required',
                'management_review_required' => 'Management review required',
            ],
            'dashboard_alert_types' => [
                'deadline_approaching' => 'Tender deadline approaching',
                'missing_document' => 'Missing mandatory document',
                'bid_bond_missing' => 'Bid bond not secured',
                'estimate_unapproved' => 'Estimate not approved',
                'proposal_incomplete' => 'Proposal incomplete',
                'unanswered_rfi' => 'Unanswered RFI',
                'addendum_unacknowledged' => 'Addendum not acknowledged',
                'approval_overdue' => 'Internal approval overdue',
                'receipt_missing' => 'Submission receipt missing',
                'result_overdue' => 'Tender result overdue',
            ],
        ];
    }

    private function tenderRecordTypes(): array
    {
        return [
            'team_member' => 'Tender team',
            'site_visit' => 'Site visit',
            'addendum' => 'Addendum',
            'boq_item' => 'BOQ item',
            'supplier_quote' => 'Supplier quotation',
            'technical_proposal' => 'Technical proposal',
            'commercial_proposal' => 'Commercial proposal',
            'bid_security' => 'Bid security and compliance',
            'internal_approval' => 'Internal approval',
            'submission' => 'Submission',
            'evaluation' => 'Evaluation',
            'outcome' => 'Outcome and win/loss analysis',
            'audit_event' => 'Audit event',
            'activity_log' => 'Activity log',
        ];
    }

    private function recordStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'pending' => 'Pending',
            'in_progress' => 'In progress',
            'submitted' => 'Submitted',
            'under_review' => 'Under review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    private function priorities(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ];
    }

    private function labelMap(array $values): array
    {
        return collect($values)->mapWithKeys(fn (string $value) => [$value => str($value)->replace('_', ' ')->title()->toString()])->all();
    }

    private function tenderSummary($tenders): array
    {
        $terminal = ['won', 'awarded', 'lost', 'cancelled', 'withdrawn', 'no_bid', 'archived'];
        $active = $tenders->reject(fn (Tender $tender) => in_array($tender->status, $terminal, true));
        $won = $tenders->filter(fn (Tender $tender) => in_array($tender->status, ['won', 'awarded'], true));
        $lost = $tenders->filter(fn (Tender $tender) => in_array($tender->status, ['lost', 'cancelled', 'withdrawn', 'no_bid'], true));
        $decided = $won->count() + $lost->count();
        $prepDays = $tenders
            ->filter(fn (Tender $tender) => $tender->submitted_at)
            ->map(fn (Tender $tender) => $tender->created_at->diffInDays($tender->submitted_at));

        return [
            'active_tenders' => $active->count(),
            'due_this_week' => $active->filter(fn (Tender $tender) => $tender->deadline_at && $tender->deadline_at->between(now(), now()->addWeek()))->count(),
            'awaiting_bid_decision' => $tenders->whereIn('status', ['draft', 'under_review', 'bid_decision_pending'])->count(),
            'under_preparation' => $tenders->whereIn('status', ['bid_approved', 'planning', 'estimating', 'proposal_preparation', 'pending'])->count(),
            'awaiting_approval' => $tenders->whereIn('status', ['internal_review', 'awaiting_approval', 'ready_for_submission'])->count(),
            'submitted' => $tenders->where('status', 'submitted')->count(),
            'under_evaluation' => $tenders->whereIn('status', ['under_evaluation', 'clarification_requested', 'negotiation', 'preferred_bidder'])->count(),
            'won' => $won->count(),
            'lost' => $lost->count(),
            'active_value' => round($active->sum(fn (Tender $tender) => (float) $tender->value), 2),
            'weighted_pipeline_value' => round($active->sum(fn (Tender $tender) => (float) $tender->weighted_value), 2),
            'win_rate' => $decided ? round(($won->count() / $decided) * 100) : 0,
            'average_preparation_days' => $prepDays->count() ? round($prepDays->average()) : 0,
        ];
    }

    private function tenderSummaryCards($tenders): array
    {
        $summary = $this->tenderSummary($tenders);

        return [
            ['key' => 'active_tenders', 'label' => 'Active tenders', 'value' => $summary['active_tenders'], 'value_type' => 'number', 'sub' => 'Live bid workload'],
            ['key' => 'due_this_week', 'label' => 'Tenders due this week', 'value' => $summary['due_this_week'], 'value_type' => 'number', 'sub' => 'Submission deadline risk'],
            ['key' => 'awaiting_bid_decision', 'label' => 'Awaiting bid decision', 'value' => $summary['awaiting_bid_decision'], 'value_type' => 'number', 'sub' => 'Bid/no-bid pending'],
            ['key' => 'under_preparation', 'label' => 'Under preparation', 'value' => $summary['under_preparation'], 'value_type' => 'number', 'sub' => 'Estimating and proposal work'],
            ['key' => 'awaiting_approval', 'label' => 'Awaiting internal approval', 'value' => $summary['awaiting_approval'], 'value_type' => 'number', 'sub' => 'Approval gate'],
            ['key' => 'submitted', 'label' => 'Submitted', 'value' => $summary['submitted'], 'value_type' => 'number', 'sub' => 'Sent to client'],
            ['key' => 'under_evaluation', 'label' => 'Under evaluation', 'value' => $summary['under_evaluation'], 'value_type' => 'number', 'sub' => 'Client review'],
            ['key' => 'won', 'label' => 'Won', 'value' => $summary['won'], 'value_type' => 'number', 'sub' => 'Awarded tenders'],
            ['key' => 'lost', 'label' => 'Lost', 'value' => $summary['lost'], 'value_type' => 'number', 'sub' => 'Requires loss analysis'],
            ['key' => 'active_value', 'label' => 'Total active tender value', 'value' => $summary['active_value'], 'value_type' => 'money', 'sub' => 'Open tender value'],
            ['key' => 'weighted_pipeline_value', 'label' => 'Weighted pipeline value', 'value' => $summary['weighted_pipeline_value'], 'value_type' => 'money', 'sub' => 'Probability weighted'],
            ['key' => 'win_rate', 'label' => 'Tender win rate', 'value' => $summary['win_rate'], 'value_type' => 'percent', 'sub' => 'Awarded against decisions'],
            ['key' => 'average_preparation_days', 'label' => 'Average preparation time', 'value' => $summary['average_preparation_days'], 'value_type' => 'days', 'sub' => 'Creation to submission'],
        ];
    }

    private function tenderAlerts($tenders): array
    {
        return $tenders->flatMap(function (Tender $tender) {
            $alerts = [];
            $active = ! in_array($tender->status, ['won', 'awarded', 'lost', 'cancelled', 'withdrawn', 'no_bid', 'archived'], true);

            if ($active && $tender->deadline_at && $tender->deadline_at->between(now(), now()->addDays(3))) {
                $alerts[] = $this->alert('deadline_approaching', $tender, 'high', 'Tender Manager');
            }

            if ($active && $tender->documents->where('is_mandatory', true)->whereNotIn('status', ['approved', 'completed'])->count() > 0) {
                $alerts[] = $this->alert('missing_document', $tender, 'high', 'Document Controller');
            }

            if ($active && in_array($tender->status, ['ready_for_submission', 'submitted'], true) && $tender->documents->whereIn('document_type', ['bid_bond', 'bid_security'])->isEmpty() && $tender->records->where('record_type', 'bid_security')->whereIn('status', ['approved', 'completed'])->isEmpty()) {
                $alerts[] = $this->alert('bid_bond_missing', $tender, 'high', 'Finance Reviewer');
            }

            if ($active && in_array($tender->status, ['internal_review', 'awaiting_approval', 'ready_for_submission', 'submitted'], true) && $tender->estimates->whereIn('status', ['approved', 'converted'])->isEmpty()) {
                $alerts[] = $this->alert('estimate_unapproved', $tender, 'high', 'Estimator');
            }

            if ($active && $tender->rfis->whereNotIn('status', ['answered', 'closed', 'responded'])->isNotEmpty()) {
                $alerts[] = $this->alert('unanswered_rfi', $tender, 'medium', 'Tender Manager');
            }

            if ($active && $tender->records->where('record_type', 'addendum')->whereNotIn('status', ['completed', 'approved'])->isNotEmpty()) {
                $alerts[] = $this->alert('addendum_unacknowledged', $tender, 'medium', 'Tender Manager');
            }

            if ($active && $tender->records->where('record_type', 'internal_approval')->where('due_at', '<', now())->whereNotIn('status', ['approved', 'completed'])->isNotEmpty()) {
                $alerts[] = $this->alert('approval_overdue', $tender, 'high', 'Executive Approver');
            }

            if ($tender->status === 'submitted' && $tender->records->where('record_type', 'submission')->whereIn('status', ['submitted', 'completed', 'approved'])->isEmpty()) {
                $alerts[] = $this->alert('receipt_missing', $tender, 'medium', 'Document Controller');
            }

            return $alerts;
        })->values()->all();
    }

    private function alert(string $type, Tender $tender, string $priority, string $owner): array
    {
        $labels = $this->tenderCatalog()['dashboard_alert_types'];

        return [
            'type' => $type,
            'message' => $labels[$type] ?? str($type)->replace('_', ' ')->title()->toString(),
            'tender_id' => $tender->id,
            'tender_number' => $tender->tender_number,
            'tender_title' => $tender->title,
            'priority' => $priority,
            'owner' => $owner,
        ];
    }

    private function tenderAnalytics($tenders): array
    {
        return [
            'status_counts' => $tenders->countBy('status')->map(fn ($count, $status) => ['status' => $status, 'label' => str($status)->replace('_', ' ')->title()->toString(), 'count' => $count])->values(),
            'value_by_month' => $tenders
                ->groupBy(fn (Tender $tender) => ($tender->deadline_at ?? $tender->created_at)->format('M y'))
                ->map(fn ($items, $month) => ['month' => $month, 'value' => round($items->sum(fn (Tender $tender) => (float) $tender->value), 2)])
                ->values(),
            'wins_losses' => [
                ['label' => 'Won', 'value' => $tenders->whereIn('status', ['won', 'awarded'])->count()],
                ['label' => 'Lost', 'value' => $tenders->whereIn('status', ['lost', 'cancelled', 'withdrawn', 'no_bid'])->count()],
            ],
            'loss_reasons' => $tenders
                ->whereIn('status', ['lost', 'cancelled', 'withdrawn', 'no_bid'])
                ->countBy(fn (Tender $tender) => $tender->lost_reason ?: 'Unknown')
                ->map(fn ($count, $reason) => ['reason' => $reason, 'count' => $count])
                ->values(),
            'source_analysis' => $tenders
                ->countBy(fn (Tender $tender) => $tender->opportunity_id ? 'CRM Opportunity' : 'Direct Tender')
                ->map(fn ($count, $source) => ['source' => $source, 'count' => $count])
                ->values(),
            'value_by_estimator' => $tenders->map(function (Tender $tender) {
                $estimator = $tender->records->firstWhere('record_type', 'team_member')?->owner?->name ?? 'Unassigned';

                return ['estimator' => $estimator, 'value' => (float) $tender->value];
            })->groupBy('estimator')->map(fn ($items, $estimator) => ['estimator' => $estimator, 'value' => round($items->sum('value'), 2)])->values(),
        ];
    }

    private function tenderReports($tenders): array
    {
        return [
            'active_tenders' => $tenders
                ->reject(fn (Tender $tender) => in_array($tender->status, ['won', 'awarded', 'lost', 'cancelled', 'withdrawn', 'no_bid', 'archived'], true))
                ->map(fn (Tender $tender) => $this->reportTenderRow($tender))
                ->values(),
            'deadlines' => $tenders
                ->filter(fn (Tender $tender) => $tender->deadline_at)
                ->sortBy('deadline_at')
                ->map(fn (Tender $tender) => [
                    'tender_number' => $tender->tender_number,
                    'title' => $tender->title,
                    'deadline_at' => $tender->deadline_at,
                    'days_left' => $tender->days_left,
                    'status' => $tender->status,
                ])
                ->values(),
            'submitted' => $tenders->where('status', 'submitted')->map(fn (Tender $tender) => $this->reportTenderRow($tender))->values(),
            'awarded' => $tenders->whereIn('status', ['won', 'awarded'])->map(fn (Tender $tender) => $this->reportTenderRow($tender))->values(),
            'lost' => $tenders->whereIn('status', ['lost', 'cancelled', 'withdrawn', 'no_bid'])->map(fn (Tender $tender) => $this->reportTenderRow($tender))->values(),
        ];
    }

    private function reportTenderRow(Tender $tender): array
    {
        return [
            'tender_number' => $tender->tender_number,
            'title' => $tender->title,
            'client' => $tender->client?->name,
            'status' => $tender->status,
            'value' => (float) $tender->value,
            'weighted_value' => (float) $tender->weighted_value,
            'completion_percent' => $tender->completion_percent,
            'source' => $tender->source_label,
            'lost_reason' => $tender->lost_reason,
        ];
    }

    private function tenderCompletion(Tender $tender): int
    {
        $records = $tender->records;
        $checks = [
            (bool) $tender->title,
            (bool) $tender->client_id,
            (bool) $tender->deadline_at,
            (float) $tender->value > 0,
            $records->where('record_type', 'team_member')->isNotEmpty(),
            $tender->documents->isNotEmpty(),
            $tender->estimates->isNotEmpty(),
            $tender->estimates->whereIn('status', ['approved', 'converted'])->isNotEmpty(),
            $records->where('record_type', 'technical_proposal')->whereIn('status', ['approved', 'completed'])->isNotEmpty(),
            $records->where('record_type', 'commercial_proposal')->whereIn('status', ['approved', 'completed'])->isNotEmpty(),
            $records->where('record_type', 'internal_approval')->whereIn('status', ['approved', 'completed'])->isNotEmpty(),
            in_array($tender->status, ['submitted', 'under_evaluation', 'clarification_requested', 'negotiation', 'preferred_bidder', 'won', 'awarded', 'lost'], true),
            in_array($tender->status, ['won', 'awarded', 'lost', 'cancelled', 'withdrawn', 'no_bid'], true),
        ];

        return (int) round((collect($checks)->filter()->count() / count($checks)) * 100);
    }

    private function tenderChecklistSummary(Tender $tender): array
    {
        $items = collect($tender->checklist ?? []);
        $completedRecordTitles = $tender->records
            ->whereIn('status', ['approved', 'completed'])
            ->pluck('title')
            ->map(fn ($title) => strtolower($title));

        $completed = $items->filter(fn ($item) => $completedRecordTitles->contains(strtolower((string) $item)))->count();

        return [
            'total' => $items->count(),
            'completed' => $completed,
            'pending' => max(0, $items->count() - $completed),
        ];
    }

    private function daysLeft(Tender $tender): ?int
    {
        if (! $tender->deadline_at) {
            return null;
        }

        return now()->diffInDays($tender->deadline_at, false);
    }

    private function tenderProbability(string $status): int
    {
        return [
            'draft' => 10,
            'under_review' => 15,
            'bid_decision_pending' => 20,
            'no_bid' => 0,
            'bid_approved' => 35,
            'planning' => 40,
            'estimating' => 45,
            'proposal_preparation' => 50,
            'internal_review' => 55,
            'awaiting_approval' => 60,
            'ready_for_submission' => 65,
            'pending' => 55,
            'submitted' => 65,
            'under_evaluation' => 70,
            'clarification_requested' => 72,
            'negotiation' => 80,
            'preferred_bidder' => 88,
            'won' => 100,
            'awarded' => 100,
            'lost' => 0,
            'cancelled' => 0,
            'withdrawn' => 0,
            'archived' => 0,
        ][$status] ?? 25;
    }

    private function assertTenant(Request $request, object $model): void
    {
        abort_if((int) $model->company_id !== $this->companyId($request), 404);
    }
}
