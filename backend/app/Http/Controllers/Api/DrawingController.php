<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Drawing;
use App\Models\DrawingRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DrawingController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $drawings = Drawing::query()
            ->forCompany($this->companyId($request))
            ->with(['project:id,code,name', 'revisions' => fn ($query) => $query->latest()])
            ->when($request->query('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($request->query('project_id'), fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->when($request->query('discipline'), fn ($query, $discipline) => $query->where('discipline', $discipline))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('drawing_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate((int) $request->query('per_page', 30));

        return response()->json($drawings);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'drawing_number' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'discipline' => ['required', Rule::in(['architectural', 'structural', 'mep', 'landscape', 'interiors', 'civil', 'other'])],
            'status' => ['nullable', Rule::in(['draft', 'issued_for_review', 'approved_for_construction', 'superseded'])],
            'revision_code' => ['nullable', 'string', 'max:24'],
            'description' => ['nullable', 'string', 'max:4000'],
            'tags' => ['nullable', 'array'],
            'linked_records' => ['nullable', 'array'],
            'file' => ['nullable', 'file', 'max:102400'],
        ]);

        $projectId = $data['project_id'] ?? null;
        $branchId = $data['branch_id'] ?? $this->user($request)->branch_id;

        if ($projectId) {
            $project = $this->projectForTenant($request, $projectId);
            $branchId = $project->branch_id;
        } else {
            Branch::query()->forCompany($companyId)->whereKey($branchId)->firstOrFail();
        }

        $drawing = DB::transaction(function () use ($request, $data, $companyId, $branchId, $projectId) {
            $revisionCode = strtoupper($data['revision_code'] ?? 'P01');

            $drawing = Drawing::query()->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'project_id' => $projectId,
                'uploaded_by' => $this->user($request)->id,
                'drawing_number' => strtoupper($data['drawing_number']),
                'title' => $data['title'],
                'discipline' => $data['discipline'],
                'status' => $data['status'] ?? 'draft',
                'current_revision' => $revisionCode,
                'description' => $data['description'] ?? null,
                'tags' => $data['tags'] ?? [],
                'linked_records' => $data['linked_records'] ?? [],
            ]);

            DrawingRevision::query()->create([
                'company_id' => $companyId,
                'drawing_id' => $drawing->id,
                'uploaded_by' => $this->user($request)->id,
                'revision_code' => $revisionCode,
                'status' => $drawing->status === 'draft' ? 'draft' : 'issued_for_review',
                'issued_at' => now(),
                ...$this->storeUploadedFile($request, $companyId, $branchId, $projectId),
            ]);

            return $drawing;
        });

        return response()->json(['drawing' => $drawing->load(['project', 'revisions'])], 201);
    }

    public function revise(Request $request, Drawing $drawing): JsonResponse
    {
        $this->assertTenant($request, $drawing);

        $data = $request->validate([
            'revision_code' => ['required', 'string', 'max:24'],
            'status' => ['nullable', Rule::in(['draft', 'issued_for_review', 'approved_for_construction'])],
            'notes' => ['nullable', 'string', 'max:4000'],
            'file' => ['nullable', 'file', 'max:102400'],
        ]);

        $revisionCode = strtoupper($data['revision_code']);

        $revision = DB::transaction(function () use ($request, $drawing, $data, $revisionCode) {
            $drawing->revisions()
                ->whereNull('superseded_at')
                ->where('revision_code', '!=', $revisionCode)
                ->update([
                    'status' => 'superseded',
                    'superseded_at' => now(),
                ]);

            $revision = DrawingRevision::query()->create([
                'company_id' => $drawing->company_id,
                'drawing_id' => $drawing->id,
                'uploaded_by' => $this->user($request)->id,
                'revision_code' => $revisionCode,
                'status' => $data['status'] ?? 'issued_for_review',
                'notes' => $data['notes'] ?? null,
                'issued_at' => now(),
                ...$this->storeUploadedFile($request, $drawing->company_id, $drawing->branch_id, $drawing->project_id),
            ]);

            $drawing->update([
                'current_revision' => $revisionCode,
                'status' => $data['status'] ?? 'issued_for_review',
            ]);

            return $revision;
        });

        return response()->json(['revision' => $revision, 'drawing' => $drawing->fresh('revisions')], 201);
    }

    public function transition(Request $request, Drawing $drawing): JsonResponse
    {
        $this->assertTenant($request, $drawing);

        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'issued_for_review', 'approved_for_construction', 'superseded'])],
        ]);

        $allowed = [
            'draft' => ['issued_for_review', 'superseded'],
            'issued_for_review' => ['approved_for_construction', 'draft', 'superseded'],
            'approved_for_construction' => ['superseded'],
            'superseded' => [],
        ];

        abort_if(! in_array($data['status'], $allowed[$drawing->status] ?? [], true), 422, 'Invalid drawing status transition.');

        $drawing->update(['status' => $data['status']]);
        $currentRevision = $drawing->revisions()
            ->where('revision_code', $drawing->current_revision)
            ->latest()
            ->first();

        $currentRevision?->update(['status' => $data['status']]);

        return response()->json(['drawing' => $drawing->fresh('revisions')]);
    }

    public function downloadRevision(Request $request, DrawingRevision $revision): StreamedResponse|JsonResponse
    {
        abort_if($revision->company_id !== $this->companyId($request), 404);
        abort_if(! $revision->file_path || ! Storage::disk('local')->exists($revision->file_path), 404, 'Drawing revision file was not found.');

        return Storage::disk('local')->download($revision->file_path, $revision->original_filename);
    }

    private function storeUploadedFile(Request $request, int $companyId, ?int $branchId, ?int $projectId): array
    {
        if (! $request->hasFile('file')) {
            return [];
        }

        $file = $request->file('file');
        $path = $file->store("structra/companies/{$companyId}/branches/{$branchId}/drawings/".($projectId ?: 'shared'), 'local');

        return [
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ];
    }

    private function assertTenant(Request $request, Drawing $drawing): void
    {
        abort_if($drawing->company_id !== $this->companyId($request), 404);
    }
}
