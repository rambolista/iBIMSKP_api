<?php

namespace App\Http\Controllers\Api\BarangayId;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BarangayId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BarangayIdController extends Controller
{
    private const MENU_URL = '/barangay-id';

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json(BarangayId::query()
            ->with('resident:id,resident_number,first_name,middle_name,last_name,suffix')
            ->when($request->integer('resident_id'), fn ($query, $residentId) => $query->where('resident_id', $residentId))
            ->latest('applied_at')
            ->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');
        $data = $this->validated($request);
        unset($data['photo']);
        $data['verification_code'] = (string) Str::uuid();
        $barangayId = BarangayId::create($data);
        if ($request->hasFile('photo')) {
            $barangayId->replacePhoto($request->file('photo'));
        }
        $barangayId = $barangayId->fresh();
        AuditLog::recordCreated($request->user(), $barangayId, [
            ...array_keys($data),
            ...($request->hasFile('photo') ? ['photo_path'] : []),
        ]);

        return response()->json($this->loadBarangayId($barangayId), 201);
    }

    public function show(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadBarangayId($barangayId));
    }

    public function update(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');
        $before = $barangayId->getAttributes();
        $data = $this->validated($request, $barangayId);
        unset($data['photo']);
        $barangayId->update($data);
        if ($request->hasFile('photo')) {
            $barangayId->replacePhoto($request->file('photo'));
        }
        $barangayId = $barangayId->fresh();
        AuditLog::recordUpdated($request->user(), $barangayId, $before, [
            ...array_keys($data),
            ...($request->hasFile('photo') ? ['photo_path'] : []),
        ]);

        return response()->json($this->loadBarangayId($barangayId));
    }

    public function destroy(Request $request, BarangayId $barangayId): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');
        $before = $barangayId->getAttributes();
        $barangayId->update(['status' => 'archived', 'archived_at' => now(), 'archived_by' => $request->user()->id]);
        AuditLog::recordUpdated($request->user(), $barangayId->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Barangay ID archived.']);
    }

    private function loadBarangayId(BarangayId $barangayId): BarangayId
    {
        return $barangayId->load('resident');
    }

    private function validated(Request $request, ?BarangayId $barangayId = null): array
    {
        return $request->validate([
            'id_number' => ['required', 'string', 'max:30', Rule::unique('barangay_ids', 'id_number')->ignore($barangayId)],
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->where('status', 'active')],
            'applied_at' => ['required', 'date'],
            'issued_at' => ['nullable', 'date', 'after_or_equal:applied_at'],
            'expires_at' => ['nullable', 'date', 'after:issued_at'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'issued', 'lost', 'replaced', 'cancelled'])],
            'remarks' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
