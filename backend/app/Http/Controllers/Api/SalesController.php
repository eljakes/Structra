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
use App\Models\TenderRfi;
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

        return response()->json([
            'leads' => Lead::query()->forCompany($companyId)->with(['client', 'opportunity'])->latest()->get(),
            'opportunities' => Opportunity::query()->forCompany($companyId)->with(['client', 'lead', 'tenders'])->latest()->get(),
            'tenders' => Tender::query()->forCompany($companyId)->with(['client', 'opportunity', 'estimates.lines', 'rfis', 'documents'])->latest()->get(),
            'estimates' => Estimate::query()->forCompany($companyId)->with(['tender', 'lines'])->latest()->get(),
            'pricing_items' => PricingItem::query()->forCompany($companyId)->where('active', true)->orderBy('category')->orderBy('description')->get(),
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

    public function createTenderFromOpportunity(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->assertTenant($request, $opportunity);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'deadline_at' => ['nullable', 'date'],
            'checklist' => ['nullable', 'array'],
        ]);

        $tender = Tender::query()->create([
            'company_id' => $opportunity->company_id,
            'branch_id' => $opportunity->branch_id,
            'client_id' => $opportunity->client_id,
            'opportunity_id' => $opportunity->id,
            'tender_number' => $this->nextNumber('TND', Tender::class, 'tender_number', $opportunity->company_id),
            'title' => $data['title'] ?? $opportunity->name,
            'status' => 'draft',
            'deadline_at' => $data['deadline_at'] ?? null,
            'value' => $opportunity->estimated_value,
            'currency' => $opportunity->currency,
            'checklist' => $data['checklist'] ?? ['BOQ', 'Specifications', 'Drawings', 'Commercial return'],
            'created_by' => $this->user($request)->id,
            'updated_by' => $this->user($request)->id,
        ]);

        $opportunity->update(['stage' => 'tender']);

        return response()->json(['tender' => $tender->load(['client', 'opportunity'])], 201);
    }

    public function updateTender(Request $request, Tender $tender): JsonResponse
    {
        $this->assertTenant($request, $tender);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'deadline_at' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['draft', 'submitted', 'pending', 'won', 'lost'])],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'checklist' => ['nullable', 'array'],
            'lost_reason' => ['nullable', 'string', 'max:4000'],
        ]);

        $tender->update([...$data, 'updated_by' => $this->user($request)->id]);

        return response()->json(['tender' => $tender->fresh(['client', 'opportunity', 'estimates.lines', 'rfis', 'documents'])]);
    }

    public function submitTender(Request $request, Tender $tender): JsonResponse
    {
        $this->assertTenant($request, $tender);
        abort_if(! in_array($tender->status, ['draft', 'pending'], true), 422, 'Only draft or pending tenders can be submitted.');

        $tender->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'updated_by' => $this->user($request)->id,
        ]);

        return response()->json(['tender' => $tender->fresh(['estimates.lines'])]);
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

        return response()->json(['tender' => $tender->fresh()]);
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
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            ...$data,
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
            'question' => ['required', 'string', 'max:4000'],
            'due_at' => ['nullable', 'date'],
        ]);

        $rfi = TenderRfi::query()->create([
            'company_id' => $tender->company_id,
            'tender_id' => $tender->id,
            'asked_by' => $this->user($request)->id,
            ...$data,
        ]);

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

        return response()->json(['rfi' => $rfi->fresh()]);
    }

    public function uploadTenderDocument(Request $request, Tender $tender): JsonResponse
    {
        $this->assertTenant($request, $tender);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'file' => ['nullable', 'file', 'max:51200'],
        ]);

        $filePayload = [];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store("structra/companies/{$tender->company_id}/tenders/{$tender->id}", 'local');
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
            ...$filePayload,
        ]);

        return response()->json(['document' => $document], 201);
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

        return EstimateLine::query()->create([
            'company_id' => $estimate->company_id,
            'estimate_id' => $estimate->id,
            'pricing_item_id' => $pricingItem?->id,
            'cost_code' => $line['cost_code'] ?? $pricingItem?->cost_code,
            'description' => $line['description'] ?? $pricingItem?->description,
            'category' => $line['category'] ?? $pricingItem?->category ?? 'materials',
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

    private function assertTenant(Request $request, object $model): void
    {
        abort_if((int) $model->company_id !== $this->companyId($request), 404);
    }
}
