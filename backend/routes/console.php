<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('structra:platform-admin {email} {--name=Platform Administrator} {--password=} {--create}', function (): int {
    $email = strtolower((string) $this->argument('email'));
    $name = (string) $this->option('name');
    $password = (string) ($this->option('password') ?: 'Structra'.now()->format('ymd').Str::upper(Str::random(6)).'1');

    $company = Company::query()->firstOrCreate(
        ['tenant_key' => 'navkwa-group'],
        [
            'name' => 'Navkwa Group Ltd.',
            'default_currency' => 'GHS',
            'country' => 'GH',
            'base_timezone' => 'Africa/Accra',
            'status' => 'active',
            'settings' => ['tenant_mode' => 'platform_operator'],
        ],
    );

    $branch = Branch::query()->firstOrCreate(
        ['company_id' => $company->id, 'code' => 'HQ'],
        ['name' => 'Head Office', 'country' => $company->country],
    );

    $role = Role::query()->updateOrCreate(
        ['company_id' => $company->id, 'slug' => 'platform-super-admin'],
        ['name' => 'Platform Super Admin', 'permissions' => ['platform.manage'], 'is_system' => true],
    );

    $user = User::query()->where('email', $email)->first();

    if (! $user && ! $this->option('create')) {
        $this->error('User not found. Rerun with --create to create the account.');

        return self::FAILURE;
    }

    if (! $user) {
        $user = User::query()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'role_id' => $role->id,
            'name' => $name,
            'email' => $email,
            'job_title' => 'Platform Administrator',
            'password' => $password,
            'status' => 'active',
        ]);

        $this->info("Platform administrator created for {$email}.");
        $this->line("Temporary password: {$password}");

        return self::SUCCESS;
    }

    $permissions = array_values(array_unique([...$user->accessPermissions(), 'platform.manage']));
    $user->forceFill([
        'permissions' => $permissions,
        'status' => 'active',
    ])->save();

    $this->info("Platform access granted to {$email}.");

    return self::SUCCESS;
})->purpose('Grant or create a Structra Platform Administration user.');

Artisan::command('structra:production-check {--strict : Fail unless the loaded environment is production-ready}', function (): int {
    $failures = [];
    $warnings = [];
    $strict = (bool) $this->option('strict');
    $environment = (string) config('app.env');
    $envValue = fn (string $key, mixed $default = ''): string => trim((string) env($key, $default));
    $envFlag = fn (string $key, bool $default = false): bool => filter_var(env($key, $default), FILTER_VALIDATE_BOOLEAN);
    $isLocalValue = fn (string $value): bool => Str::contains(Str::lower($value), ['localhost', '127.0.0.1', '::1']);
    $requiresHttps = function (string $key, string $value) use (&$failures, $isLocalValue): void {
        if ($value === '') {
            $failures[] = "{$key} must be set.";

            return;
        }

        if (! Str::startsWith($value, 'https://') || $isLocalValue($value)) {
            $failures[] = "{$key} must be a real HTTPS production URL.";
        }
    };

    if ($strict && $environment !== 'production') {
        $failures[] = 'APP_ENV must be production for deployment.';
    }

    if (! $strict && $environment !== 'production') {
        $warnings[] = 'Run this command with --strict after loading production environment variables.';
    }

    if ($strict || $environment === 'production') {
        if ((bool) config('app.debug')) {
            $failures[] = 'APP_DEBUG must be false.';
        }

        if ($envValue('APP_KEY') === '') {
            $failures[] = 'APP_KEY must be generated before deployment.';
        }

        if ($envValue('APP_VERSION') === '') {
            $failures[] = 'APP_VERSION must identify the release being deployed.';
        }

        $requiresHttps('APP_URL', (string) config('app.url'));
        $requiresHttps('FRONTEND_URL', $envValue('FRONTEND_URL'));

        $corsOrigins = array_values(array_filter(array_map('trim', explode(',', $envValue('CORS_ALLOWED_ORIGINS')))));
        if ($corsOrigins === []) {
            $failures[] = 'CORS_ALLOWED_ORIGINS must contain the production frontend origin.';
        }
        foreach ($corsOrigins as $origin) {
            if ($origin === '*' || ! Str::startsWith($origin, 'https://') || $isLocalValue($origin)) {
                $failures[] = "CORS_ALLOWED_ORIGINS contains an unsafe origin: {$origin}";
            }
        }

        if ($envFlag('STRUCTRA_SEED_DEVELOPMENT')) {
            $failures[] = 'STRUCTRA_SEED_DEVELOPMENT must be false in production.';
        }

        if (config('database.default') !== 'pgsql') {
            $failures[] = 'DB_CONNECTION should be pgsql for the production deployment.';
        }
        if ($envValue('DB_DATABASE') === '' || $envValue('DB_USERNAME') === '' || $envValue('DB_PASSWORD') === '') {
            $failures[] = 'DB_DATABASE, DB_USERNAME, and DB_PASSWORD must be set.';
        }
        if ($envValue('DB_PASSWORD') === 'structra_secret') {
            $failures[] = 'DB_PASSWORD must not use the local development password.';
        }
        if (! in_array($envValue('DB_SSLMODE', 'prefer'), ['require', 'verify-ca', 'verify-full'], true)) {
            $failures[] = 'DB_SSLMODE should require TLS in production.';
        }

        if (in_array((string) config('mail.default'), ['log', 'array'], true)) {
            $failures[] = 'MAIL_MAILER must send real mail in production.';
        }
        if ($envValue('MAIL_FROM_ADDRESS') === '' || Str::contains($envValue('MAIL_FROM_ADDRESS'), 'example.com')) {
            $failures[] = 'MAIL_FROM_ADDRESS must be a real sender address.';
        }
        if ((string) config('mail.default') === 'smtp' && $envValue('MAIL_HOST') === '') {
            $failures[] = 'MAIL_HOST must be set when MAIL_MAILER=smtp.';
        }

        if ((string) config('queue.default') === 'sync') {
            $failures[] = 'QUEUE_CONNECTION must use a worker-backed queue, not sync.';
        }
        if (! (bool) config('session.encrypt')) {
            $failures[] = 'SESSION_ENCRYPT must be true.';
        }
        if (! (bool) config('session.secure')) {
            $failures[] = 'SESSION_SECURE_COOKIE must be true.';
        }
        if ($envValue('LOG_LEVEL', 'debug') === 'debug') {
            $failures[] = 'LOG_LEVEL should not be debug in production.';
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $failures[] = 'Database connection failed: '.$exception->getMessage();
        }
    }

    foreach ($warnings as $warning) {
        $this->warn($warning);
    }

    if ($failures !== []) {
        $this->error('Structra production readiness check failed.');
        foreach ($failures as $failure) {
            $this->line(" - {$failure}");
        }

        return self::FAILURE;
    }

    $this->info('Structra production readiness check passed.');

    return self::SUCCESS;
})->purpose('Validate production environment and deployment safety settings.');
