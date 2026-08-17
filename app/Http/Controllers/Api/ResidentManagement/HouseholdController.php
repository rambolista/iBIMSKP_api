<?php

namespace App\Http\Controllers\Api\ResidentManagement;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HouseholdController extends Controller
{
    private const MENU_URL = '/residents/households';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(Household::query()
            ->with(['purok:id,code,name'])
            ->withCount(['residents as member_count' => fn ($query) => $query->whereNull('archived_at')])
            ->with(['residents' => fn ($query) => $query->where('is_household_head', true)->select('id', 'household_id', 'first_name', 'middle_name', 'last_name', 'suffix')])
            ->orderBy('household_number')
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $household = Household::create($this->validated($request));
        AuditLog::recordCreated($request->user(), $household->fresh(), array_keys($household->getAttributes()));

        return response()->json($this->loadHousehold($household), 201);
    }

    public function show(Request $request, Household $household): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadHousehold($household));
    }

    public function update(Request $request, Household $household): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $household->getAttributes();
        $data = $this->validated($request, $household);
        $household->update($data);
        AuditLog::recordUpdated($request->user(), $household->fresh(), $before, array_keys($data));

        return response()->json($this->loadHousehold($household->fresh()));
    }

    public function destroy(Request $request, Household $household): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        if ($household->residents()->whereNull('archived_at')->exists()) {
            return response()->json(['message' => 'Archive or move active household members before archiving this household.'], 422);
        }

        $before = $household->getAttributes();
        $household->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $household->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Household archived.']);
    }

    private function loadHousehold(Household $household): Household
    {
        return $household->load(['purok:id,code,name', 'residents' => fn ($query) => $query->whereNull('archived_at')->orderBy('last_name')->orderBy('first_name')])
            ->loadCount(['residents as member_count' => fn ($query) => $query->whereNull('archived_at')]);
    }

    private function validated(Request $request, ?Household $household = null): array
    {
        return $request->validate([
            'household_number' => ['required', 'string', 'max:30', Rule::unique('households', 'household_number')->ignore($household)],
            'name' => ['required', 'string', 'max:150'],
            'purok_id' => ['required', 'integer', Rule::exists('puroks', 'id')->where('status', 'active')],
            'house_number' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
