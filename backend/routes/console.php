<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
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
