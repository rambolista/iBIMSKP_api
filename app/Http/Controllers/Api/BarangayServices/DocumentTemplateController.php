<?php

namespace App\Http\Controllers\Api\BarangayServices;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentTemplateController extends Controller
{
    private const MENU_URL = '/barangay-services/document-templates';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(DocumentTemplate::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $template = DocumentTemplate::create($data);
        AuditLog::recordCreated($request->user(), $template->fresh(), array_keys($data));

        return response()->json($template->fresh(), 201);
    }

    public function show(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($documentTemplate);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $documentTemplate->getAttributes();
        $data = $this->validated($request, $documentTemplate);
        $data['updated_by'] = $request->user()->id;
        $documentTemplate->update($data);
        AuditLog::recordUpdated($request->user(), $documentTemplate->fresh(), $before, array_keys($data));

        return response()->json($documentTemplate->fresh());
    }

    public function destroy(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        if ($documentTemplate->serviceTypes()->whereNull('archived_at')->exists()) {
            return response()->json(['message' => 'This template is still attached to active service types.'], 422);
        }

        $before = $documentTemplate->getAttributes();
        $documentTemplate->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $documentTemplate->fresh(), $before, ['status', 'archived_at', 'archived_by', 'updated_by']);

        return response()->json(['message' => 'Document template archived.']);
    }

    private function validated(Request $request, ?DocumentTemplate $documentTemplate = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('document_templates', 'code')->ignore($documentTemplate)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'content_html' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'variables.*.key' => ['required_with:variables', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'variables.*.label' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
