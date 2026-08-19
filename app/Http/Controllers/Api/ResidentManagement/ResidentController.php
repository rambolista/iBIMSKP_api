<?php

namespace App\Http\Controllers\Api\ResidentManagement;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\Resident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResidentController extends Controller
{
    private const MENU_URL = '/residents';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(Resident::query()
            ->with(['purok:id,code,name', 'household:id,household_number,name'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->resolveLocation($this->validated($request));
        unset($data['photo']);
        $resident = Resident::create($data);
        if ($request->hasFile('photo')) {
            $resident->replacePhoto($request->file('photo'));
        }
        $resident = $resident->fresh();
        AuditLog::recordCreated($request->user(), $resident, [
            ...array_keys($data),
            ...($request->hasFile('photo') ? ['photo_path'] : []),
        ]);

        return response()->json($this->loadResident($resident), 201);
    }

    public function show(Request $request, Resident $resident): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadResident($resident));
    }

    public function update(Request $request, Resident $resident): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $resident->getAttributes();
        $data = $this->resolveLocation($this->validated($request, $resident), $resident);
        unset($data['photo']);
        $resident->update($data);
        if ($request->hasFile('photo')) {
            $resident->replacePhoto($request->file('photo'));
        }
        $resident = $resident->fresh();
        AuditLog::recordUpdated($request->user(), $resident, $before, [
            ...array_keys($data),
            ...($request->hasFile('photo') ? ['photo_path'] : []),
        ]);

        return response()->json($this->loadResident($resident));
    }

    public function destroy(Request $request, Resident $resident): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $resident->getAttributes();
        $resident->update([
            'status' => 'archived',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $resident->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Resident archived.']);
    }

    public function duplicates(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date'],
            'except_id' => ['nullable', 'integer'],
        ]);

        return response()->json(Resident::query()
            ->whereRaw('LOWER(first_name) = ?', [strtolower(trim($data['first_name']))])
            ->whereRaw('LOWER(last_name) = ?', [strtolower(trim($data['last_name']))])
            ->whereDate('birth_date', $data['birth_date'])
            ->when($data['except_id'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))
            ->with(['purok:id,code,name', 'household:id,household_number'])
            ->get());
    }

    private function loadResident(Resident $resident): Resident
    {
        return $resident->load(['purok:id,code,name', 'household:id,household_number,name,purok_id,house_number,street,address,latitude,longitude']);
    }

    private function validated(Request $request, ?Resident $resident = null): array
    {
        return $request->validate([
            'resident_number' => ['required', 'string', 'max:30', Rule::unique('residents', 'resident_number')->ignore($resident)],
            'household_id' => ['required', 'integer', Rule::exists('households', 'id')->where('status', 'active')],
            'purok_id' => ['nullable', 'integer', Rule::exists('puroks', 'id')->where('status', 'active')],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['required', 'date', 'before:today'],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'civil_status' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'mobile_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'employer' => ['nullable', 'string', 'max:150'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'is_voter' => ['nullable', 'boolean'],
            'is_senior_citizen' => ['nullable', 'boolean'],
            'is_pwd' => ['nullable', 'boolean'],
            'is_4ps' => ['nullable', 'boolean'],
            'is_solo_parent' => ['nullable', 'boolean'],
            'is_household_head' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'registered_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }

    private function resolveLocation(array $data, ?Resident $resident = null): array
    {
        $householdId = array_key_exists('household_id', $data)
            ? $data['household_id']
            : $resident?->household_id;

        if (empty($householdId)) {
            return $data;
        }

        $household = Household::query()
            ->where('status', 'active')
            ->findOrFail($householdId);

        $data['purok_id'] = $household->purok_id;

        return $data;
    }
}
