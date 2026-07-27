<?php

namespace App\Services;

use App\Models\FinanceAccount;
use App\Models\FinanceBankAccount;
use App\Models\FinanceCostCenter;
use App\Models\FinanceLedgerEntry;
use App\Models\PayrollRun;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;

class FinancePostingService
{
    public function syncPayrollRunsForCompany(int $companyId, int $userId): void
    {
        PayrollRun::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['approved', 'paid'])
            ->with('payslips')
            ->chunkById(100, function ($runs) use ($userId): void {
                foreach ($runs as $run) {
                    $this->postPayrollApproval($run, $userId);

                    if ($run->status === 'paid') {
                        $this->postPayrollPayment($run, $userId);
                    }
                }
            });
    }

    public function attachPayrollFinanceStatus(PayrollRun $run): PayrollRun
    {
        $approvalPosted = $this->ledgerExists(PayrollRun::class, $run->id);
        $paymentPosted = $this->ledgerExists(PayrollRun::class.'#payment', $run->id);

        $financeStatus = match (true) {
            $run->status === 'draft' => 'forecast_in_finance',
            $run->status === 'approved' && $approvalPosted => 'approved_posted',
            $run->status === 'approved' => 'pending_finance_posting',
            $run->status === 'paid' && $approvalPosted && $paymentPosted => 'paid_posted',
            $run->status === 'paid' && $approvalPosted => 'payment_pending_posting',
            default => 'not_linked',
        };

        $run->setAttribute('finance_linked', $financeStatus !== 'not_linked' && ! str_contains($financeStatus, 'pending'));
        $run->setAttribute('finance_status', $financeStatus);
        $run->setAttribute('finance_posting', [
            'approval_posted' => $approvalPosted,
            'payment_posted' => $paymentPosted,
            'status' => $financeStatus,
        ]);

        return $run;
    }

    public function postSupplierInvoiceApproval(SupplierInvoice $invoice, int $userId): void
    {
        if ($this->ledgerExists(SupplierInvoice::class, $invoice->id)) {
            return;
        }

        $this->ensureFoundation($invoice->company_id);
        $date = $invoice->approved_at?->toDateString() ?? now()->toDateString();
        $amount = (float) $invoice->total_amount;

        $this->postLine($invoice->company_id, '5000', SupplierInvoice::class, $invoice->id, $date, $invoice->invoice_number, 'Supplier invoice approved', $amount, 0, $invoice->project_id, $invoice->currency, $userId);
        $this->postLine($invoice->company_id, '2000', SupplierInvoice::class, $invoice->id, $date, $invoice->invoice_number, 'Supplier invoice payable', 0, $amount, $invoice->project_id, $invoice->currency, $userId);
    }

    public function postSupplierPayment(SupplierPayment $payment, SupplierInvoice $invoice, int $userId, ?int $bankAccountId = null): SupplierPayment
    {
        if ($this->ledgerExists(SupplierPayment::class, $payment->id)) {
            return $payment;
        }

        $this->ensureFoundation($invoice->company_id);
        $bankAccount = $this->bankAccount($invoice->company_id, $invoice->branch_id, $invoice->currency, $bankAccountId);

        if (! $payment->finance_bank_account_id) {
            $payment->forceFill(['finance_bank_account_id' => $bankAccount->id])->save();
        }

        $bankAccount->decrement('current_balance', (float) $payment->amount);

        $date = $payment->payment_date?->toDateString() ?? now()->toDateString();
        $this->postLine($invoice->company_id, '2000', SupplierPayment::class, $payment->id, $date, $payment->payment_number, 'Supplier payment', (float) $payment->amount, 0, $invoice->project_id, $invoice->currency, $userId);
        $this->postLine($invoice->company_id, '1000', SupplierPayment::class, $payment->id, $date, $payment->payment_number, 'Supplier payment', 0, (float) $payment->amount, $invoice->project_id, $invoice->currency, $userId);

        return $payment->fresh();
    }

    public function postPayrollApproval(PayrollRun $run, int $userId): void
    {
        if ($this->ledgerExists(PayrollRun::class, $run->id)) {
            return;
        }

        $this->ensureFoundation($run->company_id);
        $date = $run->approved_at?->toDateString() ?? now()->toDateString();

        if ((float) $run->gross_pay > 0) {
            $this->postLine($run->company_id, '5100', PayrollRun::class, $run->id, $date, $run->run_number, 'Payroll approved', (float) $run->gross_pay, 0, null, $run->currency, $userId);
        }

        if ((float) $run->total_deductions > 0) {
            $this->postLine($run->company_id, '2100', PayrollRun::class, $run->id, $date, $run->run_number, 'Payroll deductions payable', 0, (float) $run->total_deductions, null, $run->currency, $userId);
        }

        if ((float) $run->net_pay > 0) {
            $this->postLine($run->company_id, '2300', PayrollRun::class, $run->id, $date, $run->run_number, 'Net payroll payable', 0, (float) $run->net_pay, null, $run->currency, $userId);
        }
    }

    public function postPayrollPayment(PayrollRun $run, int $userId, ?int $bankAccountId = null): void
    {
        $sourceType = PayrollRun::class.'#payment';
        if ($this->ledgerExists($sourceType, $run->id)) {
            return;
        }

        $this->ensureFoundation($run->company_id);
        $bankAccount = $this->bankAccount($run->company_id, $run->branch_id, $run->currency, $bankAccountId);
        $bankAccount->decrement('current_balance', (float) $run->net_pay);
        $date = $run->paid_at?->toDateString() ?? now()->toDateString();

        $this->postLine($run->company_id, '2300', $sourceType, $run->id, $date, $run->run_number, 'Payroll paid', (float) $run->net_pay, 0, null, $run->currency, $userId);
        $this->postLine($run->company_id, '1000', $sourceType, $run->id, $date, $run->run_number, 'Payroll paid', 0, (float) $run->net_pay, null, $run->currency, $userId);
    }

    private function ensureFoundation(int $companyId): void
    {
        foreach ([
            ['1000', 'Cash and Bank', 'asset', 'debit', true],
            ['2000', 'Accounts Payable', 'liability', 'credit', true],
            ['2100', 'Tax Payable', 'liability', 'credit', true],
            ['2300', 'Payroll Payable', 'liability', 'credit', true],
            ['5000', 'Direct Project Costs', 'expense', 'debit', false],
            ['5100', 'Salaries and Wages', 'expense', 'debit', false],
        ] as [$code, $name, $type, $normal, $control]) {
            FinanceAccount::query()->firstOrCreate(
                ['company_id' => $companyId, 'account_code' => $code],
                [
                    'account_name' => $name,
                    'account_type' => $type,
                    'normal_balance' => $normal,
                    'is_control_account' => $control,
                    'is_active' => true,
                ]
            );
        }
    }

    private function postLine(int $companyId, string $accountCode, string $sourceType, int $sourceId, string $entryDate, ?string $reference, ?string $description, float $debit, float $credit, ?int $projectId, string $currency, int $userId): void
    {
        if ($debit <= 0 && $credit <= 0) {
            return;
        }

        $account = FinanceAccount::query()->where('company_id', $companyId)->where('account_code', $accountCode)->firstOrFail();
        $costCenter = $projectId ? FinanceCostCenter::query()->where('company_id', $companyId)->where('project_id', $projectId)->first() : null;
        $lastBalance = (float) FinanceLedgerEntry::query()->where('company_id', $companyId)->where('finance_account_id', $account->id)->latest('id')->value('running_balance');
        $delta = $account->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;

        FinanceLedgerEntry::query()->create([
            'company_id' => $companyId,
            'finance_account_id' => $account->id,
            'project_id' => $projectId,
            'cost_center_id' => $costCenter?->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'entry_date' => $entryDate,
            'reference' => $reference,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
            'running_balance' => $lastBalance + $delta,
            'currency' => strtoupper($currency),
            'created_by' => $userId,
        ]);
    }

    private function bankAccount(int $companyId, ?int $branchId, string $currency, ?int $bankAccountId): FinanceBankAccount
    {
        if ($bankAccountId) {
            return FinanceBankAccount::query()->where('company_id', $companyId)->whereKey($bankAccountId)->firstOrFail();
        }

        return FinanceBankAccount::query()->where('company_id', $companyId)->where('is_default', true)->first()
            ?? FinanceBankAccount::query()->where('company_id', $companyId)->where('status', 'active')->first()
            ?? FinanceBankAccount::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'account_name' => 'Main Operating Account',
                'bank_name' => 'Primary Bank',
                'currency' => strtoupper($currency),
                'is_default' => true,
                'status' => 'active',
            ]);
    }

    private function ledgerExists(string $sourceType, int $sourceId): bool
    {
        return FinanceLedgerEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }
}
