<?php

namespace App\Http\Controllers\Api\KatarungangPambarangay;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LuponPangkat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PangkatController extends Controller
{
    private const MENU_URL = '/katarungang-pambarangay/pangkat';
    private const STATUSES = ['constituted', 'active', 'completed', 'dissolved', 'inactive'];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'date_constituted' => ['nullable', 'date'],
            'case_id' => ['nullable', 'integer', Rule::exists('lupon_cases', 'id')],
            'case_number' => ['nullable', 'string', 'max:40'],
            'member' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(
            LuponPangkat::query()
                ->with([
                    'case:id,case_number,nature,assigned_lupon,status,complainant_name,respondent_name,complainant_resident_id,respondent_resident_id',
                    'case.complainantResident:id,first_name,middle_name,last_name,suffix',
                    'case.respondentResident:id,first_name,middle_name,last_name,suffix',
                    'members:id,member_id,resident_id,position,status',
                    'members.resident:id,resident_number,first_name,middle_name,last_name,suffix',
                ])
                ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
                ->when(! empty($validated['date_constituted']), fn ($query) => $query->whereDate('date_constituted', $validated['date_constituted']))
                ->when(! empty($validated['case_id']), fn ($query) => $query->where('case_id', $validated['case_id']))
                ->when(! empty($validated['case_number']), function ($query) use ($validated) {
                    $query->whereHas('case', fn ($caseQuery) => $caseQuery->where('case_number', 'like', '%'.$validated['case_number'].'%'));
                })
                ->when(! empty($validated['member']), fn ($query) => $query->where('members_summary', 'like', '%'.$validated['member'].'%'))
                ->orderByDesc('date_constituted')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'can_add');

        $data = $this->validated($request);
        $memberIds = $data['member_ids'] ?? [];
        unset($data['member_ids']);
        $pangkat = LuponPangkat::create($data)->fresh();
        $pangkat->members()->sync($memberIds);
        AuditLog::recordCreated($request->user(), $pangkat, [...array_keys($data), 'member_ids']);

        return response()->json($this->loadPangkat($pangkat), 201);
    }

    public function show(Request $request, LuponPangkat $pangkat): JsonResponse
    {
        $this->authorizeAction($request, 'can_view');

        return response()->json($this->loadPangkat($pangkat));
    }

    public function update(Request $request, LuponPangkat $pangkat): JsonResponse
    {
        $this->authorizeAction($request, 'can_edit');

        $before = $pangkat->getAttributes();
        $data = $this->validated($request, $pangkat);
        $memberIds = $data['member_ids'] ?? [];
        unset($data['member_ids']);
        $pangkat->update($data);
        $pangkat->members()->sync($memberIds);
        AuditLog::recordUpdated($request->user(), $pangkat->fresh(), $before, [...array_keys($data), 'member_ids']);

        return response()->json($this->loadPangkat($pangkat->fresh()));
    }

    public function destroy(Request $request, LuponPangkat $pangkat): JsonResponse
    {
        $this->authorizeAction($request, 'can_delete');

        $before = $pangkat->getAttributes();
        $pangkat->update([
            'status' => 'inactive',
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
        ]);
        AuditLog::recordUpdated($request->user(), $pangkat->fresh(), $before, ['status', 'archived_at', 'archived_by']);

        return response()->json(['message' => 'Pangkat archived.']);
    }

    private function loadPangkat(LuponPangkat $pangkat): LuponPangkat
    {
        return $pangkat->load([
            'case:id,case_number,date_filed,nature,assigned_lupon,status,complainant_name,respondent_name,complainant_resident_id,respondent_resident_id',
            'case.complainantResident:id,resident_number,first_name,middle_name,last_name,suffix',
            'case.respondentResident:id,resident_number,first_name,middle_name,last_name,suffix',
            'members:id,member_id,resident_id,position,status',
            'members.resident:id,resident_number,first_name,middle_name,last_name,suffix',
        ]);
    }

    private function validated(Request $request, ?LuponPangkat $pangkat = null): array
    {
        return $request->validate([
            'pangkat_id' => ['required', 'string', 'max:40', Rule::unique('lupon_pangkats', 'pangkat_id')->ignore($pangkat)],
            'case_id' => ['required', 'integer', Rule::exists('lupon_cases', 'id')->whereNull('archived_at')],
            'date_constituted' => ['required', 'date'],
            'member_ids' => ['nullable', 'array', 'max:3'],
            'member_ids.*' => ['integer', Rule::exists('lupon_members', 'id')->where('status', 'active')],
            'members_summary' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'remarks' => ['nullable', 'string'],
        ]);
    }

    private function authorizeAction(Request $request, string $action): void
    {
        abort_unless($this->userHasPermission($request->user(), self::MENU_URL, $action), 403, 'Forbidden.');
    }
}
