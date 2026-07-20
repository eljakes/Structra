<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if ($changes === []) {
            return;
        }

        $before = [];
        foreach ($changes as $attribute => $value) {
            $before[$attribute] = $model->getOriginal($attribute);
        }

        $this->record($model, 'updated', $before, $changes);
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getOriginal(), null);
    }

    private function record(Model $model, string $action, ?array $before, ?array $after): void
    {
        AuditLog::query()->create([
            'company_id' => $model->getAttribute('company_id') ?? Auth::user()?->company_id,
            'user_id' => Auth::id(),
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
