<?php

namespace App\Http\Controllers\Api\Blotter;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Blotter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlotterController extends Controller
{
    private const MENU_URL = '/blotter';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(
            Blotter::query()
                ->with('resident:id,resident_number,first_name,middle_name,last_name,suffix')
                ->when($request->integer('resident_id'), fn ($query, $residentId) => $query->where('resident_id', $residentId))
                ->orderByDesc('incident_date')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        $blotter = Blotter::create($data);
        AuditLog::recordCreated($request->user(), $blotter, array_keys($data));

        return response()->json($this->loadBlotter($blotter), 201);
    }

    public function show(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadBlotter($blotter));
    }

    public function update(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $blotter->getAttributes();
        $data = $this->validated($request, $blotter);
        $blotter->update($data);
        AuditLog::recordUpdated($request->user(), $blotter->fresh(), $before, array_keys($data));

        return response()->json($this->loadBlotter($blotter->fresh()));
    }

    public function destroy(Request $request, Blotter $blotter): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $blotter->getAttributes();
        $blotter->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $blotter->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Blotter record archived.']);
    }

    private function loadBlotter(Blotter $blotter): Blotter
    {
        return $blotter->load('resident');
    }

    private function validated(Request $request, ?Blotter $blotter = null): array
    {
        return $request->validate([
            'blotter_number' => ['required', 'string', 'max:30', Rule::unique('blotters', 'blotter_number')->ignore($blotter)],
            'incident_date' => ['required', 'date'],
            'incident_time' => ['nullable', 'string', 'max:30'],
            'resident_id' => ['nullable', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'complainant_name' => ['nullable', 'string', 'max:255'],
            'respondent_name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'narrative' => ['required', 'string'],
            'action_taken' => ['nullable', 'string'],
            'settled_at' => ['nullable', 'date', 'after_or_equal:incident_date'],
            'status' => ['nullable', Rule::in(['open', 'under_mediation', 'resolved', 'dismissed'])],
            'remarks' => ['nullable', 'string'],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
