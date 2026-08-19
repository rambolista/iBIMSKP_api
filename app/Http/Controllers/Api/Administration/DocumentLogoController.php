<?php

namespace App\Http\Controllers\Api\Administration;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentLogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentLogoController extends Controller
{
    private const MENU_URL = '/apps/administration/document-logos';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(DocumentLogo::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        unset($data['photo']);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $documentLogo = DocumentLogo::create($data);

        if ($request->hasFile('photo')) {
            $documentLogo->replacePhoto($request->file('photo'));
        }

        $documentLogo = $documentLogo->fresh();
        AuditLog::recordCreated($request->user(), $documentLogo, [
            ...array_keys($data),
            ...($request->hasFile('photo') ? ['photo_path'] : []),
        ]);

        return response()->json($documentLogo, 201);
    }

    public function show(Request $request, DocumentLogo $documentLogo): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($documentLogo);
    }

    public function update(Request $request, DocumentLogo $documentLogo): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $documentLogo->getAttributes();
        $data = $this->validated($request, $documentLogo);
        unset($data['photo']);
        $data['updated_by'] = $request->user()->id;
        $documentLogo->update($data);

        if ($request->hasFile('photo')) {
            $documentLogo->replacePhoto($request->file('photo'));
        }

        $documentLogo = $documentLogo->fresh();
        AuditLog::recordUpdated($request->user(), $documentLogo, $before, [
            ...array_keys($data),
            ...($request->hasFile('photo') ? ['photo_path'] : []),
        ]);

        return response()->json($documentLogo);
    }

    public function destroy(Request $request, DocumentLogo $documentLogo): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $documentLogo->getAttributes();
        $documentLogo->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $documentLogo->fresh(), $before, ['status', 'archived_at', 'archived_by', 'updated_by']);

        return response()->json(['message' => 'Document logo archived.']);
    }

    private function validated(Request $request, ?DocumentLogo $documentLogo = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'photo' => [($documentLogo?->photo_path ? 'nullable' : 'required'), 'image', 'max:5120'],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
