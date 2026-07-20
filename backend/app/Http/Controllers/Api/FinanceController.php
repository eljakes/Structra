<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinanceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $receivables = Invoice::query()
            ->forCompany($companyId)
            ->whereNotIn('status', ['draft', 'void'])
            ->selectRaw('coalesce(sum(total_amount), 0) as invoiced')
            ->selectRaw('coalesce(sum(amount_paid), 0) as paid')
            ->selectRaw('coalesce(sum(balance_due), 0) as outstanding')
            ->first();

        return response()->json([
            'summary' => [
                'invoiced' => (float) ($receivables->invoiced ?? 0),
                'paid' => (float) ($receivables->paid ?? 0),
                'outstanding' => (float) ($receivables->outstanding ?? 0),
                'overdue' => (float) Invoice::query()
                    ->forCompany($companyId)
                    ->whereNotIn('payment_status', ['paid'])
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->sum('balance_due'),
                'approved_expenses' => (float) Expense::query()
                    ->forCompany($companyId)
                    ->whereIn('status', ['approved', 'paid'])
                    ->sum(DB::raw('amount + tax_amount')),
            ],
            'invoices' => Invoice::query()->forCompany($companyId)->with(['client', 'project:id,code,name', 'lines', 'payments'])->latest()->limit(100)->get(),
            'payments' => Payment::query()->forCompany($companyId)->with(['invoice:id,invoice_number,title', 'client:id,name'])->latest('received_at')->limit(100)->get(),
            'expenses' => Expense::query()->forCompany($companyId)->with(['project:id,code,name', 'supplier:id,name'])->latest()->limit(100)->get(),
            'journal_entries' => JournalEntry::query()->forCompany($companyId)->with('lines')->latest('entry_date')->limit(80)->get(),
        ]);
    }

    public function storeInvoice(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.cost_code' => ['nullable', 'string', 'max:40'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit' => ['nullable', 'string', 'max:24'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $project = null;
        $branchId = $data['branch_id'] ?? $this->user($request)->branch_id;
        $clientId = $data['client_id'] ?? null;

        if (! empty($data['project_id'])) {
            $project = Project::query()->forCompany($companyId)->whereKey($data['project_id'])->firstOrFail();
            $branchId = $project->branch_id;
            $clientId = $clientId ?: $project->client_id;
        }

        Branch::query()->forCompany($companyId)->whereKey($branchId)->firstOrFail();

        if ($clientId) {
            Client::query()->forCompany($companyId)->whereKey($clientId)->firstOrFail();
        }

        $invoice = DB::transaction(function () use ($request, $companyId, $data, $branchId, $clientId, $project) {
            $invoice = Invoice::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'project_id' => $project?->id,
                'client_id' => $clientId,
                'invoice_number' => $this->nextNumber('INV', Invoice::class, 'invoice_number', $companyId),
                'title' => $data['title'],
                'status' => 'draft',
                'issue_date' => $data['issue_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
                'notes' => $data['notes'] ?? null,
                'created_by' => $this->user($request)->id,
                'updated_by' => $this->user($request)->id,
            ]);

            foreach ($data['lines'] as $line) {
                InvoiceLine::query()->create([
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    ...$this->invoiceLinePayload($line),
                ]);
            }

            $this->syncInvoiceTotals($invoice);

            return $invoice;
        });

        return response()->json(['invoice' => $invoice->fresh(['client', 'project', 'lines', 'payments'])], 201);
    }

    public function addInvoiceLine(Request $request, Invoice $invoice): JsonResponse
    {
        $this->assertTenant($request, $invoice);
        abort_if(! in_array($invoice->status, ['draft', 'issued'], true), 422, 'Invoice lines can only be added to draft or issued invoices.');

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'cost_code' => ['nullable', 'string', 'max:40'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['nullable', 'string', 'max:24'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $line = DB::transaction(function () use ($data, $invoice) {
            $line = InvoiceLine::query()->create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                ...$this->invoiceLinePayload($data),
            ]);

            $this->syncInvoiceTotals($invoice);

            return $line;
        });

        return response()->json(['line' => $line, 'invoice' => $invoice->fresh(['lines', 'payments'])], 201);
    }

    public function issueInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $this->assertTenant($request, $invoice);
        abort_if($invoice->lines()->count() === 0, 422, 'Invoice requires at least one line.');
        abort_if(! in_array($invoice->status, ['draft', 'issued'], true), 422, 'Invoice cannot be issued from its current status.');

        $data = $request->validate([
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $invoice->update([
            'status' => 'issued',
            'issue_date' => $invoice->issue_date ?: now()->toDateString(),
            'due_date' => $data['due_date'] ?? $invoice->due_date,
            'notes' => $data['notes'] ?? $invoice->notes,
            'issued_by' => $this->user($request)->id,
            'issued_at' => now(),
            'updated_by' => $this->user($request)->id,
        ]);

        $this->syncInvoiceTotals($invoice);

        return response()->json(['invoice' => $invoice->fresh(['client', 'project', 'lines', 'payments'])]);
    }

    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $this->assertTenant($request, $invoice);
        abort_if(in_array($invoice->status, ['draft', 'void'], true), 422, 'Payments can only be recorded against issued invoices.');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'card', 'mobile_money', 'cheque'])],
            'reference' => ['nullable', 'string', 'max:120'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->syncInvoiceTotals($invoice);
        abort_if((float) $data['amount'] > (float) $invoice->fresh()->balance_due + 0.01, 422, 'Payment exceeds the invoice balance.');

        $payment = DB::transaction(function () use ($request, $invoice, $data) {
            $payment = Payment::query()->create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'payment_number' => $this->nextNumber('PAY', Payment::class, 'payment_number', $invoice->company_id),
                'amount' => $data['amount'],
                'currency' => $invoice->currency,
                'method' => $data['method'] ?? 'bank_transfer',
                'reference' => $data['reference'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'received_by' => $this->user($request)->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncInvoiceTotals($invoice);

            return $payment;
        });

        return response()->json(['payment' => $payment, 'invoice' => $invoice->fresh(['payments', 'lines'])], 201);
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:255'],
            'cost_code' => ['nullable', 'string', 'max:40'],
            'amount' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'incurred_on' => ['nullable', 'date'],
        ]);

        $branchId = $data['branch_id'] ?? $this->user($request)->branch_id;

        if (! empty($data['project_id'])) {
            $project = Project::query()->forCompany($companyId)->whereKey($data['project_id'])->firstOrFail();
            $branchId = $project->branch_id;
        }

        Branch::query()->forCompany($companyId)->whereKey($branchId)->firstOrFail();

        if (! empty($data['supplier_id'])) {
            Supplier::query()->forCompany($companyId)->whereKey($data['supplier_id'])->firstOrFail();
        }

        $expense = Expense::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'expense_number' => $this->nextNumber('EXP', Expense::class, 'expense_number', $companyId),
            'category' => $data['category'] ?? 'site_cost',
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            'status' => 'submitted',
            'submitted_by' => $this->user($request)->id,
            ...collect($data)->except(['branch_id', 'category', 'currency'])->all(),
        ]);

        return response()->json(['expense' => $expense->load(['project', 'supplier'])], 201);
    }

    public function reviewExpense(Request $request, Expense $expense): JsonResponse
    {
        $this->assertTenant($request, $expense);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'paid'])],
        ]);

        $allowed = [
            'submitted' => ['approved', 'rejected'],
            'approved' => ['paid'],
            'paid' => [],
            'rejected' => [],
        ];

        abort_if(! in_array($data['status'], $allowed[$expense->status] ?? [], true), 422, 'Invalid expense transition.');

        $updates = [
            'status' => $data['status'],
            'approved_by' => $data['status'] === 'approved' ? $this->user($request)->id : $expense->approved_by,
            'approved_at' => $data['status'] === 'approved' ? now() : $expense->approved_at,
        ];

        if ($data['status'] === 'paid') {
            $updates['paid_at'] = now();
        }

        $expense->update($updates);

        return response()->json(['expense' => $expense->fresh(['project', 'supplier'])]);
    }

    public function storeJournalEntry(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['nullable', Rule::in(['draft', 'posted'])],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.project_id' => ['nullable', 'integer'],
            'lines.*.account_code' => ['required', 'string', 'max:40'],
            'lines.*.account_name' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! empty($data['branch_id'])) {
            Branch::query()->forCompany($companyId)->whereKey($data['branch_id'])->firstOrFail();
        }

        $totalDebit = collect($data['lines'])->sum(fn (array $line) => (float) ($line['debit'] ?? 0));
        $totalCredit = collect($data['lines'])->sum(fn (array $line) => (float) ($line['credit'] ?? 0));

        abort_if(abs($totalDebit - $totalCredit) > 0.01, 422, 'Journal entry must balance debits and credits.');
        abort_if($totalDebit <= 0, 422, 'Journal entry requires debit and credit amounts.');

        $entry = DB::transaction(function () use ($request, $companyId, $data, $totalDebit, $totalCredit) {
            $entry = JournalEntry::query()->create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? $this->user($request)->branch_id,
                'entry_number' => $this->nextNumber('JE', JournalEntry::class, 'entry_number', $companyId),
                'entry_date' => $data['entry_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'posted_by' => ($data['status'] ?? 'draft') === 'posted' ? $this->user($request)->id : null,
                'posted_at' => ($data['status'] ?? 'draft') === 'posted' ? now() : null,
                'created_by' => $this->user($request)->id,
            ]);

            foreach ($data['lines'] as $line) {
                if (! empty($line['project_id'])) {
                    Project::query()->forCompany($companyId)->whereKey($line['project_id'])->firstOrFail();
                }

                JournalLine::query()->create([
                    'company_id' => $companyId,
                    'journal_entry_id' => $entry->id,
                    'project_id' => $line['project_id'] ?? null,
                    'account_code' => $line['account_code'],
                    'account_name' => $line['account_name'],
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                ]);
            }

            return $entry;
        });

        return response()->json(['journal_entry' => $entry->fresh('lines')], 201);
    }

    private function invoiceLinePayload(array $line): array
    {
        $quantity = (float) $line['quantity'];
        $unitPrice = (float) $line['unit_price'];
        $taxRate = (float) ($line['tax_rate'] ?? 0);
        $subtotal = round($quantity * $unitPrice, 2);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        return [
            'description' => $line['description'],
            'cost_code' => $line['cost_code'] ?? null,
            'quantity' => $quantity,
            'unit' => $line['unit'] ?? 'each',
            'unit_price' => $unitPrice,
            'tax_rate' => $taxRate,
            'line_subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'line_total' => $subtotal + $taxAmount,
        ];
    }

    private function syncInvoiceTotals(Invoice $invoice): void
    {
        $subtotal = (float) $invoice->lines()->sum('line_subtotal');
        $taxAmount = (float) $invoice->lines()->sum('tax_amount');
        $total = $subtotal + $taxAmount;
        $paid = (float) $invoice->payments()->sum('amount');
        $balance = max(0, $total - $paid);

        $paymentStatus = match (true) {
            $paid <= 0 => 'unpaid',
            $balance <= 0.01 => 'paid',
            default => 'partial',
        };

        $invoice->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'amount_paid' => $paid,
            'balance_due' => $balance,
            'payment_status' => $paymentStatus,
            'paid_at' => $paymentStatus === 'paid' ? ($invoice->paid_at ?: now()) : null,
        ])->save();
    }

    private function assertTenant(Request $request, object $model): void
    {
        abort_if((int) $model->company_id !== $this->companyId($request), 404);
    }
}
