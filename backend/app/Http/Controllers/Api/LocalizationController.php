<?php

namespace App\Http\Controllers\Api;

use App\Models\CompanyLocalizationSetting;
use App\Models\ExchangeRate;
use App\Models\LocalizationCountry;
use App\Models\TaxRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocalizationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $settings = $this->settings($request);

        return response()->json([
            'settings' => $settings,
            'countries' => LocalizationCountry::query()->where('is_active', true)->orderBy('name')->get(),
            'tax_rates' => TaxRate::query()
                ->where(fn ($query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))
                ->orderBy('country')
                ->orderByDesc('effective_from')
                ->get(),
            'exchange_rates' => ExchangeRate::query()
                ->where(fn ($query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))
                ->orderByDesc('rate_date')
                ->limit(100)
                ->get(),
            'currencies' => LocalizationCountry::query()->where('is_active', true)->distinct()->orderBy('currency')->pluck('currency')->values(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'base_country' => ['required', 'string', 'size:2'],
            'base_currency' => ['required', 'string', 'size:3'],
            'enabled_countries' => ['nullable', 'array'],
            'enabled_currencies' => ['nullable', 'array'],
            'tax_rounding_mode' => ['nullable', Rule::in(['line', 'document'])],
            'date_format' => ['nullable', 'string', 'max:40'],
        ]);

        LocalizationCountry::query()->where('iso2', strtoupper($data['base_country']))->firstOrFail();

        $settings = CompanyLocalizationSetting::query()->updateOrCreate(
            ['company_id' => $companyId],
            [
                'base_country' => strtoupper($data['base_country']),
                'base_currency' => strtoupper($data['base_currency']),
                'enabled_countries' => array_values(array_unique(array_map('strtoupper', $data['enabled_countries'] ?? [strtoupper($data['base_country'])]))),
                'enabled_currencies' => array_values(array_unique(array_map('strtoupper', $data['enabled_currencies'] ?? [strtoupper($data['base_currency'])]))),
                'tax_rounding_mode' => $data['tax_rounding_mode'] ?? 'line',
                'date_format' => $data['date_format'] ?? 'Y-m-d',
            ],
        );

        $this->user($request)->company->update([
            'country' => $settings->base_country,
            'default_currency' => $settings->base_currency,
        ]);

        return response()->json(['settings' => $settings]);
    }

    public function storeTaxRate(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'name' => ['required', 'string', 'max:120'],
            'tax_type' => ['nullable', 'string', 'max:40'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        LocalizationCountry::query()->where('iso2', strtoupper($data['country']))->firstOrFail();

        if ($data['is_default'] ?? false) {
            TaxRate::query()
                ->where('company_id', $companyId)
                ->where('country', strtoupper($data['country']))
                ->where('tax_type', $data['tax_type'] ?? 'vat')
                ->update(['is_default' => false]);
        }

        $taxRate = TaxRate::query()->create([
            'company_id' => $companyId,
            'country' => strtoupper($data['country']),
            'name' => $data['name'],
            'tax_type' => $data['tax_type'] ?? 'vat',
            'rate_percent' => $data['rate_percent'],
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'effective_to' => $data['effective_to'] ?? null,
            'is_default' => $data['is_default'] ?? false,
        ]);

        return response()->json(['tax_rate' => $taxRate], 201);
    }

    public function storeExchangeRate(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'base_currency' => ['required', 'string', 'size:3'],
            'quote_currency' => ['required', 'string', 'size:3', 'different:base_currency'],
            'rate' => ['required', 'numeric', 'min:0.00000001'],
            'rate_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:80'],
        ]);

        $rate = ExchangeRate::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'base_currency' => strtoupper($data['base_currency']),
                'quote_currency' => strtoupper($data['quote_currency']),
                'rate_date' => $data['rate_date'] ?? now()->toDateString(),
            ],
            [
                'rate' => $data['rate'],
                'source' => $data['source'] ?? 'manual',
            ],
        );

        return response()->json(['exchange_rate' => $rate], 201);
    }

    public function convertCurrency(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'amount' => ['required', 'numeric'],
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'rate_date' => ['nullable', 'date'],
        ]);

        $from = strtoupper($data['from_currency']);
        $to = strtoupper($data['to_currency']);
        $amount = (float) $data['amount'];
        $rateDate = $data['rate_date'] ?? now()->toDateString();
        $rate = $this->rate($companyId, $from, $to, $rateDate);

        return response()->json([
            'conversion' => [
                'amount' => $amount,
                'from_currency' => $from,
                'to_currency' => $to,
                'rate' => $rate,
                'converted_amount' => round($amount * $rate, 2),
                'rate_date' => $rateDate,
            ],
        ]);
    }

    public function calculateTax(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'country' => ['nullable', 'string', 'size:2'],
            'tax_type' => ['nullable', 'string', 'max:40'],
            'tax_rate_id' => ['nullable', 'integer'],
            'tax_date' => ['nullable', 'date'],
        ]);

        $settings = $this->settings($request);
        $country = strtoupper($data['country'] ?? $settings->base_country);
        $taxType = $data['tax_type'] ?? 'vat';
        $taxDate = $data['tax_date'] ?? now()->toDateString();

        $taxRate = ! empty($data['tax_rate_id'])
            ? TaxRate::query()->where(fn ($query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))->whereKey($data['tax_rate_id'])->firstOrFail()
            : TaxRate::query()
                ->where(fn ($query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))
                ->where('country', $country)
                ->where('tax_type', $taxType)
                ->whereDate('effective_from', '<=', $taxDate)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $taxDate))
                ->orderByDesc('company_id')
                ->orderByDesc('is_default')
                ->orderByDesc('effective_from')
                ->firstOrFail();

        $amount = (float) $data['amount'];
        $taxAmount = round($amount * ((float) $taxRate->rate_percent / 100), 2);

        return response()->json([
            'tax' => [
                'amount' => $amount,
                'country' => $country,
                'tax_type' => $taxType,
                'rate_percent' => (float) $taxRate->rate_percent,
                'tax_amount' => $taxAmount,
                'total_amount' => $amount + $taxAmount,
                'tax_rate_id' => $taxRate->id,
            ],
        ]);
    }

    private function settings(Request $request): CompanyLocalizationSetting
    {
        $company = $this->user($request)->company;

        return CompanyLocalizationSetting::query()->firstOrCreate(
            ['company_id' => $company->id],
            [
                'base_country' => $company->country,
                'base_currency' => $company->default_currency,
                'enabled_countries' => [$company->country],
                'enabled_currencies' => [$company->default_currency],
                'tax_rounding_mode' => 'line',
                'date_format' => 'Y-m-d',
            ],
        );
    }

    private function rate(int $companyId, string $from, string $to, string $date): float
    {
        if ($from === $to) {
            return 1.0;
        }

        $direct = ExchangeRate::query()
            ->where(fn ($query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))
            ->where('base_currency', $from)
            ->where('quote_currency', $to)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('company_id')
            ->orderByDesc('rate_date')
            ->first();

        if ($direct) {
            return (float) $direct->rate;
        }

        $inverse = ExchangeRate::query()
            ->where(fn ($query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))
            ->where('base_currency', $to)
            ->where('quote_currency', $from)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('company_id')
            ->orderByDesc('rate_date')
            ->firstOrFail();

        return round(1 / (float) $inverse->rate, 8);
    }
}
