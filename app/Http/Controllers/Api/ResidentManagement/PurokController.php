<?php

namespace App\Http\Controllers\Api\ResidentManagement;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Purok;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurokController extends Controller
{
    private const MENU_URL = '/residents/puroks';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(Purok::query()
            ->withCount(['residents', 'households'])
            ->orderBy('name')
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $purok = Purok::create($this->validated($request));
        AuditLog::recordCreated($request->user(), $purok->fresh(), array_keys($purok->getAttributes()));

        return response()->json($purok->fresh()->loadCount(['residents', 'households']), 201);
    }

    public function show(Request $request, Purok $purok): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($purok->loadCount(['residents', 'households']));
    }

    public function update(Request $request, Purok $purok): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $purok->getAttributes();
        $data = $this->validated($request, $purok);
        $purok->update($data);
        AuditLog::recordUpdated($request->user(), $purok->fresh(), $before, array_keys($data));

        return response()->json($purok->fresh()->loadCount(['residents', 'households']));
    }

    public function destroy(Request $request, Purok $purok): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        if ($purok->residents()->whereNull('archived_at')->exists() || $purok->households()->whereNull('archived_at')->exists()) {
            return response()->json(['message' => 'Archive or reassign active residents and households before archiving this purok.'], 422);
        }

        $before = $purok->getAttributes();
        $purok->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $purok->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Purok archived.']);
    }

    private function validated(Request $request, ?Purok $purok = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('puroks', 'code')->ignore($purok)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
