<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PeopleController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        return response()->json([
            'employees' => EmployeeProfile::query()->forCompany($companyId)->with(['user:id,name,email,job_title', 'branch:id,name,code'])->orderBy('employee_number')->get(),
            'leave_requests' => LeaveRequest::query()->forCompany($companyId)->with(['employeeProfile.user:id,name,email'])->latest()->limit(100)->get(),
            'payroll_runs' => PayrollRun::query()->forCompany($companyId)->with(['payslips.employeeProfile.user:id,name,email'])->latest('period_end')->limit(50)->get(),
            'summary' => [
                'active_employees' => EmployeeProfile::query()->forCompany($companyId)->where('status', 'active')->count(),
                'pending_leave' => LeaveRequest::query()->forCompany($companyId)->where('status', 'pending')->count(),
                'draft_payroll' => PayrollRun::query()->forCompany($companyId)->where('status', 'draft')->count(),
                'payroll_liability' => (float) PayrollRun::query()->forCompany($companyId)->whereIn('status', ['draft', 'approved'])->sum('net_pay'),
            ],
        ]);
    }

    public function storeEmployeeProfile(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'user_id' => ['required', 'integer', Rule::unique('employee_profiles')->where('company_id', $companyId)],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'casual'])],
            'department' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'hire_date' => ['nullable', 'date'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::query()->where('company_id', $companyId)->whereKey($data['user_id'])->firstOrFail();
        $branchId = $data['branch_id'] ?? $user->branch_id ?? $this->user($request)->branch_id;
        Branch::query()->forCompany($companyId)->whereKey($branchId)->firstOrFail();

        $employee = EmployeeProfile::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'employee_number' => $this->nextNumber('EMP', EmployeeProfile::class, 'employee_number', $companyId),
            'employment_type' => $data['employment_type'] ?? 'full_time',
            'department' => $data['department'] ?? 'operations',
            'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
            'status' => 'active',
            ...collect($data)->except(['branch_id', 'employment_type', 'department', 'currency'])->all(),
        ]);

        return response()->json(['employee' => $employee->load(['user', 'branch'])], 201);
    }

    public function storeLeaveRequest(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'employee_profile_id' => ['required', 'integer'],
            'leave_type' => ['nullable', Rule::in(['annual', 'sick', 'unpaid', 'maternity', 'paternity', 'compassionate'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'days' => ['nullable', 'numeric', 'min:0.5'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = EmployeeProfile::query()->forCompany($companyId)->whereKey($data['employee_profile_id'])->firstOrFail();
        $days = $data['days'] ?? Carbon::parse($data['starts_on'])->diffInDays(Carbon::parse($data['ends_on'])) + 1;

        $leave = LeaveRequest::query()->create([
            'company_id' => $companyId,
            'employee_profile_id' => $employee->id,
            'user_id' => $employee->user_id,
            'leave_type' => $data['leave_type'] ?? 'annual',
            'status' => 'pending',
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'days' => $days,
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['leave_request' => $leave->load('employeeProfile.user')], 201);
    }

    public function reviewLeaveRequest(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->assertTenant($request, $leaveRequest);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $allowed = [
            'pending' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['cancelled'],
            'rejected' => [],
            'cancelled' => [],
        ];

        abort_if(! in_array($data['status'], $allowed[$leaveRequest->status] ?? [], true), 422, 'Invalid leave request transition.');

        $leaveRequest->update([
            'status' => $data['status'],
            'reviewed_by' => $this->user($request)->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        return response()->json(['leave_request' => $leaveRequest->fresh('employeeProfile.user')]);
    }

    public function storePayrollRun(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payslips' => ['nullable', 'array'],
            'payslips.*.employee_profile_id' => ['required_with:payslips', 'integer'],
            'payslips.*.gross_pay' => ['nullable', 'numeric', 'min:0'],
            'payslips.*.overtime_pay' => ['nullable', 'numeric', 'min:0'],
            'payslips.*.allowances' => ['nullable', 'numeric', 'min:0'],
            'payslips.*.deductions' => ['nullable', 'numeric', 'min:0'],
            'payslips.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! empty($data['branch_id'])) {
            Branch::query()->forCompany($companyId)->whereKey($data['branch_id'])->firstOrFail();
        }

        $payslipLines = $data['payslips'] ?? [];

        if ($payslipLines === []) {
            $employees = EmployeeProfile::query()
                ->forCompany($companyId)
                ->when($data['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->where('status', 'active')
                ->get();

            abort_if($employees->isEmpty(), 422, 'No active employees available for this payroll run.');

            $payslipLines = $employees->map(fn (EmployeeProfile $employee) => [
                'employee_profile_id' => $employee->id,
                'gross_pay' => (float) $employee->base_salary,
                'overtime_pay' => 0,
                'allowances' => 0,
                'deductions' => 0,
                'tax_amount' => 0,
            ])->all();
        }

        $run = DB::transaction(function () use ($request, $companyId, $data, $payslipLines) {
            $run = PayrollRun::query()->create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'run_number' => $this->nextNumber('PAYRUN', PayrollRun::class, 'run_number', $companyId),
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'status' => 'draft',
                'currency' => strtoupper($data['currency'] ?? $this->user($request)->company->default_currency),
                'created_by' => $this->user($request)->id,
            ]);

            foreach ($payslipLines as $line) {
                $employee = EmployeeProfile::query()->forCompany($companyId)->whereKey($line['employee_profile_id'])->firstOrFail();
                $gross = (float) ($line['gross_pay'] ?? $employee->base_salary);
                $overtime = (float) ($line['overtime_pay'] ?? 0);
                $allowances = (float) ($line['allowances'] ?? 0);
                $deductions = (float) ($line['deductions'] ?? 0);
                $tax = (float) ($line['tax_amount'] ?? 0);
                $grossTotal = $gross + $overtime + $allowances;
                $deductionTotal = $deductions + $tax;

                Payslip::query()->create([
                    'company_id' => $companyId,
                    'payroll_run_id' => $run->id,
                    'employee_profile_id' => $employee->id,
                    'user_id' => $employee->user_id,
                    'gross_pay' => $gross,
                    'overtime_pay' => $overtime,
                    'allowances' => $allowances,
                    'deductions' => $deductions,
                    'tax_amount' => $tax,
                    'net_pay' => max(0, $grossTotal - $deductionTotal),
                    'status' => 'draft',
                    'metadata' => ['period' => [$data['period_start'], $data['period_end']]],
                ]);
            }

            $this->syncPayrollTotals($run);

            return $run;
        });

        return response()->json(['payroll_run' => $run->fresh('payslips.employeeProfile.user')], 201);
    }

    public function approvePayrollRun(Request $request, PayrollRun $payrollRun): JsonResponse
    {
        $this->assertTenant($request, $payrollRun);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['approved', 'paid'])],
        ]);

        $target = $data['status'] ?? 'approved';
        $allowed = [
            'draft' => ['approved'],
            'approved' => ['paid'],
            'paid' => [],
        ];

        abort_if(! in_array($target, $allowed[$payrollRun->status] ?? [], true), 422, 'Invalid payroll transition.');

        $updates = ['status' => $target];

        if ($target === 'approved') {
            $updates['approved_by'] = $this->user($request)->id;
            $updates['approved_at'] = now();
        }

        if ($target === 'paid') {
            $updates['paid_at'] = now();
        }

        DB::transaction(function () use ($payrollRun, $target, $updates) {
            $payrollRun->update($updates);
            $payrollRun->payslips()->update([
                'status' => $target,
                'paid_at' => $target === 'paid' ? now() : null,
            ]);
        });

        return response()->json(['payroll_run' => $payrollRun->fresh('payslips.employeeProfile.user')]);
    }

    private function syncPayrollTotals(PayrollRun $run): void
    {
        $payslips = $run->payslips()->get();

        $run->forceFill([
            'gross_pay' => $payslips->sum(fn (Payslip $payslip) => (float) $payslip->gross_pay + (float) $payslip->overtime_pay + (float) $payslip->allowances),
            'total_deductions' => $payslips->sum(fn (Payslip $payslip) => (float) $payslip->deductions + (float) $payslip->tax_amount),
            'net_pay' => $payslips->sum(fn (Payslip $payslip) => (float) $payslip->net_pay),
        ])->save();
    }

    private function assertTenant(Request $request, object $model): void
    {
        abort_if((int) $model->company_id !== $this->companyId($request), 404);
    }
}
